<?php
// FILE PATH: /pet_sitter/booking_details.php

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
    
    // If accepting a booking, check for conflicts first
    if ($new_status === 'Confirmed') {
        $availability = checkSitterAvailability(
            $conn, 
            $user_id, 
            $booking['checkInDate'], 
            $booking['checkInTime'], 
            $booking['checkOutDate'], 
            $booking['checkOutTime'],
            $booking_id  // Exclude current booking from conflict check
        );
        
        if (!$availability['available']) {
            $_SESSION['error_message'] = "Cannot accept booking - you have conflicting bookings during this time period. Please check your schedule and contact the pet owner to reschedule.";
            header("Location: booking_details.php?id=" . $booking_id);
            exit();
        }
    }
    
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
        $_SESSION['error_message'] = "Failed to update booking status: " . $update_stmt->error;
    }
    
    $update_stmt->close();
}

// Include header
include_once '../includes/header.php';
?>

<style>
/* Modern Booking Details Styles */
.page-header-modern {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 60px 0;
    margin-bottom: 0;
}

.page-badge {
    background: rgba(255, 255, 255, 0.2);
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 600;
    display: inline-block;
    margin-bottom: 15px;
}

.page-title {
    font-size: 2.5rem;
    font-weight: 700;
    margin-bottom: 12px;
}

