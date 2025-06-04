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
    
    // Check if booking belongs to user and can be cancelled (only Pending or Confirmed status)
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

// Prepare SQL query - Include payment status check
$sql = "SELECT b.*, p.petName, ps.fullName as sitterName, ps.service, ps.price as hourlyRate,
        (SELECT COUNT(*) FROM payment WHERE bookingID = b.bookingID AND status = 'Completed') as is_paid
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
                            <option value="Pending" <?php echo ($status_filter === 'Pending') ? 'selected' : ''; ?>>Pending Request</option>
                            <option value="Confirmed" <?php echo ($status_filter === 'Confirmed') ? 'selected' : ''; ?>>Confirmed (Payment Due)</option>
                            <option value="Paid" <?php echo ($status_filter === 'Paid') ? 'selected' : ''; ?>>Paid & Ready</option>
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
                <?php foreach ($bookings as $booking): 
                    // Calculate booking cost
                    $check_in_datetime = new DateTime($booking['checkInDate'] . ' ' . $booking['checkInTime']);
                    $check_out_datetime = new DateTime($booking['checkOutDate'] . ' ' . $booking['checkOutTime']);
                    $interval = $check_in_datetime->diff($check_out_datetime);
                    $hours = $interval->h + ($interval->days * 24) + ($interval->i > 0 ? 1 : 0);
                    $total_cost = $hours * $booking['hourlyRate'];
                ?>
                    <div class="booking-card-modern">
                        <div class="booking-status-indicator status-<?php echo strtolower($booking['status']); ?>"></div>
                        
                        <div class="booking-header">
                            <div class="booking-info">
                                <h5 class="booking-title">Booking #<?php echo $booking['bookingID']; ?></h5>
                                <div class="status-badges">
                                    <span class="booking-status-badge status-<?php echo strtolower($booking['status']); ?>">
                                        <?php echo $booking['status']; ?>
                                    </span>
                                    <?php if ($booking['status'] === 'Confirmed' && $booking['is_paid'] == 0): ?>
                                        <span class="payment-badge payment-due">
                                            <i class="fas fa-exclamation-triangle me-1"></i>Payment Due
                                        </span>
                                    <?php elseif ($booking['is_paid'] > 0): ?>
                                        <span class="payment-badge payment-completed">
                                            <i class="fas fa-check me-1"></i>Paid
                                        </span>
                                    <?php endif; ?>
                                </div>
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

                            <div class="detail-item">
                                <div class="detail-icon">
                                    <i class="fas fa-dollar-sign"></i>
                                </div>
                                <div class="detail-content">
                                    <span class="detail-label">Total Cost</span>
                                    <span class="detail-value cost">$<?php echo number_format($total_cost, 2); ?></span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Status-based action buttons -->
                        <div class="booking-actions">
                            <a href="booking_details.php?id=<?php echo $booking['bookingID']; ?>" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-eye me-1"></i> View Details
                            </a>
                            
                            <?php if ($booking['status'] === 'Pending'): ?>
                                <div class="status-info pending-info">
                                    <i class="fas fa-clock me-1"></i> Waiting for sitter response
                                </div>
                                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to cancel this booking request?');">
                                    <input type="hidden" name="booking_id" value="<?php echo $booking['bookingID']; ?>">
                                    <button type="submit" name="cancel_booking" class="btn btn-outline-danger btn-sm">
                                        <i class="fas fa-times me-1"></i> Cancel Request
                                    </button>
                                </form>
                            
                            <?php elseif ($booking['status'] === 'Confirmed' && $booking['is_paid'] == 0): ?>
                                <div class="payment-due-section">
                                    <div class="status-info confirmed-info">
                                        <i class="fas fa-check-circle me-1"></i> Sitter accepted! Payment required
                                    </div>
                                    <a href="../payment.php?type=booking&booking_id=<?php echo $booking['bookingID']; ?>" class="btn btn-success btn-sm payment-btn">
                                        <i class="fas fa-credit-card me-1"></i> Pay Now ($<?php echo number_format($total_cost, 2); ?>)
                                    </a>
                                </div>
                                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to cancel this confirmed booking?');">
                                    <input type="hidden" name="booking_id" value="<?php echo $booking['bookingID']; ?>">
                                    <button type="submit" name="cancel_booking" class="btn btn-outline-danger btn-sm">
                                        <i class="fas fa-times me-1"></i> Cancel
                                    </button>
                                </form>
                            
                            <?php elseif ($booking['status'] === 'Paid'): ?>
                                <div class="status-info paid-info">
                                    <i class="fas fa-check-double me-1"></i> Paid & Ready - Service upcoming
                                </div>
                            
                            <?php elseif ($booking['status'] === 'Completed'): ?>
                                <div class="status-info completed-info">
                                    <i class="fas fa-star me-1"></i> Service completed
                                </div>
                                <a href="add_review.php?sitter_id=<?php echo $booking['sitterID']; ?>&booking_id=<?php echo $booking['bookingID']; ?>" class="btn btn-primary-gradient btn-sm">
                                    <i class="fas fa-star me-1"></i> Review Sitter
                                </a>
                            
                            <?php elseif ($booking['status'] === 'Cancelled'): ?>
                                <div class="status-info cancelled-info">
                                    <i class="fas fa-ban me-1"></i> Booking cancelled
                                </div>
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

