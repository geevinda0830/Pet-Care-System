<?php
// FILE PATH: /pet_sitter/bookings.php

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in as pet sitter
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'pet_sitter') {
    $_SESSION['error_message'] = "You must be logged in as a pet sitter to access this page.";
    header("Location: ../login.php");
    exit();
}

// Include database connection
require_once '../config/db_connect.php';

$user_id = $_SESSION['user_id'];

// Function to check if a sitter is available during a specific time period
function checkSitterAvailability($conn, $sitter_id, $check_in_date, $check_in_time, $check_out_date, $check_out_time, $exclude_booking_id = null) {
    // Create datetime objects for comparison
    $requested_start = $check_in_date . ' ' . $check_in_time;
    $requested_end = $check_out_date . ' ' . $check_out_time;
    
    // Check for overlapping bookings with status 'Confirmed' or 'Completed'
    $sql = "SELECT bookingID, checkInDate, checkInTime, checkOutDate, checkOutTime 
            FROM booking 
            WHERE sitterID = ? 
            AND status IN ('Confirmed', 'Completed')
            AND (
                (CONCAT(checkInDate, ' ', checkInTime) < ? AND CONCAT(checkOutDate, ' ', checkOutTime) > ?) OR
                (CONCAT(checkInDate, ' ', checkInTime) < ? AND CONCAT(checkOutDate, ' ', checkOutTime) > ?) OR
                (CONCAT(checkInDate, ' ', checkInTime) >= ? AND CONCAT(checkOutDate, ' ', checkOutTime) <= ?)
            )";
    
    $params = [$sitter_id, $requested_end, $requested_start, $requested_start, $requested_end, $requested_start, $requested_end];
    
    // If we're updating an existing booking, exclude it from the check
    if ($exclude_booking_id !== null) {
        $sql .= " AND bookingID != ?";
        $params[] = $exclude_booking_id;
    }
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(str_repeat('s', count($params) - 1) . 'i', ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $conflicts = [];
    while ($row = $result->fetch_assoc()) {
        $conflicts[] = $row;
    }
    
    $stmt->close();
    
    return empty($conflicts) ? ['available' => true] : ['available' => false, 'conflicts' => $conflicts];
}

// Process booking update if requested
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_status'])) {
    $booking_id = $_POST['booking_id'];
    $new_status = $_POST['new_status'];
    
    // Check if booking belongs to this sitter
    $check_sql = "SELECT * FROM booking WHERE bookingID = ? AND sitterID = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ii", $booking_id, $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $booking_data = $check_result->fetch_assoc();
        
        // If accepting a booking, check for conflicts
        if ($new_status === 'Confirmed') {
            $availability = checkSitterAvailability(
                $conn, 
                $user_id, 
                $booking_data['checkInDate'], 
                $booking_data['checkInTime'], 
                $booking_data['checkOutDate'], 
                $booking_data['checkOutTime'],
                $booking_id  // Exclude current booking from conflict check
            );
            
            if (!$availability['available']) {
                $_SESSION['error_message'] = "Cannot accept booking - you have conflicting bookings during this time period. Please check your schedule and try again.";
                header("Location: bookings.php");
                exit();
            }
        }
        
        // Update booking status
        $update_sql = "UPDATE booking SET status = ? WHERE bookingID = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("si", $new_status, $booking_id);
        
        if ($update_stmt->execute()) {
            $_SESSION['success_message'] = "Booking status updated successfully.";
        } else {
            $_SESSION['error_message'] = "Failed to update booking status: " . $conn->error;
        }
        
        $update_stmt->close();
    } else {
        $_SESSION['error_message'] = "Booking not found or does not belong to you.";
    }
    
    $check_stmt->close();
    
    // Redirect to refresh page
    header("Location: bookings.php");
    exit();
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'newest';

// Prepare SQL query
$sql = "SELECT b.*, 
               po.fullName as ownerName, 
               po.contact as ownerContact, 
               po.email as ownerEmail, 
               p.petName, 
               p.type as petType, 
               p.breed as petBreed, 
               p.age as petAge, 
               p.image as petImage,
               (SELECT COUNT(*) FROM payment WHERE bookingID = b.bookingID AND status = 'Completed') as is_paid
        FROM booking b 
        JOIN pet_owner po ON b.userID = po.userID
        JOIN pet_profile p ON b.petID = p.petID
        WHERE b.sitterID = ?";

