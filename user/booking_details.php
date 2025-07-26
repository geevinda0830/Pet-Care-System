<?php
// FILE PATH: /user/booking_details.php

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
                ps.fullName as sitterName, 
                ps.email as sitterEmail, 
                ps.contact as sitterContact, 
                ps.address as sitterAddress,
                ps.service as sitterService,
                ps.price as hourlyRate,
                ps.image as sitterImage,
                p.petID, 
                p.petName, 
                p.type as petType, 
                p.breed as petBreed, 
                p.age as petAge, 
                p.sex as petSex, 
                p.color as petColor,
                p.image as petImage
                FROM booking b 
                JOIN pet_sitter ps ON b.sitterID = ps.userID
                JOIN pet_profile p ON b.petID = p.petID
                WHERE b.bookingID = ? AND b.userID = ?";
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
$total_hours = $interval->days * 24 + $interval->h + ($interval->i / 60);
$total_cost = $total_hours * $booking['hourlyRate'];

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

.cost-breakdown {
    background: #f8fafc;
    border-radius: 12px;
    padding: 20px;
}

.cost-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid #e5e7eb;
}

.cost-item:last-child {
    border-bottom: none;
    font-weight: 600;
    font-size: 1.1rem;
}

.pet-card {
    display: flex;
    align-items: center;
    gap: 20px;
    background: #f8fafc;
    padding: 20px;
    border-radius: 12px;
}

.pet-image {
    width: 80px;
    height: 80px;
    border-radius: 12px;
    object-fit: cover;
}

.pet-info h6 {
    margin: 0 0 8px 0;
    color: #1e293b;
}

.pet-details {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
    font-size: 0.9rem;
    color: #64748b;
}

.contact-grid {
    display: grid;
    gap: 20px;
}

.contact-item {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 15px;
    background: #f8fafc;
    border-radius: 12px;
}

.contact-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
}

