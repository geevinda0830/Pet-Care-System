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

// Check if booking ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "Invalid booking ID.";
    header("Location: bookings.php");
    exit();
}

// Include database connection
require_once '../config/db_connect.php';

$user_id = $_SESSION['user_id'];
$booking_id = $_GET['id'];

// Get booking details
$booking_sql = "SELECT b.*, 
                po.fullName as ownerName, 
                po.email as ownerEmail, 
                po.contact as ownerContact, 
                po.address as ownerAddress,
                p.petID, 
                p.petName, 
                p.type as petType, 
                p.breed as petBreed, 
                p.age as petAge, 
                p.sex as petSex, 
                p.color as petColor,
                p.image as petImage,
                (SELECT AVG(pr.rating) FROM pet_ranking pr WHERE pr.petID = p.petID) as petRating,
                (SELECT COUNT(pr.rankingID) FROM pet_ranking pr WHERE pr.petID = p.petID) as petRatingCount
                FROM booking b 
                JOIN pet_owner po ON b.userID = po.userID
                JOIN pet_profile p ON b.petID = p.petID
                WHERE b.bookingID = ? AND b.sitterID = ?";
$booking_stmt = $conn->prepare($booking_sql);
$booking_stmt->bind_param("ii", $booking_id, $user_id);
$booking_stmt->execute();
$booking_result = $booking_stmt->get_result();

if ($booking_result->num_rows === 0) {
    $_SESSION['error_message'] = "Booking not found or does not belong to you.";
    header("Location: bookings.php");
    exit();
}

$booking = $booking_result->fetch_assoc();
$booking_stmt->close();

// Get payment info
$payment_sql = "SELECT * FROM payment WHERE bookingID = ?";
$payment_stmt = $conn->prepare($payment_sql);
$payment_stmt->bind_param("i", $booking_id);
$payment_stmt->execute();
$payment_result = $payment_stmt->get_result();
$payment = $payment_result->num_rows > 0 ? $payment_result->fetch_assoc() : null;
$payment_stmt->close();

// Calculate duration and total cost
$check_in = new DateTime($booking['checkInDate'] . ' ' . $booking['checkInTime']);
$check_out = new DateTime($booking['checkOutDate'] . ' ' . $booking['checkOutTime']);
$interval = $check_in->diff($check_out);

// Get the pet sitter's hourly rate
$rate_sql = "SELECT price FROM pet_sitter WHERE userID = ?";
$rate_stmt = $conn->prepare($rate_sql);
$rate_stmt->bind_param("i", $user_id);
$rate_stmt->execute();
$rate_result = $rate_stmt->get_result();
$hourly_rate = $rate_result->fetch_assoc()['price'];
$rate_stmt->close();

// Calculate total hours and cost
$total_hours = $interval->days * 24 + $interval->h + ($interval->i / 60);
$total_cost = $total_hours * $hourly_rate;

// Process booking status update if submitted
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_status'])) {
    $new_status = $_POST['new_status'];
    
    // Update booking status
    $update_sql = "UPDATE booking SET status = ? WHERE bookingID = ? AND sitterID = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("sii", $new_status, $booking_id, $user_id);
    
    if ($update_stmt->execute()) {
        $_SESSION['success_message'] = "Booking status updated successfully.";
        // Refresh the page to show updated status
        header("Location: booking_details.php?id=" . $booking_id);
        exit();
    } else {
        $_SESSION['error_message'] = "Failed to update booking status: " . $conn->error;
    }
    
    $update_stmt->close();
}

// Include header
include_once '../includes/header.php';
?>

<!-- Modern Booking Details Header -->
<section class="booking-details-header-section">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <div class="booking-header-content">
                    <span class="section-badge">📅 Booking #<?php echo $booking_id; ?></span>
                    <h1 class="booking-title">Booking <span class="text-gradient">Details</span></h1>
                    <div class="booking-status-header">
                        <?php
                        $status_class = '';
                        $status_icon = '';
                        switch ($booking['status']) {
                            case 'Pending':
                                $status_class = 'pending';
                                $status_icon = 'fa-clock';
                                break;
                            case 'Confirmed':
                                $status_class = 'confirmed';
                                $status_icon = 'fa-check-circle';
                                break;
                            case 'Cancelled':
                                $status_class = 'cancelled';
                                $status_icon = 'fa-times-circle';
                                break;
                            case 'Completed':
                                $status_class = 'completed';
                                $status_icon = 'fa-check-double';
                                break;
                        }
                        ?>
                        <span class="status-badge-large <?php echo $status_class; ?>">
                            <i class="fas <?php echo $status_icon; ?> me-2"></i>
                            <?php echo $booking['status']; ?>
                        </span>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 text-lg-end">
                <a href="bookings.php" class="btn btn-outline-light btn-lg">
                    <i class="fas fa-arrow-left me-2"></i> Back to Bookings
                </a>
            </div>
        </div>
    </div>
