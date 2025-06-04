<?php
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

// Prepare SQL query - Fixed table joins and field names
$sql = "SELECT b.*, 
               po.fullName as ownerName, 
               po.contact as ownerContact, 
               po.email as ownerEmail, 
               p.petName, 
               p.type as petType, 
               p.breed as petBreed, 
               p.age as petAge, 
               p.image as petImage
        FROM booking b 
        JOIN pet_owner po ON b.userID = po.userID
        JOIN pet_profile p ON b.petID = p.petID
        WHERE b.sitterID = ?";

// Add status filter if specified
if ($status_filter === 'active') {
    $sql .= " AND b.status IN ('Pending', 'Confirmed')";
} elseif ($status_filter === 'completed') {
    $sql .= " AND b.status = 'Completed'";
} elseif ($status_filter === 'cancelled') {
    $sql .= " AND b.status = 'Cancelled'";
} elseif ($status_filter === 'pending') {
    $sql .= " AND b.status = 'Pending'";
} elseif ($status_filter === 'confirmed') {
    $sql .= " AND b.status = 'Confirmed'";
} elseif (!empty($status_filter)) {
    $sql .= " AND b.status = ?";
}

// Add sorting
switch ($sort_by) {
    case 'oldest':
        $sql .= " ORDER BY b.bookingID ASC";
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
    case 'newest':
    default:
        $sql .= " ORDER BY b.bookingID DESC";
        break;
}

// Prepare and execute the query
$stmt = $conn->prepare($sql);

if ($status_filter === 'active' || $status_filter === 'completed' || $status_filter === 'cancelled' || $status_filter === 'pending' || $status_filter === 'confirmed' || empty($status_filter)) {
    $stmt->bind_param("i", $user_id);
} else {
    $stmt->bind_param("is", $user_id, $status_filter);
}

$stmt->execute();
$result = $stmt->get_result();
$bookings = [];

while ($row = $result->fetch_assoc()) {
    $bookings[] = $row;
}

$stmt->close();

// Include header
include_once '../includes/header.php';
?>

<!-- Modern Bookings Header -->
<section class="bookings-header-section">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <div class="bookings-header-content">
                    <span class="section-badge">📅 Booking Management</span>
                    <h1 class="bookings-title">My <span class="text-gradient">Bookings</span></h1>
                    <p class="bookings-subtitle">Manage your pet sitting appointments and build lasting relationships with clients.</p>
                </div>
            </div>
            <div class="col-lg-4 text-lg-end">
                <a href="dashboard.php" class="btn btn-outline-light btn-lg">
                    <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </div>
</section>

<!-- Success/Error Messages -->
<?php if (isset($_SESSION['success_message'])): ?>
    <div class="container mt-3">
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['error_message'])): ?>
    <div class="container mt-3">
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    </div>
<?php endif; ?>

<!-- Quick Stats -->
<section class="bookings-stats-section">
    <div class="container">
        <div class="stats-grid">
            <?php
            $pending_count = count(array_filter($bookings, function($b) { return $b['status'] === 'Pending'; }));
            $confirmed_count = count(array_filter($bookings, function($b) { return $b['status'] === 'Confirmed'; }));
            $completed_count = count(array_filter($bookings, function($b) { return $b['status'] === 'Completed'; }));
            ?>
            
            <div class="stat-item">
                <div class="stat-icon pending">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $pending_count; ?></h3>
                    <p>Pending Requests</p>
                </div>
            </div>
            
            <div class="stat-item">
                <div class="stat-icon confirmed">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $confirmed_count; ?></h3>
                    <p>Confirmed Bookings</p>
                </div>
            </div>
            
            <div class="stat-item">
                <div class="stat-icon completed">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $completed_count; ?></h3>
                    <p>Completed Jobs</p>
                </div>
            </div>
            
            <div class="stat-item">
                <div class="stat-icon total">
                    <i class="fas fa-list"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo count($bookings); ?></h3>
                    <p>Total Bookings</p>
                </div>
            </div>
        </div>
    </div>