.contact-icon.phone { background: #10b981; }
.contact-icon.email { background: #3b82f6; }
.contact-icon.address { background: #8b5cf6; }

.back-btn {
    background: linear-gradient(135deg, #667eea, #764ba2);
    border: none;
    color: white;
    padding: 12px 24px;
    border-radius: 25px;
    text-decoration: none;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.back-btn:hover {
    color: white;
    transform: translateY(-2px);
}
</style>

<section class="page-header-modern">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <div class="page-badge">Booking Details</div>
                <h1 class="page-title">Booking #<?php echo $booking['bookingID']; ?></h1>
                <p class="page-subtitle mb-0">View your booking information and details</p>
            </div>
            <div class="col-lg-4 text-end">
                <a href="bookings.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i> Back to Bookings
                </a>
            </div>
        </div>
    </div>
</section>

<section class="booking-details-section">
    <div class="container">
        <div class="row">
            <div class="col-lg-8">
                <!-- Booking Status -->
                <div class="info-card">
                    <div class="card-header">
                        <h5><i class="fas fa-calendar-check me-2"></i> Booking Status</h5>
                    </div>
                    <div class="info-content">
                        <span class="status-badge status-<?php echo strtolower($booking['status']); ?>">
                            <?php echo $booking['status']; ?>
                        </span>
                        
                        <!-- Schedule -->
                        <div class="schedule-grid mt-4">
                            <div class="schedule-item">
                                <div class="schedule-icon checkin">
                                    <i class="fas fa-sign-in-alt"></i>
                                </div>
                                <div>
                                    <strong>Check-in</strong><br>
                                    <span class="text-muted">
                                        <?php echo date('M d, Y', strtotime($booking['checkInDate'])); ?> at 
                                        <?php echo date('g:i A', strtotime($booking['checkInTime'])); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="schedule-item">
                                <div class="schedule-icon checkout">
                                    <i class="fas fa-sign-out-alt"></i>
                                </div>
                                <div>
                                    <strong>Check-out</strong><br>
                                    <span class="text-muted">
                                        <?php echo date('M d, Y', strtotime($booking['checkOutDate'])); ?> at 
                                        <?php echo date('g:i A', strtotime($booking['checkOutTime'])); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <?php if (!empty($booking['additionalInformations'])): ?>
                        <div class="mt-4">
                            <h6>Additional Information</h6>
                            <p class="text-muted mb-0"><?php echo nl2br(htmlspecialchars($booking['additionalInformations'])); ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Pet Information -->
                <div class="info-card">
                    <div class="card-header">
                        <h5><i class="fas fa-paw me-2"></i> Pet Information</h5>
                    </div>
                    <div class="info-content">
                        <div class="pet-card">
                            <?php if (!empty($booking['petImage'])): ?>
                                <img src="../assets/images/pets/<?php echo htmlspecialchars($booking['petImage']); ?>" 
                                     alt="<?php echo htmlspecialchars($booking['petName']); ?>" class="pet-image">
                            <?php else: ?>
                                <div class="pet-image bg-light d-flex align-items-center justify-content-center">
                                    <i class="fas fa-paw fa-2x text-muted"></i>
                                </div>
                            <?php endif; ?>
                            
                            <div class="pet-info">
                                <h6><?php echo htmlspecialchars($booking['petName']); ?></h6>
                                <div class="pet-details">
                                    <span><strong>Type:</strong> <?php echo htmlspecialchars($booking['petType']); ?></span>
                                    <span><strong>Age:</strong> <?php echo htmlspecialchars($booking['petAge']); ?> years</span>
                                    <span><strong>Breed:</strong> <?php echo htmlspecialchars($booking['petBreed']); ?></span>
                                    <span><strong>Color:</strong> <?php echo htmlspecialchars($booking['petColor']); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pet Sitter Information -->
                <div class="info-card">
                    <div class="card-header">
                        <h5><i class="fas fa-user-tie me-2"></i> Pet Sitter Information</h5>
                    </div>
                    <div class="info-content">
                        <div class="contact-grid">
                            <div class="contact-item">
                                <div class="contact-icon phone">
                                    <i class="fas fa-phone"></i>
                                </div>
                                <div>
                                    <strong><?php echo htmlspecialchars($booking['sitterName']); ?></strong><br>
                                    <a href="tel:<?php echo htmlspecialchars($booking['sitterContact']); ?>" class="text-decoration-none">
                                        <?php echo htmlspecialchars($booking['sitterContact']); ?>
                                    </a>
                                </div>
                            </div>
                            
                            <div class="contact-item">
                                <div class="contact-icon email">
                                    <i class="fas fa-envelope"></i>
                                </div>
                                <div>
                                    <strong>Email:</strong><br>
                                    <a href="mailto:<?php echo htmlspecialchars($booking['sitterEmail']); ?>" class="text-decoration-none">
                                        <?php echo htmlspecialchars($booking['sitterEmail']); ?>
                                    </a>
                                </div>
                            </div>
                            
                            <div class="contact-item">
                                <div class="contact-icon address">
                                    <i class="fas fa-map-marker-alt"></i>
                                </div>
                                <div>
                                    <strong>Address:</strong><br>
                                    <span class="text-muted"><?php echo htmlspecialchars($booking['sitterAddress']); ?></span>
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
                                <span>Rs. <?php echo number_format($booking['hourlyRate'], 2); ?></span>
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
                                    <span>Payment Date:</span>
                                    <span><?php echo date('M d, Y', strtotime($payment['paymentDate'])); ?></span>
                                </div>
                                <?php if (!empty($payment['payment_method'])): ?>
                                <div class="cost-item">
                                    <span>Payment Method:</span>
                                    <span><?php echo htmlspecialchars($payment['payment_method']); ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php elseif ($booking['status'] === 'Confirmed'): ?>
                    <div class="info-card">
                        <div class="card-header">
                            <h5><i class="fas fa-credit-card me-2"></i> Payment Required</h5>
                        </div>
                        <div class="info-content">
                            <p class="text-muted mb-3">Payment is required to complete this booking.</p>
                            <a href="../payment.php?booking_id=<?php echo $booking['bookingID']; ?>" class="btn btn-primary w-100">
                                <i class="fas fa-credit-card me-2"></i> Make Payment
                            </a>
                        </div>
                    </div>
                <?php elseif ($booking['status'] === 'Pending'): ?>
                    <div class="info-card">
                        <div class="card-header">
                            <h5><i class="fas fa-clock me-2"></i> Payment Status</h5>
                        </div>
                        <div class="info-content">
                            <p class="text-muted mb-0">Payment will be available once the pet sitter confirms your booking request.</p>
                        </div>
                    </div>
                <?php elseif ($booking['status'] === 'Cancelled'): ?>
                    <div class="info-card">
                        <div class="card-header">
                            <h5><i class="fas fa-times-circle me-2"></i> Booking Cancelled</h5>
                        </div>
                        <div class="info-content">
                            <p class="text-muted mb-0">This booking has been cancelled. No payment is required.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php
// Include footer
include_once '../includes/footer.php';

// Close database connection
$conn->close();
?>