</section>

<!-- Booking Information Cards -->
<section class="booking-info-section">
    <div class="container">
        <div class="row g-4">
            <!-- Pet Information Card -->
            <div class="col-lg-4">
                <div class="info-card pet-card">
                    <div class="card-header-modern">
                        <h5><i class="fas fa-paw me-2"></i> Pet Information</h5>
                    </div>
                    <div class="card-body-modern text-center">
                        <div class="pet-avatar-large">
                            <?php if (!empty($booking['petImage'])): ?>
                                <img src="../assets/images/pets/<?php echo htmlspecialchars($booking['petImage']); ?>" alt="<?php echo htmlspecialchars($booking['petName']); ?>">
                            <?php else: ?>
                                <div class="pet-placeholder">
                                    <i class="fas fa-paw"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <h4 class="pet-name"><?php echo htmlspecialchars($booking['petName']); ?></h4>
                        
                        <?php if ($booking['petRating']): ?>
                            <div class="pet-rating">
                                <?php
                                $pet_rating = round($booking['petRating'], 1);
                                $full_stars = floor($pet_rating);
                                $half_star = $pet_rating - $full_stars >= 0.5;
                                
                                for ($i = 1; $i <= 5; $i++) {
                                    if ($i <= $full_stars) {
                                        echo '<i class="fas fa-star"></i>';
                                    } elseif ($i == $full_stars + 1 && $half_star) {
                                        echo '<i class="fas fa-star-half-alt"></i>';
                                    } else {
                                        echo '<i class="far fa-star"></i>';
                                    }
                                }
                                ?>
                                <span class="rating-text"><?php echo $pet_rating; ?> (<?php echo $booking['petRatingCount']; ?> ratings)</span>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">No ratings yet</p>
                        <?php endif; ?>
                        
                        <div class="pet-details">
                            <div class="detail-row">
                                <span class="detail-label">Type:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($booking['petType']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Breed:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($booking['petBreed']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Age:</span>
                                <span class="detail-value"><?php echo $booking['petAge']; ?> years</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Sex:</span>
                                <span class="detail-value"><?php echo $booking['petSex']; ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Color:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($booking['petColor']); ?></span>
                            </div>
                        </div>
                        
                        <?php if ($booking['status'] === 'Completed'): ?>
                            <a href="add_pet_ranking.php?booking_id=<?php echo $booking_id; ?>" class="btn btn-warning w-100 mt-3">
                                <i class="fas fa-star me-2"></i> Rate This Pet
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Owner Information Card -->
            <div class="col-lg-4">
                <div class="info-card owner-card">
                    <div class="card-header-modern">
                        <h5><i class="fas fa-user me-2"></i> Pet Owner</h5>
                    </div>
                    <div class="card-body-modern">
                        <div class="owner-info">
                            <div class="owner-avatar">
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="owner-details">
                                <h6><?php echo htmlspecialchars($booking['ownerName']); ?></h6>
                                <p class="text-muted">Pet Owner</p>
                            </div>
                        </div>
                        
                        <div class="contact-details">
                            <div class="contact-item">
                                <i class="fas fa-envelope"></i>
                                <div>
                                    <span class="contact-label">Email</span>
                                    <span class="contact-value"><?php echo htmlspecialchars($booking['ownerEmail']); ?></span>
                                </div>
                            </div>
                            <div class="contact-item">
                                <i class="fas fa-phone"></i>
                                <div>
                                    <span class="contact-label">Phone</span>
                                    <span class="contact-value"><?php echo htmlspecialchars($booking['ownerContact']); ?></span>
                                </div>
                            </div>
                            <div class="contact-item">
                                <i class="fas fa-map-marker-alt"></i>
                                <div>
                                    <span class="contact-label">Address</span>
                                    <span class="contact-value"><?php echo nl2br(htmlspecialchars($booking['ownerAddress'])); ?></span>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($booking['status'] !== 'Cancelled'): ?>
                            <div class="contact-actions">
                                <a href="mailto:<?php echo $booking['ownerEmail']; ?>" class="btn btn-outline-primary">
                                    <i class="fas fa-envelope me-2"></i> Send Email
                                </a>
                                <a href="tel:<?php echo $booking['ownerContact']; ?>" class="btn btn-outline-success">
                                    <i class="fas fa-phone me-2"></i> Call Owner
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Booking Summary Card -->
            <div class="col-lg-4">
                <div class="info-card booking-summary-card">
                    <div class="card-header-modern">
                        <h5><i class="fas fa-calculator me-2"></i> Booking Summary</h5>
                    </div>
                    <div class="card-body-modern">
                        <div class="summary-item">
                            <span class="summary-label">Service Duration</span>
                            <span class="summary-value">
                                <?php
                                $duration = '';
                                if ($interval->days > 0) {
                                    $duration .= $interval->days . ' day' . ($interval->days > 1 ? 's' : '') . ' ';
                                }
                                
                                if ($interval->h > 0) {
                                    $duration .= $interval->h . ' hour' . ($interval->h > 1 ? 's' : '') . ' ';
                                }
                                
                                if (empty($duration) && $interval->i > 0) {
                                    $duration .= $interval->i . ' minute' . ($interval->i > 1 ? 's' : '') . ' ';
                                }
                                
                                echo trim($duration);
                                ?>
                            </span>
                        </div>
                        
                        <div class="summary-item">
                            <span class="summary-label">Your Hourly Rate</span>
                            <span class="summary-value">$<?php echo number_format($hourly_rate, 2); ?></span>
                        </div>
                        
                        <div class="summary-item">
                            <span class="summary-label">Total Hours</span>
                            <span class="summary-value"><?php echo number_format($total_hours, 1); ?> hours</span>
                        </div>
                        
                        <div class="summary-divider"></div>
                        
                        <div class="summary-item total">
                            <span class="summary-label">Total Value</span>
                            <span class="summary-value total-value">$<?php echo number_format($total_cost, 2); ?></span>
                        </div>
                        
                        <div class="payment-status">
                            <?php if ($payment): ?>
                                <div class="payment-info">
                                    <div class="payment-badge <?php echo strtolower($payment['status']); ?>">
                                        <i class="fas fa-credit-card me-1"></i>
                                        Payment <?php echo $payment['status']; ?>
                                    </div>
                                    <div class="payment-details">
                                        <small>Amount: $<?php echo number_format($payment['amount'], 2); ?></small><br>
                                        <small>Method: <?php echo ucfirst($payment['payment_method']); ?></small><br>
                                        <small>Date: <?php echo date('M d, Y', strtotime($payment['paymentDate'])); ?></small>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="payment-badge pending">
                                    <i class="fas fa-clock me-1"></i>
                                    Payment Pending
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Booking Timeline and Details -->
<section class="booking-timeline-section">
    <div class="container">
        <div class="row g-4">
            <!-- Timeline and Schedule -->
            <div class="col-lg-8">
                <div class="info-card timeline-card">
                    <div class="card-header-modern">
                        <h5><i class="fas fa-calendar-alt me-2"></i> Schedule & Timeline</h5>
                    </div>
                    <div class="card-body-modern">
                        <div class="schedule-grid">
                            <div class="schedule-item">
                                <div class="schedule-icon checkin">
                                    <i class="fas fa-play"></i>
                                </div>
                                <div class="schedule-content">
                                    <h6>Check-in</h6>
                                    <p class="schedule-date"><?php echo date('l, F d, Y', strtotime($booking['checkInDate'])); ?></p>
                                    <p class="schedule-time"><?php echo date('h:i A', strtotime($booking['checkInTime'])); ?></p>
                                </div>
                            </div>
                            
                            <div class="schedule-item">
                                <div class="schedule-icon checkout">
                                    <i class="fas fa-stop"></i>
                                </div>
                                <div class="schedule-content">
                                    <h6>Check-out</h6>
                                    <p class="schedule-date"><?php echo date('l, F d, Y', strtotime($booking['checkOutDate'])); ?></p>
                                    <p class="schedule-time"><?php echo date('h:i A', strtotime($booking['checkOutTime'])); ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="booking-timeline">
                            <div class="timeline-item">
                                <div class="timeline-marker booking-created">
                                    <i class="fas fa-plus"></i>
                                </div>
                                <div class="timeline-content">
                                    <h6>Booking Created</h6>
                                    <p><?php echo date('M d, Y \a\t h:i A', strtotime($booking['created_at'])); ?></p>
                                </div>
                            </div>
                            
                            <?php if ($booking['status'] !== 'Pending'): ?>
                                <div class="timeline-item">
                                    <div class="timeline-marker status-updated">
                                        <i class="fas fa-edit"></i>
                                    </div>
                                    <div class="timeline-content">
                                        <h6>Status Updated to <?php echo $booking['status']; ?></h6>
                                        <p>Updated by you</p>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($payment): ?>
                                <div class="timeline-item">
                                    <div class="timeline-marker payment-received">
                                        <i class="fas fa-dollar-sign"></i>
                                    </div>
                                    <div class="timeline-content">
                                        <h6>Payment Received</h6>
                                        <p>$<?php echo number_format($payment['amount'], 2); ?> via <?php echo ucfirst($payment['payment_method']); ?></p>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (!empty($booking['additionalInformation'])): ?>
                            <div class="additional-info">
                                <h6><i class="fas fa-info-circle me-2"></i> Special Instructions</h6>
                                <div class="info-content">
                                    <?php echo nl2br(htmlspecialchars($booking['additionalInformation'])); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Actions and Status Updates -->
            <div class="col-lg-4">
                <div class="info-card actions-card">
                    <div class="card-header-modern">
                        <h5><i class="fas fa-cogs me-2"></i> Actions</h5>
                    </div>
                    <div class="card-body-modern">
                        <?php if ($booking['status'] === 'Pending'): ?>
                            <div class="action-section pending-actions">
                                <h6>Booking Pending</h6>
                                <p class="text-muted mb-3">This booking request is waiting for your response.</p>
                                
                                <div class="action-buttons">
                                    <button type="button" class="btn btn-success w-100 mb-2" data-bs-toggle="modal" data-bs-target="#confirmModal">
                                        <i class="fas fa-check me-2"></i> Accept Booking
                                    </button>
                                    <button type="button" class="btn btn-danger w-100" data-bs-toggle="modal" data-bs-target="#declineModal">
                                        <i class="fas fa-times me-2"></i> Decline Booking
                                    </button>
                                </div>
                            </div>
                        <?php elseif ($booking['status'] === 'Confirmed' && strtotime($booking['checkOutDate']) <= time()): ?>
                            <div class="action-section complete-actions">
                                <h6>Ready to Complete</h6>
                                <p class="text-muted mb-3">This booking has passed its end date. Mark it as completed.</p>
                                
                                <button type="button" class="btn btn-info w-100" data-bs-toggle="modal" data-bs-target="#completeModal">
                                    <i class="fas fa-check-circle me-2"></i> Mark as Completed
                                </button>
                            </div>
                        <?php elseif ($booking['status'] === 'Confirmed'): ?>
                            <div class="action-section confirmed-actions">
                                <h6>Booking Confirmed</h6>
                                <p class="text-muted mb-3">Everything looks good! The booking is confirmed and scheduled.</p>
                                
                                <div class="countdown-timer">
                                    <?php
                                    $now = new DateTime();
                                    $checkIn = new DateTime($booking['checkInDate'] . ' ' . $booking['checkInTime']);
                                    $timeDiff = $now->diff($checkIn);
                                    
                                    if ($checkIn > $now): ?>
                                        <div class="countdown">
                                            <i class="fas fa-clock me-2"></i>
                                            Starts in <?php echo $timeDiff->days; ?> days, <?php echo $timeDiff->h; ?> hours
                                        </div>
                                    <?php else: ?>
                                        <div class="countdown active">
                                            <i class="fas fa-play me-2"></i>
                                            Service is active
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php elseif ($booking['status'] === 'Completed'): ?>
                            <div class="action-section completed-actions">
                                <h6>Booking Completed</h6>
                                <p class="text-muted mb-3">Great job! This booking has been successfully completed.</p>
                                
                                <a href="add_pet_ranking.php?booking_id=<?php echo $booking_id; ?>" class="btn btn-warning w-100">
                                    <i class="fas fa-star me-2"></i> Rate This Pet
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="action-section cancelled-actions">
                                <h6>Booking Cancelled</h6>
                                <p class="text-muted">This booking has been cancelled.</p>
                            </div>
                        <?php endif; ?>
                        
                        <div class="quick-actions">
                            <h6>Quick Actions</h6>
                            <div class="quick-action-grid">
                                <button type="button" class="quick-action-btn" onclick="window.print();">
                                    <i class="fas fa-print"></i>
                                    <span>Print</span>
                                </button>
                                <a href="mailto:<?php echo $booking['ownerEmail']; ?>" class="quick-action-btn">
                                    <i class="fas fa-envelope"></i>
                                    <span>Email</span>
                                </a>
                                <a href="tel:<?php echo $booking['ownerContact']; ?>" class="quick-action-btn">
                                    <i class="fas fa-phone"></i>
                                    <span>Call</span>
                                </a>
                                <a href="bookings.php" class="quick-action-btn">
                                    <i class="fas fa-list"></i>
                                    <span>All Bookings</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Status Update Modals -->
<?php if ($booking['status'] === 'Pending'): ?>
    <!-- Confirm Modal -->
    <div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content modern-modal">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Booking</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <i class="fas fa-calendar-check text-success mb-3" style="font-size: 4rem;"></i>
                    <h5>Accept this booking?</h5>
                    <p class="text-muted">You're about to confirm the booking for <strong><?php echo htmlspecialchars($booking['petName']); ?></strong>.</p>
                    <div class="booking-summary-modal">
                        <div class="summary-row">
                            <span>Date:</span>
                            <span><?php echo date('M d, Y', strtotime($booking['checkInDate'])); ?></span>
                        </div>
                        <div class="summary-row">
                            <span>Time:</span>
                            <span><?php echo date('h:i A', strtotime($booking['checkInTime'])); ?> - <?php echo date('h:i A', strtotime($booking['checkOutTime'])); ?></span>
                        </div>
                        <div class="summary-row">
                            <span>Estimated Value:</span>
                            <span class="fw-bold">$<?php echo number_format($total_cost, 2); ?></span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . '?id=' . $booking_id); ?>" method="post" class="d-inline">
                        <input type="hidden" name="new_status" value="Confirmed">
                        <button type="submit" name="update_status" class="btn btn-success">
                            <i class="fas fa-check me-2"></i> Confirm Booking
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Decline Modal -->
    <div class="modal fade" id="declineModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content modern-modal">
                <div class="modal-header">
                    <h5 class="modal-title">Decline Booking</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <i class="fas fa-times-circle text-danger mb-3" style="font-size: 4rem;"></i>
                    <h5>Decline this booking?</h5>
                    <p class="text-muted">This action cannot be undone. The pet owner will be notified about the decline.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . '?id=' . $booking_id); ?>" method="post" class="d-inline">
                        <input type="hidden" name="new_status" value="Cancelled">
                        <button type="submit" name="update_status" class="btn btn-danger">
                            <i class="fas fa-times me-2"></i> Decline Booking
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if ($booking['status'] === 'Confirmed' && strtotime($booking['checkOutDate']) <= time()): ?>
    <!-- Complete Modal -->
    <div class="modal fade" id="completeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content modern-modal">
                <div class="modal-header">
                    <h5 class="modal-title">Complete Booking</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <i class="fas fa-check-circle text-info mb-3" style="font-size: 4rem;"></i>
                    <h5>Mark as completed?</h5>
                    <p class="text-muted">This will finalize the booking and allow the pet owner to leave a review.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . '?id=' . $booking_id); ?>" method="post" class="d-inline">
                        <input type="hidden" name="new_status" value="Completed">
                        <button type="submit" name="update_status" class="btn btn-info">
                            <i class="fas fa-check-circle me-2"></i> Mark as Completed
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<style>
.booking-details-header-section {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 80px 0;
    position: relative;
    overflow: hidden;
}