</section>

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
                            <option value="pending" <?php echo ($status_filter === 'pending') ? 'selected' : ''; ?>>Pending Requests</option>
                            <option value="confirmed" <?php echo ($status_filter === 'confirmed') ? 'selected' : ''; ?>>Confirmed Bookings</option>
                            <option value="completed" <?php echo ($status_filter === 'completed') ? 'selected' : ''; ?>>Completed Jobs</option>
                            <option value="cancelled" <?php echo ($status_filter === 'cancelled') ? 'selected' : ''; ?>>Cancelled Bookings</option>
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
                    <a href="../pet_sitters.php" class="btn btn-primary-gradient">
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
            
            <!-- Bookings Grid -->
            <div class="bookings-grid">
                <?php foreach ($bookings as $booking): ?>
                    <div class="booking-card-modern">
                        <div class="booking-header">
                            <div class="pet-info">
                                <div class="pet-avatar">
                                    <?php if (!empty($booking['petImage'])): ?>
                                        <img src="../assets/images/pets/<?php echo htmlspecialchars($booking['petImage']); ?>" alt="<?php echo htmlspecialchars($booking['petName']); ?>">
                                    <?php else: ?>
                                        <div class="pet-initial"><?php echo strtoupper(substr($booking['petName'], 0, 1)); ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="pet-details">
                                    <h5><?php echo htmlspecialchars($booking['petName']); ?></h5>
                                    <p><?php echo htmlspecialchars($booking['petType']); ?> • <?php echo htmlspecialchars($booking['petBreed']); ?> • <?php echo $booking['petAge']; ?> years</p>
                                </div>
                            </div>
                            <div class="booking-status">
                                <?php
                                $status_class = '';
                                switch ($booking['status']) {
                                    case 'Pending':
                                        $status_class = 'pending';
                                        break;
                                    case 'Confirmed':
                                        $status_class = 'confirmed';
                                        break;
                                    case 'Cancelled':
                                        $status_class = 'cancelled';
                                        break;
                                    case 'Completed':
                                        $status_class = 'completed';
                                        break;
                                }
                                ?>
                                <span class="status-badge <?php echo $status_class; ?>"><?php echo $booking['status']; ?></span>
                            </div>
                        </div>
                        
                        <div class="booking-details">
                            <div class="owner-info">
                                <i class="fas fa-user"></i>
                                <span><?php echo htmlspecialchars($booking['ownerName']); ?></span>
                            </div>
                            <div class="contact-info">
                                <i class="fas fa-phone"></i>
                                <span><?php echo htmlspecialchars($booking['ownerContact']); ?></span>
                            </div>
                            <div class="date-info">
                                <i class="fas fa-calendar"></i>
                                <span><?php echo date('M d, Y', strtotime($booking['checkInDate'])); ?> - <?php echo date('M d, Y', strtotime($booking['checkOutDate'])); ?></span>
                            </div>
                            <div class="time-info">
                                <i class="fas fa-clock"></i>
                                <span><?php echo date('h:i A', strtotime($booking['checkInTime'])); ?> - <?php echo date('h:i A', strtotime($booking['checkOutTime'])); ?></span>
                            </div>
                        </div>
                        
                        <div class="booking-actions">
                            <a href="booking_details.php?id=<?php echo $booking['bookingID']; ?>" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-eye me-1"></i> View Details
                            </a>
                            
                            <?php if ($booking['status'] === 'Pending'): ?>
                                <div class="action-buttons">
                                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="d-inline">
                                        <input type="hidden" name="booking_id" value="<?php echo $booking['bookingID']; ?>">
                                        <input type="hidden" name="new_status" value="Confirmed">
                                        <button type="submit" name="update_status" class="btn btn-success btn-sm" onclick="return confirm('Accept this booking?')">
                                            <i class="fas fa-check me-1"></i> Accept
                                        </button>
                                    </form>
                                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="d-inline">
                                        <input type="hidden" name="booking_id" value="<?php echo $booking['bookingID']; ?>">
                                        <input type="hidden" name="new_status" value="Cancelled">
                                        <button type="submit" name="update_status" class="btn btn-danger btn-sm" onclick="return confirm('Decline this booking?')">
                                            <i class="fas fa-times me-1"></i> Decline
                                        </button>
                                    </form>
                                </div>
                            <?php elseif ($booking['status'] === 'Confirmed' && strtotime($booking['checkOutDate']) <= time()): ?>
                                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="d-inline">
                                    <input type="hidden" name="booking_id" value="<?php echo $booking['bookingID']; ?>">
                                    <input type="hidden" name="new_status" value="Completed">
                                    <button type="submit" name="update_status" class="btn btn-info btn-sm" onclick="return confirm('Mark this booking as completed?')">
                                        <i class="fas fa-check-circle me-1"></i> Mark Complete
                                    </button>
                                </form>
                            <?php elseif ($booking['status'] === 'Completed'): ?>
                                <a href="add_pet_ranking.php?booking_id=<?php echo $booking['bookingID']; ?>" class="btn btn-warning btn-sm">
                                    <i class="fas fa-star me-1"></i> Rate Pet
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<style>
.bookings-header-section {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 80px 0;
    position: relative;
    overflow: hidden;
}