.text-gradient {
    background: linear-gradient(45deg, #ffd700, #ffed4e);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.page-subtitle {
    font-size: 1.1rem;
    opacity: 0.9;
    margin: 0;
}

.booking-details-section {
    padding: 60px 0;
    background: #f8fafc;
}

.info-card {
    background: white;
    border-radius: 20px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
    overflow: hidden;
    margin-bottom: 30px;
}

.card-header {
    background: linear-gradient(135deg, #f8f9ff 0%, #f1f5f9 100%);
    padding: 25px 30px;
    border-bottom: 1px solid #e5e7eb;
}

.card-header h5 {
    margin: 0;
    font-weight: 600;
    color: #1e293b;
    font-size: 1.2rem;
}

.info-content {
    padding: 30px;
}

.status-badge {
    padding: 8px 16px;
    border-radius: 25px;
    font-size: 0.9rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-pending { background: #fef3c7; color: #92400e; }
.status-confirmed { background: #d1fae5; color: #065f46; }
.status-completed { background: #dbeafe; color: #1e40af; }
.status-cancelled { background: #fee2e2; color: #991b1b; }

.schedule-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 25px;
    margin-bottom: 30px;
}

.schedule-item {
    background: #f8fafc;
    padding: 25px;
    border-radius: 16px;
    border-left: 4px solid #667eea;
    display: flex;
    align-items: center;
    gap: 20px;
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

.pet-info {
    display: flex;
    align-items: center;
    gap: 20px;
}

.pet-avatar {
    width: 80px;
    height: 80px;
    border-radius: 16px;
    object-fit: cover;
    border: 3px solid #f1f5f9;
}

.pet-details h4 {
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 8px;
}

.pet-details p {
    margin-bottom: 4px;
    color: #64748b;
}

.pet-rating {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 8px;
}

.rating-stars {
    color: #fbbf24;
}

.owner-contact {
    background: #f8fafc;
    padding: 20px;
    border-radius: 12px;
    margin-top: 20px;
}

.contact-item {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 10px;
}

.contact-item:last-child {
    margin-bottom: 0;
}

.contact-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 0.85rem;
}

.contact-icon.email { background: linear-gradient(135deg, #3b82f6, #1d4ed8); }
.contact-icon.phone { background: linear-gradient(135deg, #10b981, #059669); }
.contact-icon.address { background: linear-gradient(135deg, #ef4444, #dc2626); }

.cost-breakdown {
    background: linear-gradient(135deg, #f8f9ff 0%, #f1f5f9 100%);
    padding: 25px;
    border-radius: 16px;
    border: 1px solid #e5e7eb;
}

.cost-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid #e5e7eb;
}

.cost-item:last-child {
    border-bottom: none;
    font-weight: 600;
    font-size: 1.1rem;
    color: #1e293b;
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

.info-content-text {
    color: #92400e;
    line-height: 1.6;
}

.btn-primary-gradient {
    background: linear-gradient(135deg, #667eea, #764ba2);
    border: none;
    color: white;
    border-radius: 10px;
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

.btn-outline-primary {
    border: 2px solid #667eea;
    color: #667eea;
    background: transparent;
    border-radius: 10px;
    padding: 10px 22px;
    font-weight: 600;
}

.btn-outline-primary:hover {
    background: #667eea;
    color: white;
}

.btn-outline-success {
    border: 2px solid #10b981;
    color: #10b981;
    background: transparent;
    border-radius: 10px;
    padding: 10px 22px;
    font-weight: 600;
}

.btn-outline-success:hover {
    background: #10b981;
    color: white;
}

.btn-outline-info {
    border: 2px solid #3b82f6;
    color: #3b82f6;
    background: transparent;
    border-radius: 10px;
    padding: 10px 22px;
    font-weight: 600;
}

.btn-outline-info:hover {
    background: #3b82f6;
    color: white;
}
</style>

<!-- Page Header -->
<div class="page-header-modern">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <div class="page-badge">
                    <i class="fas fa-calendar-check me-2"></i>Booking Details
                </div>
                <h1 class="page-title">Booking #<span class="text-gradient"><?php echo $booking_id; ?></span></h1>
                <p class="page-subtitle mb-0">Manage this pet sitting appointment</p>
            </div>
        </div>
    </div>
</div>

<div class="booking-details-section">
    <div class="container">
        <!-- Success/Error Messages -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-8">
                <!-- Booking Status -->
                <div class="info-card">
                    <div class="card-header">
                        <h5><i class="fas fa-info-circle me-2"></i> Booking Status & Actions</h5>
                    </div>
                    <div class="info-content">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="status-badge status-<?php echo strtolower($booking['status']); ?>">
                                <?php echo $booking['status']; ?>
                            </span>
                            <small class="text-muted">
                                Booking ID: #<?php echo $booking_id; ?>
                            </small>
                        </div>

                        <!-- Status Update Form -->
                        <?php if ($booking['status'] == 'Pending' || $booking['status'] == 'Confirmed'): ?>
                            <div class="mt-4">
                                <h6><i class="fas fa-edit me-2"></i>Update Booking Status:</h6>
                                <form method="POST" class="d-flex gap-2 align-items-end">
                                    <div class="flex-grow-1">
                                        <select name="new_status" class="form-select" required>
                                            <option value="">Select new status...</option>
                                            <?php if ($booking['status'] == 'Pending'): ?>
                                                <option value="Confirmed">✅ Accept Booking</option>
                                                <option value="Cancelled">❌ Decline Booking</option>
                                            <?php elseif ($booking['status'] == 'Confirmed'): ?>
                                                <option value="Completed">🏁 Mark as Completed</option>
                                                <option value="Cancelled">❌ Cancel Booking</option>
                                            <?php endif; ?>
                                        </select>
                                    </div>
                                    <button type="submit" name="update_status" class="btn btn-primary-gradient">
                                        <i class="fas fa-save me-2"></i> Update
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Schedule Information -->
                <div class="info-card">
                    <div class="card-header">
                        <h5><i class="fas fa-clock me-2"></i> Schedule Information</h5>
                    </div>
                    <div class="info-content">
                        <div class="schedule-grid">
                            <div class="schedule-item">
                                <div class="schedule-icon checkin">
                                    <i class="fas fa-play"></i>
                                </div>
                                <div class="schedule-content">
                                    <h6>Check-in</h6>
                                    <div class="schedule-date"><?php echo date('l, F d, Y', strtotime($booking['checkInDate'])); ?></div>
                                    <p class="schedule-time"><?php echo date('g:i A', strtotime($booking['checkInTime'])); ?></p>
                                </div>
                            </div>
                            
                            <div class="schedule-item">
                                <div class="schedule-icon checkout">
                                    <i class="fas fa-stop"></i>
                                </div>
                                <div class="schedule-content">
                                    <h6>Check-out</h6>
                                    <div class="schedule-date"><?php echo date('l, F d, Y', strtotime($booking['checkOutDate'])); ?></div>
                                    <p class="schedule-time"><?php echo date('g:i A', strtotime($booking['checkOutTime'])); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Booking Timeline -->
                <div class="info-card">
                    <div class="card-header">
                        <h5><i class="fas fa-history me-2"></i> Booking Timeline</h5>
                    </div>
                    <div class="info-content">
                        <div class="booking-timeline">
                            <div class="timeline-item">
                                <div class="timeline-marker booking-created">
                                    <i class="fas fa-calendar-plus"></i>
                                </div>
                                <div class="timeline-content">
                                    <h6>Booking Created</h6>
                                    <p>Booking request submitted by <?php echo htmlspecialchars($booking['ownerName']); ?></p>
                                </div>
                            </div>
                            
                            <?php if ($booking['status'] != 'Pending'): ?>
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
                                        <p>Rs. <?php echo number_format($payment['amount'], 2); ?> received via <?php echo htmlspecialchars($payment['paymentMethod']); ?></p>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Pet Information -->
                <div class="info-card">
                    <div class="card-header">
                        <h5><i class="fas fa-paw me-2"></i> Pet Information</h5>
                    </div>
                    <div class="info-content">
                        <div class="pet-info">
                            <div class="pet-avatar">
                                <?php if (!empty($booking['petImage']) && file_exists('../assets/images/pets/' . $booking['petImage'])): ?>
                                    <img src="../assets/images/pets/<?php echo htmlspecialchars($booking['petImage']); ?>" 
                                         alt="<?php echo htmlspecialchars($booking['petName']); ?>" 
                                         class="pet-avatar">
                                <?php else: ?>
                                    <div class="pet-avatar d-flex align-items-center justify-content-center bg-light">
                                        <i class="fas fa-paw text-muted fa-2x"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="pet-details">
                                <h4><?php echo htmlspecialchars($booking['petName']); ?></h4>
                                <p><strong>Type:</strong> <?php echo htmlspecialchars($booking['petType']); ?></p>
                                <p><strong>Breed:</strong> <?php echo htmlspecialchars($booking['petBreed']); ?></p>
                                <p><strong>Age:</strong> <?php echo htmlspecialchars($booking['petAge']); ?> years old</p>
                                <p><strong>Gender:</strong> <?php echo htmlspecialchars($booking['petSex']); ?></p>
                                <p><strong>Color:</strong> <?php echo htmlspecialchars($booking['petColor']); ?></p>
                                
                                <?php if ($booking['petRating'] && $booking['petRatingCount'] > 0): ?>
                                    <div class="pet-rating">
                                        <span class="rating-stars">
                                            <?php 
                                            $rating = round($booking['petRating']);
                                            for ($i = 1; $i <= 5; $i++) {
                                                echo $i <= $rating ? '★' : '☆';
                                            }
                                            ?>
                                        </span>
                                        <span><?php echo number_format($booking['petRating'], 1); ?> (<?php echo $booking['petRatingCount']; ?> reviews)</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if (!empty($booking['additionalInformation'])): ?>
                            <div class="additional-info mt-4">
                                <h6><i class="fas fa-sticky-note me-2"></i>Special Instructions from Owner:</h6>
                                <div class="info-content-text">
                                    <?php echo nl2br(htmlspecialchars($booking['additionalInformation'])); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Pet Owner Information -->
                <div class="info-card">
                    <div class="card-header">
                        <h5><i class="fas fa-user me-2"></i> Pet Owner Information</h5>
                    </div>
                    <div class="info-content">
                        <h6><?php echo htmlspecialchars($booking['ownerName']); ?></h6>
                        
                        <div class="owner-contact">
                            <div class="contact-item">
                                <div class="contact-icon email">
                                    <i class="fas fa-envelope"></i>
                                </div>
                                <div>
                                    <strong>Email:</strong><br>
                                    <a href="mailto:<?php echo htmlspecialchars($booking['ownerEmail']); ?>" class="text-primary">
                                        <?php echo htmlspecialchars($booking['ownerEmail']); ?>
                                    </a>
                                </div>
                            </div>
                            
                            <div class="contact-item">
                                <div class="contact-icon phone">
                                    <i class="fas fa-phone"></i>
                                </div>
                                <div>
                                    <strong>Phone:</strong><br>
                                    <a href="tel:<?php echo htmlspecialchars($booking['ownerContact']); ?>" class="text-success">
                                        <?php echo htmlspecialchars($booking['ownerContact']); ?>
                                    </a>
                                </div>
                            </div>
                            
                            <div class="contact-item">
                                <div class="contact-icon address">
                                    <i class="fas fa-map-marker-alt"></i>
                                </div>
                                <div>
                                    <strong>Address:</strong><br>
                                    <span class="text-muted"><?php echo htmlspecialchars($booking['ownerAddress']); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <!-- Cost Breakdown -->
                <div class="info-card">
                    <div class="card-header">
                        <h5><i class="fas fa-calculator me-2"></i> Cost Breakdown</h5>
                    </div>
                    <div class="info-content">
                        <div class="cost-breakdown">
                            <div class="cost-item">
                                <span>Hourly Rate:</span>
                                <span>Rs. <?php echo number_format($hourly_rate, 2); ?></span>
                            </div>
                            <div class="cost-item">
                                <span>Total Hours:</span>
                                <span><?php echo number_format($total_hours, 1); ?> hours</span>
                            </div>
                            <div class="cost-item">
                                <span>Total Cost:</span>
                                <span>Rs. <?php echo number_format($total_cost, 2); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Payment Information -->
                <?php if ($payment): ?>
                    <div class="info-card">
                        <div class="card-header">
                            <h5><i class="fas fa-credit-card me-2"></i> Payment Information</h5>
                        </div>
                        <div class="info-content">
                            <div class="cost-breakdown">
                                <div class="cost-item">
                                    <span>Amount:</span>
                                    <span>Rs. <?php echo number_format($payment['amount'], 2); ?></span>
                                </div>
                                <div class="cost-item">
                                    <span>Method:</span>
                                    <span><?php echo htmlspecialchars($payment['paymentMethod']); ?></span>
                                </div>
                                <div class="cost-item">
                                    <span>Date:</span>
                                    <span><?php echo date('M d, Y g:i A', strtotime($payment['paymentDate'])); ?></span>
                                </div>
                                <div class="cost-item">
                                    <span>Status:</span>
                                    <span class="badge bg-success">✅ Paid</span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="info-card">
                        <div class="card-header">
                            <h5><i class="fas fa-credit-card me-2"></i> Payment Status</h5>
                        </div>
                        <div class="info-content">
                            <div class="text-center">
                                <i class="fas fa-clock fa-2x text-warning mb-3"></i>
                                <h6>Payment Pending</h6>
                                <p class="text-muted">Payment will be processed after you accept the booking.</p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Quick Actions -->
                <div class="info-card">
                    <div class="card-header">
                        <h5><i class="fas fa-bolt me-2"></i> Quick Actions</h5>
                    </div>
                    <div class="info-content">
                        <a href="bookings.php" class="btn btn-outline-primary w-100 mb-2">
                            <i class="fas fa-arrow-left me-2"></i> Back to Bookings
                        </a>
                        <a href="mailto:<?php echo $booking['ownerEmail']; ?>?subject=Regarding Booking #<?php echo $booking_id; ?>" class="btn btn-outline-success w-100 mb-2">
                            <i class="fas fa-envelope me-2"></i> Email Owner
                        </a>
                        <a href="tel:<?php echo $booking['ownerContact']; ?>" class="btn btn-outline-info w-100 mb-2">
                            <i class="fas fa-phone me-2"></i> Call Owner
                        </a>
                        <?php if ($booking['status'] === 'Confirmed'): ?>
                            <a href="https://maps.google.com/?q=<?php echo urlencode($booking['ownerAddress']); ?>" target="_blank" class="btn btn-outline-secondary w-100">
                                <i class="fas fa-map-marker-alt me-2"></i> Get Directions
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>