$params = [$user_id];
$param_types = "i";

// Add status filter if specified
if (!empty($status_filter)) {
    if ($status_filter === 'active') {
        $sql .= " AND b.status IN ('Pending', 'Confirmed')";
    } else {
        $sql .= " AND b.status = ?";
        $params[] = $status_filter;
        $param_types .= "s";
    }
}

// Add sorting
switch ($sort_by) {
    case 'oldest':
        $sql .= " ORDER BY b.checkInDate ASC, b.checkInTime ASC";
        break;
    case 'upcoming':
        $sql .= " ORDER BY b.checkInDate ASC, b.checkInTime ASC";
        break;
    case 'pet_name':
        $sql .= " ORDER BY p.petName ASC";
        break;
    case 'owner_name':
        $sql .= " ORDER BY po.fullName ASC";
        break;
    case 'status':
        $sql .= " ORDER BY b.status ASC, b.checkInDate DESC";
        break;
    default: // newest
        $sql .= " ORDER BY b.checkInDate DESC, b.checkInTime DESC";
        break;
}

$stmt = $conn->prepare($sql);
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$bookings_result = $stmt->get_result();
$bookings = [];

while ($row = $bookings_result->fetch_assoc()) {
    $bookings[] = $row;
}

$stmt->close();

// Get statistics for the dashboard
$stats_sql = "SELECT 
                 COUNT(CASE WHEN status = 'Pending' THEN 1 END) as pending_count,
                 COUNT(CASE WHEN status = 'Confirmed' THEN 1 END) as confirmed_count,
                 COUNT(CASE WHEN status = 'Completed' THEN 1 END) as completed_count,
                 COUNT(*) as total_count
              FROM booking WHERE sitterID = ?";
