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

// Prepare SQL query
$sql = "SELECT b.*, po.fullName as ownerName, po.contact as ownerContact, po.email as ownerEmail, p.petName, p.type as petType, p.breed as petBreed, p.age as petAge, p.image as petImage
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
        $sql .= " ORDER BY b.created_at ASC";
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
        $sql .= " ORDER BY b.created_at DESC";
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

<!-- Quick Stats -->
<section class="bookings-stats-section">
    <div class="container">
        <div class="stats-grid">
            <?php
            $pending_count = count(array_filter($bookings, function($b) { return $b['status'] === 'Pending'; }));
            $confirmed_count = count(array_filter($bookings, function($b) { return $b['status'] === 'Confirmed'; }));
            $completed_count = count(array_filter($bookings, function($b) { return $b['status'] === 'Completed'; }));
            $total_earnings = 0; // You can calculate this based on your business logic
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
                <div class="stat-icon earnings">
                    <i class="fas fa-dollar-sign"></i>
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
                <div class="view-toggles">
                    <button class="view-toggle active" data-view="cards">
                        <i class="fas fa-th-large"></i>
                    </button>
                    <button class="view-toggle" data-view="list">
                        <i class="fas fa-list"></i>
                    </button>
                </div>
            </div>
            
            <!-- Cards View -->
            <div class="bookings-grid" id="cards-view">
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
                                    <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#confirmModal<?php echo $booking['bookingID']; ?>">
                                        <i class="fas fa-check me-1"></i> Accept
                                    </button>
                                    <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#declineModal<?php echo $booking['bookingID']; ?>">
                                        <i class="fas fa-times me-1"></i> Decline
                                    </button>
                                </div>
                            <?php elseif ($booking['status'] === 'Confirmed' && strtotime($booking['checkOutDate']) <= time()): ?>
                                <button type="button" class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#completeModal<?php echo $booking['bookingID']; ?>">
                                    <i class="fas fa-check-circle me-1"></i> Mark Complete
                                </button>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Modals for this booking -->
                        <?php include 'booking_modals.php'; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- List View -->
            <div class="bookings-list" id="list-view" style="display: none;">
                <div class="table-responsive">
                    <table class="table booking-table">
                        <thead>
                            <tr>
                                <th>Pet & Owner</th>
                                <th>Dates & Time</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bookings as $booking): ?>
                                <tr>
                                    <td>
                                        <div class="table-pet-info">
                                            <div class="pet-avatar-small">
                                                <?php if (!empty($booking['petImage'])): ?>
                                                    <img src="../assets/images/pets/<?php echo htmlspecialchars($booking['petImage']); ?>" alt="<?php echo htmlspecialchars($booking['petName']); ?>">
                                                <?php else: ?>
                                                    <div class="pet-initial-small"><?php echo strtoupper(substr($booking['petName'], 0, 1)); ?></div>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <strong><?php echo htmlspecialchars($booking['petName']); ?></strong>
                                                <br><small><?php echo htmlspecialchars($booking['ownerName']); ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="table-date-info">
                                            <div><strong><?php echo date('M d, Y', strtotime($booking['checkInDate'])); ?></strong></div>
                                            <small><?php echo date('h:i A', strtotime($booking['checkInTime'])); ?> - <?php echo date('h:i A', strtotime($booking['checkOutTime'])); ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo strtolower($booking['status']); ?>">
                                            <?php echo $booking['status']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="table-actions">
                                            <a href="booking_details.php?id=<?php echo $booking['bookingID']; ?>" class="btn btn-outline-primary btn-sm">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if ($booking['status'] === 'Pending'): ?>
                                                <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#confirmModal<?php echo $booking['bookingID']; ?>">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#declineModal<?php echo $booking['bookingID']; ?>">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Status Guide -->
<section class="status-guide-section">
    <div class="container">
        <div class="guide-card">
            <h4><i class="fas fa-info-circle me-2"></i> Booking Status Guide</h4>
            <div class="status-grid">
                <div class="status-item">
                    <div class="status-icon pending">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="status-content">
                        <h6>Pending</h6>
                        <p>New booking requests awaiting your response. Accept or decline promptly.</p>
                    </div>
                </div>
                
                <div class="status-item">
                    <div class="status-icon confirmed">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="status-content">
                        <h6>Confirmed</h6>
                        <p>Bookings you've accepted and scheduled. Prepare for the service date.</p>
                    </div>
                </div>
                
                <div class="status-item">
                    <div class="status-icon completed">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="status-content">
                        <h6>Completed</h6>
                        <p>Successfully finished jobs. Request reviews from satisfied clients.</p>
                    </div>
                </div>
                
                <div class="status-item">
                    <div class="status-icon cancelled">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="status-content">
                        <h6>Cancelled</h6>
                        <p>Declined bookings or cancellations. Communicate professionally.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Include booking modals for all bookings -->