.bookings-header-section::before {
    content: '';
    position: absolute;
    width: 100%;
    height: 100%;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="20" cy="20" r="2" fill="rgba(255,255,255,0.1)"/><circle cx="80" cy="40" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="40" cy="80" r="1.5" fill="rgba(255,255,255,0.1)"/></svg>');
    animation: float 20s infinite linear;
}

.bookings-title {
    font-size: 3.5rem;
    font-weight: 800;
    line-height: 1.2;
    margin-bottom: 24px;
}

.bookings-subtitle {
    font-size: 1.2rem;
    opacity: 0.9;
}

.bookings-stats-section {
    padding: 0;
    margin-top: -40px;
    position: relative;
    z-index: 2;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 24px;
    padding: 0 15px;
}

.stat-item {
    background: white;
    border-radius: 16px;
    padding: 24px;
    display: flex;
    align-items: center;
    gap: 16px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
    border: 1px solid rgba(0, 0, 0, 0.05);
}

.stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    color: white;
}

.stat-icon.pending { background: linear-gradient(135deg, #f59e0b, #d97706); }
.stat-icon.confirmed { background: linear-gradient(135deg, #10b981, #059669); }
.stat-icon.completed { background: linear-gradient(135deg, #3b82f6, #1d4ed8); }
.stat-icon.total { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }

.stat-content h3 {
    font-size: 1.8rem;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 4px;
}

.stat-content p {
    color: #64748b;
    font-size: 0.9rem;
    margin: 0;
}

.bookings-filter-section {
    padding: 60px 0 40px;
    background: #f8f9ff;
}

.filter-card {
    background: white;
    border-radius: 20px;
    padding: 32px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(0, 0, 0, 0.05);
}

.filter-label {
    font-weight: 600;
    color: #374151;
    margin-bottom: 8px;
    display: block;
    font-size: 0.9rem;
}

.modern-select {
    padding: 12px 16px;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    transition: all 0.3s ease;
}

.modern-select:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.bookings-content-section {
    padding: 40px 0 80px;
    background: #f8f9ff;
}

.empty-bookings-state {
    text-align: center;
    padding: 100px 40px;
    background: white;
    border-radius: 24px;
    border: 2px dashed #d1d5db;
}

.empty-icon {
    font-size: 5rem;
    color: #cbd5e1;
    margin-bottom: 32px;
}

.empty-bookings-state h3 {
    font-size: 2rem;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 16px;
}

.empty-bookings-state p {
    font-size: 1.1rem;
    color: #64748b;
    margin-bottom: 32px;
}

.empty-actions {
    display: flex;
    gap: 16px;
    justify-content: center;
}

.bookings-header-bar {
    margin-bottom: 32px;
    background: white;
    padding: 24px 32px;
    border-radius: 16px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
}

.bookings-header-bar h3 {
    font-size: 1.5rem;
    font-weight: 700;
    color: #1e293b;
    margin: 0;
}

.booking-count {
    color: #64748b;
    font-weight: 500;
    font-size: 1rem;
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
}

.booking-card-modern:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
}

.booking-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 20px;
}

.pet-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.pet-avatar {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    overflow: hidden;
    background: linear-gradient(135deg, #667eea, #764ba2);
    display: flex;
    align-items: center;
    justify-content: center;
}

.pet-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.pet-initial {
    color: white;
    font-weight: 600;
    font-size: 1.2rem;
}

.pet-details h5 {
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 4px;
}

.pet-details p {
    color: #64748b;
    font-size: 0.85rem;
    margin: 0;
}

.status-badge {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
}

.status-badge.pending { background: #fef3c7; color: #92400e; }
.status-badge.confirmed { background: #dcfce7; color: #166534; }
.status-badge.completed { background: #dbeafe; color: #1e40af; }
.status-badge.cancelled { background: #fee2e2; color: #991b1b; }

.booking-details {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-bottom: 20px;
}

.booking-details > div {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #64748b;
    font-size: 0.9rem;
}

.booking-details i {
    width: 16px;
    color: #667eea;
}

.booking-actions {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}

.booking-actions .btn {
    flex: 1;
    min-width: 100px;
}

.action-buttons {
    display: flex;
    gap: 8px;
    flex: 1;
}

.action-buttons .btn {
    flex: 1;
}

@media (max-width: 991px) {
    .bookings-title {
        font-size: 2.5rem;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .bookings-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .bookings-header-section {
        text-align: center;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .booking-actions {
        flex-direction: column;
    }
    
    .action-buttons {
        flex-direction: column;
    }
    
    .empty-actions {
        flex-direction: column;
        align-items: center;
    }
    
    .filter-card {
        padding: 24px;
    }
}
</style>

<?php
// Include footer
include_once '../includes/footer.php';

// Close database connection
$conn->close();
?>