$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param("i", $user_id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();
$stats_stmt->close();

// Include header
include_once '../includes/header.php';
?>

<style>
.page-header-modern {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 80px 0;
    position: relative;
    overflow: hidden;
}

.page-badge {
    background: rgba(255, 255, 255, 0.2);
    padding: 8px 16px;
    border-radius: 50px;
    font-size: 0.9rem;
    font-weight: 600;
    margin-bottom: 16px;
    display: inline-block;
    backdrop-filter: blur(10px);
}

.page-title {
    font-size: 3.5rem;
    font-weight: 800;
    line-height: 1.2;
    margin-bottom: 16px;
}

.text-gradient {
    background: linear-gradient(45deg, #ffd700, #ffed4e);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.page-actions {
    text-align: right;
    position: relative;
    z-index: 2;
}

.btn-primary-gradient {
    background: linear-gradient(135deg, #667eea, #764ba2);
    border: none;
    color: white;
    border-radius: 50px;
    padding: 12px 24px;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-primary-gradient:hover {
    background: linear-gradient(135deg, #5a6fd8, #6b46a3);
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
    color: white;
}

.btn-outline-light {
    border: 2px solid rgba(255, 255, 255, 0.3);
    color: white;
    border-radius: 50px;
    padding: 10px 22px;
    font-weight: 600;
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
}

.btn-outline-light:hover {
    background: rgba(255, 255, 255, 0.2);
    border-color: rgba(255, 255, 255, 0.5);
    color: white;
}

.stats-section {
    padding: 60px 0;
    background: #f8f9ff;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 24px;
}

.stat-item {
    background: white;
    border-radius: 20px;
    padding: 32px;
    text-align: center;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(0, 0, 0, 0.05);
    transition: all 0.3s ease;
}

.stat-item:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 16px;
    font-size: 1.5rem;
    color: white;
}

.stat-icon.pending { background: linear-gradient(135deg, #f59e0b, #d97706); }
.stat-icon.confirmed { background: linear-gradient(135deg, #10b981, #059669); }
.stat-icon.completed { background: linear-gradient(135deg, #3b82f6, #1d4ed8); }
.stat-icon.total { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }

.stat-content h3 {
    font-size: 2.5rem;
    font-weight: 800;
    color: #1e293b;
    margin-bottom: 8px;
}

.stat-content p {
    color: #64748b;
    font-weight: 500;
    margin: 0;
}

.bookings-filter-section {
    padding: 40px 0;
    background: white;
}

.filter-card {
    background: white;
    border-radius: 20px;
    padding: 32px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(0, 0, 0, 0.05);
}

.filter-label {
    font-weight: 600;
    color: #374151;
    margin-bottom: 8px;
    display: block;
}

.modern-select {
    border-radius: 12px;
    border: 2px solid #e5e7eb;
    padding: 12px 16px;
    transition: all 0.3s ease;
}

.modern-select:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
}

.bookings-content-section {
    padding: 60px 0;
    background: #f8fafc;
}

.bookings-header-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 32px;
}

.booking-count {
    color: #667eea;
    font-weight: 600;
}

.bookings-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
    gap: 24px;
}

.booking-card-modern {
    background: white;
    border-radius: 20px;
    padding: 24px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(0, 0, 0, 0.05);
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.booking-card-modern:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
}

.booking-header {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 20px;
}

.pet-avatar {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    object-fit: cover;
    border: 3px solid #f1f5f9;
    flex-shrink: 0;
}

.booking-info h6 {
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 4px;
}

.pet-details {
    color: #64748b;
    font-size: 0.9rem;
    margin-bottom: 4px;
}

.owner-name {
    color: #667eea;
    font-weight: 500;
    font-size: 0.85rem;
}

.status-badge {
    padding: 8px 16px;
    border-radius: 25px;
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    position: absolute;
    top: 16px;
    right: 16px;
}

.status-pending { background: #fef3c7; color: #92400e; }
.status-confirmed { background: #d1fae5; color: #065f46; }
.status-completed { background: #dbeafe; color: #1e40af; }
.status-cancelled { background: #fee2e2; color: #991b1b; }

.booking-schedule {
    background: #f8fafc;
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 20px;
}

.schedule-item {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 8px;
}

.schedule-item:last-child {
    margin-bottom: 0;
}

.schedule-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
    color: white;
}

.schedule-icon.checkin { background: linear-gradient(135deg, #10b981, #059669); }
.schedule-icon.checkout { background: linear-gradient(135deg, #ef4444, #dc2626); }

.booking-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.btn-action {
    padding: 8px 16px;
    border-radius: 8px;
    font-size: 0.85rem;
    font-weight: 500;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
    flex: 1;
    min-width: 100px;
}

.btn-success {
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
}

.btn-success:hover {
    background: linear-gradient(135deg, #059669, #047857);
    color: white;
}

.btn-danger {
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: white;
}

.btn-danger:hover {
    background: linear-gradient(135deg, #dc2626, #b91c1c);
    color: white;
}

.btn-info {
    background: linear-gradient(135deg, #3b82f6, #1d4ed8);
    color: white;
}

.btn-info:hover {
    background: linear-gradient(135deg, #1d4ed8, #1e40af);
    color: white;
}

.btn-outline-primary {
    border: 2px solid #667eea;
    color: #667eea;
    background: transparent;
}

.btn-outline-primary:hover {
    background: #667eea;
    color: white;
}

.empty-bookings-state {
    text-align: center;
    padding: 80px 20px;
}

.empty-icon {
    font-size: 4rem;
    color: #cbd5e1;
    margin-bottom: 24px;
}

.empty-bookings-state h3 {
    color: #475569;
    margin-bottom: 12px;
}

.empty-bookings-state p {
    color: #64748b;
    margin-bottom: 32px;
}

.empty-actions {
    display: flex;
    gap: 16px;
    justify-content: center;
    flex-wrap: wrap;
}

.btn-outline-secondary {
    border: 2px solid #e5e7eb;
    color: #6b7280;
    background: transparent;
    border-radius: 50px;
    padding: 12px 24px;
    font-weight: 600;
}

.btn-outline-secondary:hover {
    background: #f3f4f6;
    border-color: #d1d5db;
    color: #374151;
}
</style>

<!-- Modern Page Header -->
<section class="page-header-modern">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <div class="page-header-content">
                    <span class="page-badge">📅 Pet Sitter Dashboard</span>
                    <h1 class="page-title">My <span class="text-gradient">Bookings</span></h1>
                    <p class="page-subtitle">Manage and track your pet sitting appointments with pet owners</p>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="page-actions">
                    <a href="dashboard.php" class="btn btn-outline-light">
                        <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                    </a>
                    <a href="profile.php" class="btn btn-primary-gradient">
                        <i class="fas fa-user me-2"></i> My Profile
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Statistics Section -->
<section class="stats-section">
    <div class="container">
        <div class="stats-grid">
            <div class="stat-item">
                <div class="stat-icon pending">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['pending_count']; ?></h3>
                    <p>Pending Requests</p>
                </div>
            </div>
            
            <div class="stat-item">
                <div class="stat-icon confirmed">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['confirmed_count']; ?></h3>
                    <p>Confirmed Bookings</p>
                </div>
            </div>
            
            <div class="stat-item">
                <div class="stat-icon completed">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['completed_count']; ?></h3>
                    <p>Completed Jobs</p>
                </div>
            </div>
            
            <div class="stat-item">
                <div class="stat-icon total">
                    <i class="fas fa-list"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['total_count']; ?></h3>
                    <p>Total Bookings</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Success/Error Messages -->
<div class="container">
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show mt-3">
            <i class="fas fa-check-circle me-2"></i>
            <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show mt-3">
            <i class="fas fa-exclamation-circle me-2"></i>
            <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
</div>

<!-- Filter and Search Section -->
<section class="bookings-filter-section">
    <div class="container">
        <div class="filter-card">
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="get" class="filter-form">
                <div class="row g-3 align-items-end">
                    <div class="col-lg-4">
                        <label class="filter-label">Filter by Status</label>
                        <select class="form-select modern-select" name="status">
                            <option value="" <?php echo empty($status_filter) ? 'selected' : ''; ?>>All Bookings</option>
                            <option value="Pending" <?php echo ($status_filter === 'Pending') ? 'selected' : ''; ?>>Pending Requests</option>
                            <option value="Confirmed" <?php echo ($status_filter === 'Confirmed') ? 'selected' : ''; ?>>Confirmed Bookings</option>
                            <option value="Completed" <?php echo ($status_filter === 'Completed') ? 'selected' : ''; ?>>Completed Jobs</option>
                            <option value="Cancelled" <?php echo ($status_filter === 'Cancelled') ? 'selected' : ''; ?>>Cancelled Bookings</option>
                            <option value="active" <?php echo ($status_filter === 'active') ? 'selected' : ''; ?>>Active (Pending & Confirmed)</option>
                        </select>
                    </div>
                    
                    <div class="col-lg-4">
                        <label class="filter-label">Sort By</label>
                        <select class="form-select modern-select" name="sort_by">
                            <option value="newest" <?php echo ($sort_by === 'newest') ? 'selected' : ''; ?>>Newest First</option>
                            <option value="oldest" <?php echo ($sort_by === 'oldest') ? 'selected' : ''; ?>>Oldest First</option>
                            <option value="upcoming" <?php echo ($sort_by === 'upcoming') ? 'selected' : ''; ?>>Upcoming Dates</option>
                            <option value="pet_name" <?php echo ($sort_by === 'pet_name') ? 'selected' : ''; ?>>Pet Name</option>
                            <option value="owner_name" <?php echo ($sort_by === 'owner_name') ? 'selected' : ''; ?>>Owner Name</option>
                            <option value="status" <?php echo ($sort_by === 'status') ? 'selected' : ''; ?>>By Status</option>
                        </select>
                    </div>
                    
                    <div class="col-lg-2">
                        <button type="submit" class="btn btn-primary-gradient w-100">
                            <i class="fas fa-filter me-2"></i> Apply
                        </button>
                    </div>
                    
                    <div class="col-lg-2">
                        <a href="bookings.php" class="btn btn-outline-secondary w-100">
                            <i class="fas fa-undo me-2"></i> Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</section>

<!-- Bookings Content -->
<section class="bookings-content-section">
    <div class="container">
        <?php if (empty($bookings)): ?>
            <div class="empty-bookings-state">
                <div class="empty-icon">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <h3>No Bookings Found</h3>
                <p>You don't have any bookings<?php echo !empty($status_filter) ? " with status '$status_filter'" : ""; ?> yet.</p>
                <div class="empty-actions">
                    <a href="profile.php" class="btn btn-primary-gradient">
                        <i class="fas fa-eye me-2"></i> View Your Profile
                    </a>
                    <a href="services.php" class="btn btn-outline-primary">
                        <i class="fas fa-plus me-2"></i> Add Services
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="bookings-header-bar">
                <h3>Your Bookings <span class="booking-count">(<?php echo count($bookings); ?> <?php echo count($bookings) === 1 ? 'booking' : 'bookings'; ?>)</span></h3>
            </div>
            
            <div class="bookings-grid">
                <?php foreach ($bookings as $booking): ?>
                    <div class="booking-card-modern">
                        <span class="status-badge status-<?php echo strtolower($booking['status']); ?>">
                            <?php echo $booking['status']; ?>
                        </span>
                        
                        <div class="booking-header">
                            <?php if (!empty($booking['petImage']) && file_exists('../assets/images/pets/' . $booking['petImage'])): ?>
                                <img src="../assets/images/pets/<?php echo htmlspecialchars($booking['petImage']); ?>" 
                                     alt="<?php echo htmlspecialchars($booking['petName']); ?>" 
                                     class="pet-avatar">
                            <?php else: ?>
                                <div class="pet-avatar bg-light d-flex align-items-center justify-content-center">
                                    <i class="fas fa-paw text-muted"></i>
                                </div>
                            <?php endif; ?>
                            
                            <div class="booking-info">
                                <h6><?php echo htmlspecialchars($booking['petName']); ?></h6>
                                <div class="pet-details">
                                    <?php echo htmlspecialchars($booking['petType']) . ' • ' . htmlspecialchars($booking['petBreed']); ?>
                                    <?php if (!empty($booking['petAge'])): ?>
                                        • <?php echo htmlspecialchars($booking['petAge']); ?> years
                                    <?php endif; ?>
                                </div>
                                <div class="owner-name">
                                    <i class="fas fa-user me-1"></i>
                                    <?php echo htmlspecialchars($booking['ownerName']); ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="booking-schedule">
                            <div class="schedule-item">
                                <div class="schedule-icon checkin">
                                    <i class="fas fa-play"></i>
                                </div>
                                <div>
                                    <strong>Check-in:</strong> 
                                    <?php echo date('M d, Y', strtotime($booking['checkInDate'])); ?> at 
                                    <?php echo date('g:i A', strtotime($booking['checkInTime'])); ?>
                                </div>
                            </div>
                            
                            <div class="schedule-item">
                                <div class="schedule-icon checkout">
                                    <i class="fas fa-stop"></i>
                                </div>
                                <div>
                                    <strong>Check-out:</strong> 
                                    <?php echo date('M d, Y', strtotime($booking['checkOutDate'])); ?> at 
                                    <?php echo date('g:i A', strtotime($booking['checkOutTime'])); ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="booking-actions">
                            <a href="booking_details.php?id=<?php echo $booking['bookingID']; ?>" class="btn btn-outline-primary btn-action">
                                <i class="fas fa-eye me-1"></i> Details
                            </a>
                            
                            <?php if ($booking['status'] === 'Pending'): ?>
                                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="d-inline">
                                    <input type="hidden" name="booking_id" value="<?php echo $booking['bookingID']; ?>">
                                    <input type="hidden" name="new_status" value="Confirmed">
                                    <button type="submit" name="update_status" class="btn btn-success btn-action" onclick="return confirm('Accept this booking?')">
                                        <i class="fas fa-check me-1"></i> Accept
                                    </button>
                                </form>
                                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="d-inline">
                                    <input type="hidden" name="booking_id" value="<?php echo $booking['bookingID']; ?>">
                                    <input type="hidden" name="new_status" value="Cancelled">
                                    <button type="submit" name="update_status" class="btn btn-danger btn-action" onclick="return confirm('Decline this booking?')">
                                        <i class="fas fa-times me-1"></i> Decline
                                    </button>
                                </form>
                            <?php elseif ($booking['status'] === 'Confirmed'): ?>
                                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="d-inline">
                                    <input type="hidden" name="booking_id" value="<?php echo $booking['bookingID']; ?>">
                                    <input type="hidden" name="new_status" value="Completed">
                                    <button type="submit" name="update_status" class="btn btn-info btn-action" onclick="return confirm('Mark as completed?')">
                                        <i class="fas fa-flag-checkered me-1"></i> Complete
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php include_once '../includes/footer.php'; ?>