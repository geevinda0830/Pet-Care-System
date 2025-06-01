<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in as pet owner
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'pet_owner') {
    $_SESSION['error_message'] = "You must be logged in as a pet owner to access this page.";
    header("Location: ../login.php");
    exit();
}

// Include database connection
require_once '../config/db_connect.php';

$user_id = $_SESSION['user_id'];

// Process booking cancellation if requested
if (isset($_POST['cancel_booking']) && isset($_POST['booking_id'])) {
    $booking_id = $_POST['booking_id'];
    
    // Check if booking belongs to user and can be cancelled
    $check_sql = "SELECT * FROM booking WHERE bookingID = ? AND userID = ? AND status IN ('Pending', 'Confirmed')";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ii", $booking_id, $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        // Update booking status to cancelled
        $update_sql = "UPDATE booking SET status = 'Cancelled' WHERE bookingID = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("i", $booking_id);
        
        if ($update_stmt->execute()) {
            $_SESSION['success_message'] = "Booking cancelled successfully.";
        } else {
            $_SESSION['error_message'] = "Failed to cancel booking: " . $conn->error;
        }
        
        $update_stmt->close();
    } else {
        $_SESSION['error_message'] = "Booking not found or cannot be cancelled.";
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
$sql = "SELECT b.*, p.petName, ps.fullName as sitterName, ps.service 
        FROM booking b 
        JOIN pet_profile p ON b.petID = p.petID 
        JOIN pet_sitter ps ON b.sitterID = ps.userID 
        WHERE b.userID = ?";

// Add status filter if specified
if (!empty($status_filter)) {
    $sql .= " AND b.status = ?";
}

// Add sorting
switch ($sort_by) {
    case 'oldest':
        $sql .= " ORDER BY b.created_at ASC";
        break;
    case 'upcoming':
        $sql .= " ORDER BY b.checkInDate ASC, b.checkInTime ASC";
        break;
    case 'completed':
        $sql .= " ORDER BY b.checkOutDate DESC, b.checkOutTime DESC";
        break;
    case 'newest':
    default:
        $sql .= " ORDER BY b.created_at DESC";
        break;
}

// Prepare and execute the query
$stmt = $conn->prepare($sql);

if (!empty($status_filter)) {
    $stmt->bind_param("is", $user_id, $status_filter);
} else {
    $stmt->bind_param("i", $user_id);
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

<!-- Modern Page Header -->
<section class="page-header-modern">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <div class="page-header-content">
                    <span class="page-badge">📅 My Bookings</span>
                    <h1 class="page-title">Pet Sitting <span class="text-gradient">Bookings</span></h1>
                    <p class="page-subtitle">Manage and track your pet sitting appointments with trusted sitters</p>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="page-actions">
                    <a href="../pet_sitters.php" class="btn btn-primary-gradient">
                        <i class="fas fa-plus me-2"></i> New Booking
                    </a>
                    <a href="dashboard.php" class="btn btn-outline-light">
                        <i class="fas fa-arrow-left me-2"></i> Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Modern Filters Section -->
<section class="filters-section-modern">
    <div class="container">
        <div class="filter-card-modern">
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="get" class="filter-form-modern">
                <div class="row g-3 align-items-end">
                    <div class="col-md-5">
                        <label class="filter-label-modern">Filter by Status</label>
                        <select class="form-select modern-select" name="status">
                            <option value="" <?php echo empty($status_filter) ? 'selected' : ''; ?>>All Bookings</option>
                            <option value="Pending" <?php echo ($status_filter === 'Pending') ? 'selected' : ''; ?>>Pending</option>
                            <option value="Confirmed" <?php echo ($status_filter === 'Confirmed') ? 'selected' : ''; ?>>Confirmed</option>
                            <option value="Completed" <?php echo ($status_filter === 'Completed') ? 'selected' : ''; ?>>Completed</option>
                            <option value="Cancelled" <?php echo ($status_filter === 'Cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    
                    <div class="col-md-5">
                        <label class="filter-label-modern">Sort By</label>
                        <select class="form-select modern-select" name="sort_by">
                            <option value="newest" <?php echo ($sort_by === 'newest') ? 'selected' : ''; ?>>Newest First</option>
                            <option value="oldest" <?php echo ($sort_by === 'oldest') ? 'selected' : ''; ?>>Oldest First</option>
                            <option value="upcoming" <?php echo ($sort_by === 'upcoming') ? 'selected' : ''; ?>>Upcoming Dates</option>
                            <option value="completed" <?php echo ($sort_by === 'completed') ? 'selected' : ''; ?>>Recently Completed</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary-gradient w-100">
                            <i class="fas fa-filter me-1"></i> Apply
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</section>

<!-- Modern Bookings Content -->
<section class="content-section-modern">
    <div class="container">
        <?php if (empty($bookings)): ?>
            <div class="empty-state-modern">
                <div class="empty-icon">
                    <i class="fas fa-calendar-times"></i>
                </div>
                <h4>No Bookings Found</h4>
                <p>You don't have any bookings<?php echo !empty($status_filter) ? " with status '$status_filter'" : ""; ?>.</p>
                <a href="../pet_sitters.php" class="btn btn-primary-gradient">
                    <i class="fas fa-search me-2"></i> Find Pet Sitters
                </a>
            </div>
        <?php else: ?>
            <div class="content-header">
                <h3>Your Bookings <span class="count-badge"><?php echo count($bookings); ?> total</span></h3>
            </div>
            
            <div class="bookings-grid">
                <?php foreach ($bookings as $booking): ?>
                    <div class="booking-card-modern">
                        <div class="booking-status-indicator status-<?php echo strtolower($booking['status']); ?>"></div>
                        
                        <div class="booking-header">
                            <div class="booking-info">
                                <h5 class="booking-title">Booking #<?php echo $booking['bookingID']; ?></h5>
                                <span class="booking-status-badge status-<?php echo strtolower($booking['status']); ?>">
                                    <?php echo $booking['status']; ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="booking-details">
                            <div class="detail-item">
                                <div class="detail-icon">
                                    <i class="fas fa-paw"></i>
                                </div>
                                <div class="detail-content">
                                    <span class="detail-label">Pet</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($booking['petName']); ?></span>
                                </div>
                            </div>
                            
                            <div class="detail-item">
                                <div class="detail-icon">
                                    <i class="fas fa-user-friends"></i>
                                </div>
                                <div class="detail-content">
                                    <span class="detail-label">Pet Sitter</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($booking['sitterName']); ?></span>
                                </div>
                            </div>
                            
                            <div class="detail-item">
                                <div class="detail-icon">
                                    <i class="fas fa-concierge-bell"></i>
                                </div>
                                <div class="detail-content">
                                    <span class="detail-label">Service</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($booking['service']); ?></span>
                                </div>
                            </div>
                            
                            <div class="detail-item">
                                <div class="detail-icon">
                                    <i class="fas fa-calendar-alt"></i>
                                </div>
                                <div class="detail-content">
                                    <span class="detail-label">Duration</span>
                                    <span class="detail-value">
                                        <?php echo date('M d, Y', strtotime($booking['checkInDate'])); ?> - 
                                        <?php echo date('M d, Y', strtotime($booking['checkOutDate'])); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="booking-actions">
                            <a href="booking_details.php?id=<?php echo $booking['bookingID']; ?>" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-eye me-1"></i> View Details
                            </a>
                            
                            <?php if ($booking['status'] === 'Pending' || $booking['status'] === 'Confirmed'): ?>
                                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to cancel this booking?');">
                                    <input type="hidden" name="booking_id" value="<?php echo $booking['bookingID']; ?>">
                                    <button type="submit" name="cancel_booking" class="btn btn-outline-danger btn-sm">
                                        <i class="fas fa-times me-1"></i> Cancel
                                    </button>
                                </form>
                            <?php endif; ?>
                            
                            <?php if ($booking['status'] === 'Completed'): ?>
                                <a href="add_review.php?sitter_id=<?php echo $booking['sitterID']; ?>&booking_id=<?php echo $booking['bookingID']; ?>" class="btn btn-primary-gradient btn-sm">
                                    <i class="fas fa-star me-1"></i> Review
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
.page-header-modern {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 80px 0;
    position: relative;
    overflow: hidden;
}

.page-header-modern::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="20" cy="20" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="80" cy="40" r="0.5" fill="rgba(255,255,255,0.1)"/><circle cx="40" cy="80" r="1.5" fill="rgba(255,255,255,0.1)"/></svg>');
    animation: float 20s infinite linear;
}

.page-header-content {
    position: relative;
    z-index: 2;
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

.page-subtitle {
    font-size: 1.2rem;
    opacity: 0.9;
    margin-bottom: 0;
}

.page-actions {
    text-align: right;
    position: relative;
    z-index: 2;
}

.page-actions .btn {
    margin-left: 12px;
}

.filters-section-modern {
    padding: 40px 0;
    background: #f8f9ff;
}

.filter-card-modern {
    background: white;
    border-radius: 20px;
    padding: 32px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(0, 0, 0, 0.05);
}

.filter-label-modern {
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
    background: white;
}

.modern-select:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.content-section-modern {
    padding: 80px 0;
    background: white;
}

.empty-state-modern {
    text-align: center;
    padding: 80px 40px;
    background: #f8f9ff;
    border-radius: 20px;
    border: 2px dashed #d1d5db;
    max-width: 500px;
    margin: 0 auto;
}

.empty-icon {
    font-size: 4rem;
    color: #9ca3af;
    margin-bottom: 24px;
}

.empty-state-modern h4 {
    color: #374151;
    margin-bottom: 16px;
    font-weight: 600;
}

.empty-state-modern p {
    color: #6b7280;
    margin-bottom: 32px;
}

.content-header {
    margin-bottom: 40px;
}

.content-header h3 {
    font-size: 2rem;
    font-weight: 700;
    color: #1e293b;
    margin: 0;
}

.count-badge {
    background: linear-gradient(135deg, #f1f5f9, #e2e8f0);
    color: #64748b;
    padding: 4px 12px;
    border-radius: 50px;
    font-size: 0.8rem;
    font-weight: 500;
    margin-left: 12px;
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
    transform: translateY(-4px);
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
}

.booking-status-indicator {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
}

.booking-status-indicator.status-pending { background: #f59e0b; }
.booking-status-indicator.status-confirmed { background: #10b981; }
.booking-status-indicator.status-completed { background: #3b82f6; }
.booking-status-indicator.status-cancelled { background: #ef4444; }

.booking-header {
    margin-bottom: 20px;
}

.booking-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.booking-title {
    font-size: 1.2rem;
    font-weight: 600;
    color: #1e293b;
    margin: 0;
}

.booking-status-badge {
    padding: 6px 12px;
    border-radius: 50px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.booking-status-badge.status-pending { background: #fef3c7; color: #92400e; }
.booking-status-badge.status-confirmed { background: #dcfce7; color: #166534; }
.booking-status-badge.status-completed { background: #dbeafe; color: #1e40af; }
.booking-status-badge.status-cancelled { background: #fee2e2; color: #991b1b; }

.booking-details {
    margin-bottom: 24px;
}

.detail-item {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 16px;
}

.detail-item:last-child {
    margin-bottom: 0;
}

.detail-icon {
    width: 36px;
    height: 36px;
    background: linear-gradient(135deg, #f1f5f9, #e2e8f0);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #667eea;
    flex-shrink: 0;
}

.detail-content {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.detail-label {
    font-size: 0.8rem;
    color: #9ca3af;
    font-weight: 500;
}

.detail-value {
    color: #374151;
    font-weight: 500;
}

.booking-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.booking-actions .btn {
    flex: 1;
    min-width: 120px;
}

@media (max-width: 768px) {
    .page-title {
        font-size: 2.5rem;
    }
    
    .page-actions {
        text-align: left;
        margin-top: 24px;
    }
    
    .page-actions .btn {
        margin-left: 0;
        margin-right: 12px;
        margin-bottom: 8px;
    }
    
    .bookings-grid {
        grid-template-columns: 1fr;
    }
    
    .filter-card-modern {
        padding: 24px;
    }
    
    .booking-actions {
        flex-direction: column;
    }
    
    .booking-actions .btn {
        min-width: auto;
    }
}
</style>

<?php
// Include footer
include_once '../includes/footer.php';

// Close database connection
$conn->close();
?>