<?php foreach ($bookings as $booking): ?>
    <!-- Confirm Modal -->
    <div class="modal fade" id="confirmModal<?php echo $booking['bookingID']; ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content modern-modal">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Booking</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <i class="fas fa-calendar-check text-success mb-3" style="font-size: 3rem;"></i>
                    <h6>Accept this booking request?</h6>
                    <p class="text-muted">Booking for <strong><?php echo htmlspecialchars($booking['petName']); ?></strong> by <?php echo htmlspecialchars($booking['ownerName']); ?></p>
                    <div class="booking-summary">
                        <div class="summary-item">
                            <strong>Date:</strong> <?php echo date('M d, Y', strtotime($booking['checkInDate'])); ?>
                        </div>
                        <div class="summary-item">
                            <strong>Time:</strong> <?php echo date('h:i A', strtotime($booking['checkInTime'])); ?> - <?php echo date('h:i A', strtotime($booking['checkOutTime'])); ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="d-inline">
                        <input type="hidden" name="booking_id" value="<?php echo $booking['bookingID']; ?>">
                        <input type="hidden" name="new_status" value="Confirmed">
                        <button type="submit" name="update_status" class="btn btn-success">Accept Booking</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Decline Modal -->
    <div class="modal fade" id="declineModal<?php echo $booking['bookingID']; ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content modern-modal">
                <div class="modal-header">
                    <h5 class="modal-title">Decline Booking</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <i class="fas fa-times-circle text-danger mb-3" style="font-size: 3rem;"></i>
                    <h6>Decline this booking request?</h6>
                    <p class="text-muted">This action cannot be undone. The pet owner will be notified.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="d-inline">
                        <input type="hidden" name="booking_id" value="<?php echo $booking['bookingID']; ?>">
                        <input type="hidden" name="new_status" value="Cancelled">
                        <button type="submit" name="update_status" class="btn btn-danger">Decline Booking</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Complete Modal -->
    <?php if ($booking['status'] === 'Confirmed' && strtotime($booking['checkOutDate']) <= time()): ?>
    <div class="modal fade" id="completeModal<?php echo $booking['bookingID']; ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content modern-modal">
                <div class="modal-header">
                    <h5 class="modal-title">Complete Booking</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <i class="fas fa-check-circle text-info mb-3" style="font-size: 3rem;"></i>
                    <h6>Mark this booking as completed?</h6>
                    <p class="text-muted">This will notify the pet owner and allow them to leave a review.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="d-inline">
                        <input type="hidden" name="booking_id" value="<?php echo $booking['bookingID']; ?>">
                        <input type="hidden" name="new_status" value="Completed">
                        <button type="submit" name="update_status" class="btn btn-info">Mark as Completed</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
<?php endforeach; ?>

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
.stat-icon.earnings { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }

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
    display: flex;
    justify-content: space-between;
    align-items: center;
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

.view-toggles {
    display: flex;
    gap: 8px;
}

.view-toggle {
    background: #f1f5f9;
    border: none;
    padding: 8px 12px;
    border-radius: 8px;
    color: #64748b;
    cursor: pointer;
    transition: all 0.3s ease;
}

.view-toggle.active {
    background: #667eea;
    color: white;
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

.booking-table {
    background: white;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
}

.booking-table th {
    background: #f8f9ff;
    color: #374151;
    font-weight: 600;
    border: none;
    padding: 16px;
}

.booking-table td {
    padding: 16px;
    border: none;
    border-bottom: 1px solid #f1f5f9;
}

.table-pet-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.pet-avatar-small {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    overflow: hidden;
    background: linear-gradient(135deg, #667eea, #764ba2);
    display: flex;
    align-items: center;
    justify-content: center;
}

.pet-avatar-small img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.pet-initial-small {
    color: white;
    font-weight: 600;
    font-size: 1rem;
}

.table-actions {
    display: flex;
    gap: 8px;
}

.status-guide-section {
    padding: 80px 0;
    background: white;
}

.guide-card {
    background: #f8f9ff;
    border-radius: 24px;
    padding: 48px;
    border: 1px solid rgba(0, 0, 0, 0.05);
}

.guide-card h4 {
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 32px;
    text-align: center;
}

.status-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 24px;
}

.status-item {
    display: flex;
    align-items: flex-start;
    gap: 16px;
    background: white;
    padding: 20px;
    border-radius: 16px;
}

.status-item .status-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    color: white;
    flex-shrink: 0;
}

.status-content h6 {
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 4px;
}

.status-content p {
    color: #64748b;
    font-size: 0.9rem;
    margin: 0;
    line-height: 1.4;
}

.modern-modal .modal-content {
    border-radius: 20px;
    border: none;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
}

.modern-modal .modal-header {
    border-bottom: 1px solid #f1f5f9;
    padding: 24px;
}

.modern-modal .modal-body {
    padding: 24px;
}

.modern-modal .modal-footer {
    border-top: 1px solid #f1f5f9;
    padding: 24px;
}

.booking-summary {
    background: #f8f9ff;
    padding: 16px;
    border-radius: 12px;
    margin-top: 16px;
}

.summary-item {
    padding: 4px 0;
    color: #64748b;
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
    
    .bookings-header-bar {
        flex-direction: column;
        gap: 16px;
        align-items: flex-start;
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
    
    .guide-card {
        padding: 32px 20px;
    }
    
    .status-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // View toggle functionality
    const viewToggles = document.querySelectorAll('.view-toggle');
    const cardsView = document.getElementById('cards-view');
    const listView = document.getElementById('list-view');
    
    viewToggles.forEach(toggle => {
        toggle.addEventListener('click', function() {
            const view = this.getAttribute('data-view');
            
            // Update active toggle
            viewToggles.forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            
            // Show/hide views
            if (view === 'cards') {
                cardsView.style.display = 'grid';
                listView.style.display = 'none';
            } else {
                cardsView.style.display = 'none';
                listView.style.display = 'block';
            }
        });
    });
});
</script>

<?php
// Include footer
include_once '../includes/footer.php';

// Close database connection
$conn->close();
?>