.page-actions {
    text-align: right;
    position: relative;
    z-index: 2;
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

.content-section-modern {
    padding: 80px 0;
    background: white;
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
.booking-status-indicator.status-paid { background: #3b82f6; }
.booking-status-indicator.status-completed { background: #6366f1; }
.booking-status-indicator.status-cancelled { background: #ef4444; }

.status-badges {
    display: flex;
    gap: 8px;
    align-items: center;
    flex-wrap: wrap;
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
.booking-status-badge.status-paid { background: #dbeafe; color: #1e40af; }
.booking-status-badge.status-completed { background: #e0e7ff; color: #3730a3; }
.booking-status-badge.status-cancelled { background: #fee2e2; color: #991b1b; }

.payment-badge {
    padding: 4px 8px;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
}

.payment-badge.payment-due {
    background: #fef3c7;
    color: #92400e;
    animation: pulse-payment 2s infinite;
}

.payment-badge.payment-completed {
    background: #dcfce7;
    color: #166534;
}

@keyframes pulse-payment {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.7; }
}

.detail-value.cost {
    font-weight: 700;
    color: #10b981;
    font-size: 1.1rem;
}

.booking-actions {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-top: 20px;
}

.status-info {
    background: #f8fafc;
    color: #64748b;
    padding: 8px 12px;
    border-radius: 8px;
    font-size: 0.85rem;
    font-weight: 500;
    text-align: center;
}

.status-info.pending-info {
    background: #fef3c7;
    color: #92400e;
}

.status-info.confirmed-info {
    background: #dcfce7;
    color: #166534;
}

.status-info.paid-info {
    background: #dbeafe;
    color: #1e40af;
}

.status-info.completed-info {
    background: #e0e7ff;
    color: #3730a3;
}

.status-info.cancelled-info {
    background: #fee2e2;
    color: #991b1b;
}

.payment-due-section {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.payment-btn {
    animation: glow-payment 2s infinite;
    font-weight: 600;
}

@keyframes glow-payment {
    0%, 100% { box-shadow: 0 0 5px rgba(34, 197, 94, 0.5); }
    50% { box-shadow: 0 0 20px rgba(34, 197, 94, 0.8); }
}

.action-row {
    display: flex;
    gap: 8px;
}

.action-row .btn {
    flex: 1;
}

@media (max-width: 768px) {
    .page-title {
        font-size: 2.5rem;
    }
    
    .page-actions {
        text-align: left;
        margin-top: 24px;
    }
    
    .bookings-grid {
        grid-template-columns: 1fr;
    }
    
    .status-badges {
        justify-content: flex-start;
    }
}
</style>

<?php
// Include footer
include_once '../includes/footer.php';

// Close database connection
$conn->close();
?>