.booking-details-header-section::before {
    content: '';
    position: absolute;
    width: 100%;
    height: 100%;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="20" cy="20" r="2" fill="rgba(255,255,255,0.1)"/><circle cx="80" cy="40" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="40" cy="80" r="1.5" fill="rgba(255,255,255,0.1)"/></svg>');
    animation: float 20s infinite linear;
}

.booking-title {
    font-size: 3.5rem;
    font-weight: 800;
    line-height: 1.2;
    margin-bottom: 24px;
}

.booking-status-header {
    margin-top: 16px;
}

.status-badge-large {
    padding: 12px 24px;
    border-radius: 25px;
    font-size: 1.1rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
}

.status-badge-large.pending { background: rgba(245, 158, 11, 0.2); color: #d97706; border: 2px solid #f59e0b; }
.status-badge-large.confirmed { background: rgba(16, 185, 129, 0.2); color: #059669; border: 2px solid #10b981; }
.status-badge-large.completed { background: rgba(59, 130, 246, 0.2); color: #1d4ed8; border: 2px solid #3b82f6; }
.status-badge-large.cancelled { background: rgba(239, 68, 68, 0.2); color: #dc2626; border: 2px solid #ef4444; }

.booking-info-section {
    padding: 0;
    margin-top: -40px;
    position: relative;
    z-index: 2;
}

.info-card {
    background: white;
    border-radius: 20px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    border: 1px solid rgba(0, 0, 0, 0.05);
    overflow: hidden;
    height: 100%;
}

.card-header-modern {
    background: #f8f9ff;
    padding: 20px 24px;
    border-bottom: 1px solid #f1f5f9;
}

.card-header-modern h5 {
    font-weight: 600;
    color: #1e293b;
    margin: 0;
}

.card-body-modern {
    padding: 24px;
}

.pet-avatar-large {
    width: 120px;
    height: 120px;
    border-radius: 20px;
    overflow: hidden;
    margin: 0 auto 20px;
    background: linear-gradient(135deg, #f1f5f9, #e2e8f0);
    display: flex;
    align-items: center;
    justify-content: center;
}

.pet-avatar-large img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.pet-placeholder {
    font-size: 3rem;
    color: #cbd5e1;
}

.pet-name {
    font-size: 1.8rem;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 16px;
}

.pet-rating {
    color: #fbbf24;
    font-size: 1.2rem;
    margin-bottom: 20px;
}

.rating-text {
    color: #64748b;
    font-size: 0.9rem;
    margin-left: 8px;
}

.pet-details {
    text-align: left;
    margin-top: 24px;
}

.detail-row {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px solid #f1f5f9;
}

.detail-row:last-child {
    border-bottom: none;
}

.detail-label {
    font-weight: 500;
    color: #64748b;
}

.detail-value {
    font-weight: 600;
    color: #1e293b;
}

.owner-info {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 24px;
}

.owner-avatar {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.5rem;
}

.owner-details h6 {
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 4px;
}

.contact-details {
    display: flex;
    flex-direction: column;
    gap: 16px;
    margin-bottom: 24px;
}

.contact-item {
    display: flex;
    align-items: flex-start;
    gap: 12px;
}

.contact-item i {
    width: 20px;
    color: #667eea;
    margin-top: 4px;
}

.contact-label {
    display: block;
    font-size: 0.8rem;
    color: #9ca3af;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.contact-value {
    display: block;
    font-weight: 500;
    color: #374151;
}

.contact-actions {
    display: flex;
    gap: 12px;
}

.contact-actions .btn {
    flex: 1;
    font-size: 0.9rem;
}

.summary-item {
    display: flex;
    justify-content: space-between;
    padding: 12px 0;
    border-bottom: 1px solid #f1f5f9;
}

.summary-item:last-child {
    border-bottom: none;
}

.summary-item.total {
    padding-top: 16px;
    margin-top: 8px;
    border-top: 2px solid #e5e7eb;
}

.summary-label {
    color: #64748b;
    font-weight: 500;
}

.summary-value {
    font-weight: 600;
    color: #1e293b;
}

.total-value {
    font-size: 1.3rem;
    color: #667eea !important;
}

.summary-divider {
    height: 1px;
    background: #e5e7eb;
    margin: 16px 0;
}

.payment-status {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #f1f5f9;
}

.payment-badge {
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 0.9rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    margin-bottom: 12px;
}

.payment-badge.completed { background: #dcfce7; color: #166534; }
.payment-badge.pending { background: #fef3c7; color: #92400e; }

.payment-details {
    color: #64748b;
}

.booking-timeline-section {
    padding: 60px 0 80px;
    background: #f8f9ff;
}

.schedule-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
    margin-bottom: 32px;
}

.schedule-item {
    display: flex;
    align-items: center;
    gap: 16px;
    background: #f8f9ff;
    padding: 20px;
    border-radius: 16px;
    border-left: 4px solid #667eea;
}

.schedule-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.2rem;
}

.schedule-icon.checkin { background: linear-gradient(135deg, #10b981, #059669); }
.schedule-icon.checkout { background: linear-gradient(135deg, #ef4444, #dc2626); }

.schedule-content h6 {
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 4px;
}

.schedule-date {
    font-weight: 500;
    color: #374151;
    margin-bottom: 4px;
}

.schedule-time {
    color: #667eea;
    font-weight: 600;
    font-size: 1.1rem;
    margin: 0;
}

.booking-timeline {
    margin-bottom: 32px;
}

.timeline-item {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 16px;
}

.timeline-marker {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 0.9rem;
    flex-shrink: 0;
}

.timeline-marker.booking-created { background: linear-gradient(135deg, #667eea, #764ba2); }
.timeline-marker.status-updated { background: linear-gradient(135deg, #f59e0b, #d97706); }
.timeline-marker.payment-received { background: linear-gradient(135deg, #10b981, #059669); }

.timeline-content h6 {
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 4px;
}

.timeline-content p {
    color: #64748b;
    font-size: 0.9rem;
    margin: 0;
}

.additional-info {
    background: #fef3c7;
    border-radius: 16px;
    padding: 20px;
    border-left: 4px solid #f59e0b;
}

.additional-info h6 {
    color: #92400e;
    font-weight: 600;
    margin-bottom: 12px;
}

.info-content {
    color: #92400e;
    line-height: 1.6;
}

.action-section {
    margin-bottom: 32px;
    padding-bottom: 24px;
    border-bottom: 1px solid #f1f5f9;
}

.action-section:last-child {
    border-bottom: none;
    margin-bottom: 0;
}

.action-section h6 {
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 8px;
}

.action-buttons {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.countdown-timer {
    background: #f0f9ff;
    border-radius: 12px;
    padding: 16px;
    text-align: center;
    border-left: 4px solid #3b82f6;
}

.countdown {
    color: #1e40af;
    font-weight: 600;
}

.countdown.active {
    color: #059669;
    background: #dcfce7;
    padding: 8px 16px;
    border-radius: 8px;
    border-left: 4px solid #10b981;
}

.quick-actions h6 {
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 16px;
}

.quick-action-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}

.quick-action-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    padding: 16px 12px;
    background: #f8f9ff;
    border: 2px solid #f1f5f9;
    border-radius: 12px;
    color: #64748b;
    text-decoration: none;
    transition: all 0.3s ease;
    cursor: pointer;
}

.quick-action-btn:hover {
    color: #667eea;
    border-color: #667eea;
    background: white;
    transform: translateY(-2px);
}

.quick-action-btn i {
    font-size: 1.2rem;
}

.quick-action-btn span {
    font-size: 0.8rem;
    font-weight: 500;
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

.booking-summary-modal {
    background: #f8f9ff;
    border-radius: 12px;
    padding: 20px;
    margin-top: 20px;
}

.summary-row {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px solid #e5e7eb;
}

.summary-row:last-child {
    border-bottom: none;
    padding-bottom: 0;
}

@media (max-width: 991px) {
    .booking-title {
        font-size: 2.5rem;
    }
    
    .schedule-grid {
        grid-template-columns: 1fr;
    }
    
    .quick-action-grid {
        grid-template-columns: 1fr;
    }
    
    .contact-actions {
        flex-direction: column;
    }
}

@media (max-width: 768px) {
    .booking-details-header-section {
        text-align: center;
    }
    
    .booking-info-section {
        margin-top: -20px;
    }
    
    .action-buttons {
        gap: 8px;
    }
    
    .card-body-modern {
        padding: 20px;
    }
    
    .timeline-item {
        flex-direction: column;
        text-align: center;
        gap: 8px;
    }
}
</style>

<?php
// Include footer
include_once '../includes/footer.php';

// Close database connection
$conn->close();
?>