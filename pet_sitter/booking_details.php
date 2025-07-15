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

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 25px;
}

.info-item {
    padding: 20px;
    background: #f8fafc;
    border-radius: 15px;
    border-left: 4px solid #667eea;
    transition: all 0.3s ease;
}

.info-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
}

.info-item label {
    font-weight: 600;
    color: #64748b;
    font-size: 0.9rem;
    margin-bottom: 8px;
    display: block;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.info-item p {
    margin: 0;
    color: #1e293b;
    font-weight: 500;
    font-size: 1rem;
}

.status-badge {
    padding: 8px 16px;
    border-radius: 20px;
    font-weight: 600;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-pending { background: #fef3c7; color: #92400e; }
.status-confirmed { background: #dbeafe; color: #1e40af; }
.status-completed { background: #dcfce7; color: #166534; }
.status-cancelled { background: #fee2e2; color: #dc2626; }

/* Pet Info Styles */
.pet-info {
    display: flex;
    align-items: center;
    gap: 20px;
    padding: 25px;
    background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
    border-radius: 16px;
    border: 1px solid #0ea5e9;
}

.pet-avatar {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    overflow: hidden;
    border: 3px solid white;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.pet-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.pet-placeholder {
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, #667eea, #764ba2);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 2rem;
}

.pet-details h4 {
    margin: 0 0 8px 0;
    color: #0c4a6e;
    font-weight: 700;
}

.pet-meta {
    display: flex;
    gap: 15px;
    margin-bottom: 10px;
}

.pet-meta span {
    background: rgba(14, 165, 233, 0.1);
    color: #0369a1;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 600;
}

.rating-display {
    display: flex;
    align-items: center;
    gap: 8px;
}

.stars {
    color: #fbbf24;
}

.rating-text {
    color: #64748b;
    font-size: 0.9rem;
}

/* Contact Info */
.contact-card {
    background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
    border-radius: 16px;
    padding: 25px;
    border: 1px solid #10b981;
}

.contact-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid rgba(16, 185, 129, 0.2);
}

.contact-item:last-child {
    border-bottom: none;
}

.contact-label {
    font-weight: 600;
    color: #064e3b;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.contact-value {
    font-weight: 500;
    color: #065f46;
}

.contact-actions {
    display: flex;
    gap: 10px;
    margin-top: 20px;
}

.contact-actions .btn {
    flex: 1;
    font-size: 0.9rem;
}

/* Summary Card */
.summary-card {
    background: linear-gradient(135deg, #fefce8 0%, #fef3c7 100%);
    border-radius: 16px;
    padding: 25px;
    border: 1px solid #f59e0b;
}

.summary-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid rgba(245, 158, 11, 0.2);
}

.summary-item:last-child {
    border-bottom: none;
}

.summary-item.total {
    padding-top: 16px;
    margin-top: 8px;
    border-top: 2px solid #f59e0b;
}

.summary-label {
    font-weight: 600;
    color: #92400e;
    font-size: 0.9rem;
}

.summary-value {
    font-weight: 600;
    color: #b45309;
}

.total-value {
    font-size: 1.3rem;
    color: #92400e !important;
}

.summary-divider {
    height: 1px;
    background: rgba(245, 158, 11, 0.3);
    margin: 16px 0;
}

.payment-status {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid rgba(245, 158, 11, 0.3);
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
    color: #92400e;
    font-size: 0.9rem;
}

/* Timeline */
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
    background: white;
    padding: 20px;
    border-radius: 16px;
    border-left: 4px solid #667eea;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
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

/* Status Update Form */
.status-update-form {
    background: white;
    padding: 25px;
    border-radius: 16px;
    border: 1px solid #e5e7eb;
    margin-bottom: 30px;
}

.form-group {
    margin-bottom: 20px;
}

.form-label {
    font-weight: 600;
    color: #374151;
    margin-bottom: 8px;
    display: block;
}

.form-select {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    font-size: 1rem;
    transition: all 0.3s ease;
}

.form-select:focus {
    border-color: #667eea;
    outline: none;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.btn-primary {
    background: linear-gradient(135deg, #667eea, #764ba2);
    border: none;
    padding: 12px 24px;
    border-radius: 12px;
    color: white;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
}

/* Responsive */
@media (max-width: 768px) {
    .schedule-grid {
        grid-template-columns: 1fr;
    }
    
    .info-grid {
        grid-template-columns: 1fr;
    }
    
    .pet-info {
        flex-direction: column;
        text-align: center;
    }
    
    .pet-meta {
        justify-content: center;
    }
}
</style>

<!-- Modern Page Header -->
<section class="page-header-modern">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-12 text-center">
                <span class="page-badge">🐾 Booking Management</span>
                <h1 class="page-title">Booking <span class="text-gradient">#<?php echo $booking_id; ?></span></h1>
                <p class="page-subtitle">Manage your pet sitting appointment details</p>
            </div>
        </div>
    </div>
</section>

<!-- Booking Details Content -->
<section class="booking-details-section">
    <div class="container">
        <div class="row">
            <!-- Main Content -->
            <div class="col-lg-8">
                <!-- Booking Status Update -->
                <?php if ($booking['status'] == 'Pending' || $booking['status'] == 'Confirmed'): ?>
                    <div class="info-card">
                        <div class="card-header">
                            <h5><i class="fas fa-edit me-2"></i> Update Booking Status</h5>
                        </div>
                        <div class="info-content">
                            <form method="POST" class="status-update-form" style="background: none; padding: 0; border: none; margin: 0;">
                                <div class="form-group">
                                    <label for="new_status" class="form-label">Change Status:</label>
                                    <select name="new_status" id="new_status" class="form-select" required>
                                        <option value="">Select new status...</option>
                                        <?php if ($booking['status'] == 'Pending'): ?>
                                            <option value="Confirmed">Accept Booking</option>
                                            <option value="Cancelled">Decline Booking</option>
                                        <?php elseif ($booking['status'] == 'Confirmed'): ?>
                                            <option value="Completed">Mark as Completed</option>
                                            <option value="Cancelled">Cancel Booking</option>
                                        <?php endif; ?>
                                    </select>
                                </div>
                                <button type="submit" name="update_status" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i> Update Status
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>

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
                                         alt="<?php echo htmlspecialchars($booking['petName']); ?>">
                                <?php else: ?>
                                    <div class="pet-placeholder">
                                        <i class="fas fa-paw"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="pet-details">
                                <h4><?php echo htmlspecialchars($booking['petName']); ?></h4>
                                <div class="pet-meta">
                                    <span><?php echo htmlspecialchars($booking['petType']); ?></span>
                                    <span><?php echo htmlspecialchars($booking['petBreed']); ?></span>
                                    <span><?php echo $booking['petAge']; ?> years old</span>
                                    <span><?php echo htmlspecialchars($booking['petSex']); ?></span>
                                </div>
                                <?php if ($booking['petRatingCount'] > 0): ?>
                                    <div class="rating-display">
                                        <div class="stars">
                                            <?php 
                                            $rating = round($booking['petRating']);
                                            for ($i = 1; $i <= 5; $i++): 
                                            ?>
                                                <i class="fas fa-star<?php echo $i <= $rating ? '' : '-o'; ?>"></i>
                                            <?php endfor; ?>
                                        </div>
                                        <span class="rating-text"><?php echo number_format($booking['petRating'], 1); ?> (<?php echo $booking['petRatingCount']; ?> reviews)</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pet Owner Contact -->
                <div class="info-card">
                    <div class="card-header">
                        <h5><i class="fas fa-user me-2"></i> Pet Owner Contact</h5>
                    </div>
                    <div class="info-content">
                        <div class="contact-card">
                            <div class="contact-item">
                                <span class="contact-label">Owner Name</span>
                                <span class="contact-value"><?php echo htmlspecialchars($booking['ownerName']); ?></span>
                            </div>
                            <div class="contact-item">
                                <span class="contact-label">Email</span>
                                <span class="contact-value"><?php echo htmlspecialchars($booking['ownerEmail']); ?></span>
                            </div>
                            <div class="contact-item">
                                <span class="contact-label">Phone</span>
                                <span class="contact-value"><?php echo htmlspecialchars($booking['ownerContact']); ?></span>
                            </div>
                            <div class="contact-item">
                                <span class="contact-label">Address</span>
                                <span class="contact-value"><?php echo htmlspecialchars($booking['ownerAddress']); ?></span>
                            </div>
                            <div class="contact-actions">
                                <a href="mailto:<?php echo htmlspecialchars($booking['ownerEmail']); ?>" class="btn btn-primary btn-sm">
                                    <i class="fas fa-envelope me-1"></i> Email
                                </a>
                                <a href="tel:<?php echo htmlspecialchars($booking['ownerContact']); ?>" class="btn btn-success btn-sm">
                                    <i class="fas fa-phone me-1"></i> Call
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Booking Details -->
                <div class="info-card">
                    <div class="card-header">
                        <h5><i class="fas fa-calendar me-2"></i> Booking Details</h5>
                    </div>
                    <div class="info-content">
                        <div class="info-grid">
                            <div class="info-item">
                                <label>Booking Status</label>
                                <p><span class="status-badge status-<?php echo strtolower($booking['status']); ?>"><?php echo $booking['status']; ?></span></p>
                            </div>
                            
                            <div class="info-item">
                                <label>Booking Date</label>
                                <p><?php echo date('M d, Y', strtotime($booking['checkInDate'])); ?></p>
                            </div>
                            
                            <div class="info-item">
                                <label>Check-in Time</label>
                                <p><?php echo date('g:i A', strtotime($booking['checkInTime'])); ?></p>
                            </div>
                            
                            <div class="info-item">
                                <label>Check-out Time</label>
                                <p><?php echo date('g:i A', strtotime($booking['checkOutTime'])); ?> on <?php echo date('M d, Y', strtotime($booking['checkOutDate'])); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Cost Summary -->
                <div class="summary-card">
                    <h5 class="mb-3"><i class="fas fa-calculator me-2"></i> Cost Summary</h5>
                    
                    <div class="summary-item">
                        <span class="summary-label">Duration</span>
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
                        <span class="summary-value">Rs. <?php echo number_format($hourly_rate, 2); ?></span>
                    </div>
                    
                    <div class="summary-item">
                        <span class="summary-label">Total Hours</span>
                        <span class="summary-value"><?php echo number_format($total_hours, 1); ?> hours</span>
                    </div>
                    
                    <div class="summary-divider"></div>
                    
                    <div class="summary-item total">
                        <span class="summary-label">Total Value</span>
                        <span class="summary-value total-value">Rs. <?php echo number_format($total_cost, 2); ?></span>
                    </div>
                    
                    <div class="payment-status">
                        <?php if ($payment): ?>
                            <div class="payment-info">
                                <div class="payment-badge <?php echo strtolower($payment['status']); ?>">
                                    <i class="fas fa-credit-card me-1"></i>
                                    Payment <?php echo $payment['status']; ?>
                                </div>
                                <div class="payment-details">
                                    <small>Amount: Rs. <?php echo number_format($payment['amount'], 2); ?></small><br>
                                    <small>Date: <?php echo date('M d, Y', strtotime($payment['paymentDate'])); ?></small>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="payment-badge pending">
                                <i class="fas fa-clock me-1"></i>
                                Payment Pending
                            </div>
                            <div class="payment-details">
                                <small>Payment will be processed once booking is confirmed</small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Timeline Section -->
<section class="booking-timeline-section">
    <div class="container">
        <h3 class="text-center mb-4">Booking Schedule</h3>
        
        <!-- Schedule Info -->
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

        <!-- Booking Timeline -->
        <div class="booking-timeline">
            <h5 class="mb-3">Booking Timeline</h5>
            
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
                        <p>Rs. <?php echo number_format($payment['amount'], 2); ?> via <?php echo ucfirst($payment['payment_method']); ?></p>
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
</section>

<?php
// Include footer
include_once '../includes/footer.php';

// Close database connection
$conn->close();
?>