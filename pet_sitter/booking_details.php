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

// Calculate total hours (approximate)
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

<!-- Page Header -->
<div class="container-fluid bg-light py-4">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="display-5 mb-2">Booking #<?php echo $booking_id; ?></h1>
                <p class="lead">
                    <?php
                    $status_class = '';
                    switch ($booking['status']) {
                        case 'Pending':
                            $status_class = 'bg-warning';
                            break;
                        case 'Confirmed':
                            $status_class = 'bg-success';
                            break;
                        case 'Cancelled':
                            $status_class = 'bg-danger';
                            break;
                        case 'Completed':
                            $status_class = 'bg-info';
                            break;
                    }
                    ?>
                    Status: <span class="badge <?php echo $status_class; ?>"><?php echo $booking['status']; ?></span>
                </p>
            </div>
            <div class="col-md-4 text-md-end">
                <a href="bookings.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-1"></i> Back to Bookings
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Booking Details -->
<div class="container py-5">
    <div class="row">
        <!-- Booking Information -->
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Booking Information</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Check-in Date:</strong></p>
                            <p><?php echo date('F d, Y', strtotime($booking['checkInDate'])); ?> at <?php echo date('h:i A', strtotime($booking['checkInTime'])); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Check-out Date:</strong></p>
                            <p><?php echo date('F d, Y', strtotime($booking['checkOutDate'])); ?> at <?php echo date('h:i A', strtotime($booking['checkOutTime'])); ?></p>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Duration:</strong></p>
                            <p>
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
                            </p>
                        </div>
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Booking Date:</strong></p>
                            <p><?php echo date('F d, Y', strtotime($booking['created_at'])); ?></p>
                        </div>
                    </div>
                    
                    <?php if (!empty($booking['additionalInformation'])): ?>
                        <div class="mb-3">
                            <p class="mb-1"><strong>Additional Information:</strong></p>
                            <div class="alert alert-light">
                                <?php echo nl2br(htmlspecialchars($booking['additionalInformation'])); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <p class="mb-1"><strong>Payment Status:</strong></p>
                        <?php if ($payment): ?>
                            <span class="badge bg-<?php echo ($payment['status'] === 'Completed') ? 'success' : 'warning'; ?>">
                                <?php echo $payment['status']; ?>
                            </span>
                            <p class="mt-2">
                                <strong>Payment Amount:</strong> $<?php echo number_format($payment['amount'], 2); ?><br>
                                <strong>Payment Method:</strong> <?php echo ucfirst($payment['payment_method']); ?><br>
                                <strong>Payment Date:</strong> <?php echo date('F d, Y', strtotime($payment['paymentDate'])); ?>
                            </p>
                        <?php else: ?>
                            <span class="badge bg-secondary">No Payment Information</span>
                        <?php endif; ?>
                    </div>
                    
                    <hr>
                    
                    <div class="d-flex justify-content-between">
                        <div>
                            <p class="mb-1"><strong>Your Hourly Rate:</strong></p>
                            <p>$<?php echo number_format($hourly_rate, 2); ?> per hour</p>
                        </div>
                        <div>
                            <p class="mb-1"><strong>Total Booking Value:</strong></p>
                            <p class="h4 text-primary">$<?php echo number_format($total_cost, 2); ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Pet Owner Information -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Pet Owner Information</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Name:</strong></p>
                            <p><?php echo htmlspecialchars($booking['ownerName']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Contact:</strong></p>
                            <p><?php echo htmlspecialchars($booking['ownerContact']); ?></p>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Email:</strong></p>
                            <p><?php echo htmlspecialchars($booking['ownerEmail']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Address:</strong></p>
                            <p><?php echo nl2br(htmlspecialchars($booking['ownerAddress'])); ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Status Update -->
            <?php if ($booking['status'] === 'Pending'): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Update Booking Status</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            This booking is currently pending. Please confirm or decline this booking.
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . '?id=' . $booking_id); ?>" method="post" onsubmit="return confirm('Are you sure you want to confirm this booking?');">
                                <input type="hidden" name="new_status" value="Confirmed">
                                <button type="submit" name="update_status" class="btn btn-success">
                                    <i class="fas fa-check me-1"></i> Confirm Booking
                                </button>
                            </form>
                            
                            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . '?id=' . $booking_id); ?>" method="post" onsubmit="return confirm('Are you sure you want to decline this booking?');">
                                <input type="hidden" name="new_status" value="Cancelled">
                                <button type="submit" name="update_status" class="btn btn-danger">
                                    <i class="fas fa-times me-1"></i> Decline Booking
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php elseif ($booking['status'] === 'Confirmed' && strtotime($booking['checkOutDate']) <= time()): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Update Booking Status</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            This booking has passed its check-out date. You can now mark it as completed.
                        </div>
                        
                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . '?id=' . $booking_id); ?>" method="post" onsubmit="return confirm('Are you sure you want to mark this booking as completed?');">
                            <input type="hidden" name="new_status" value="Completed">
                            <button type="submit" name="update_status" class="btn btn-info">
                                <i class="fas fa-check-circle me-1"></i> Mark as Completed
                            </button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Pet Information -->
        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Pet Information</h5>
                </div>
                <div class="card-body text-center">
                    <?php if (!empty($booking['petImage'])): ?>
                        <img src="../assets/images/pets/<?php echo htmlspecialchars($booking['petImage']); ?>" class="rounded-circle mb-3" width="150" height="150" alt="<?php echo htmlspecialchars($booking['petName']); ?>">
                    <?php else: ?>
                        <img src="../assets/images/pet-placeholder.jpg" class="rounded-circle mb-3" width="150" height="150" alt="Pet Placeholder">
                    <?php endif; ?>
                    
                    <h4><?php echo htmlspecialchars($booking['petName']); ?></h4>
                    
                    <?php if ($booking['petRating']): ?>
                        <div class="rating mb-3">
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
                            <span class="ms-1"><?php echo $pet_rating; ?> (<?php echo $booking['petRatingCount']; ?> ratings)</span>
                        </div>
                    <?php else: ?>
                        <p class="text-muted mb-3">No ratings yet</p>
                    <?php endif; ?>
                    
                    <div class="text-start">
                        <div class="mb-3">
                            <p class="mb-1"><strong>Type:</strong> <?php echo htmlspecialchars($booking['petType']); ?></p>
                            <p class="mb-1"><strong>Breed:</strong> <?php echo htmlspecialchars($booking['petBreed']); ?></p>
                            <p class="mb-1"><strong>Age:</strong> <?php echo $booking['petAge']; ?> years</p>
                            <p class="mb-1"><strong>Sex:</strong> <?php echo $booking['petSex']; ?></p>
                            <p class="mb-0"><strong>Color:</strong> <?php echo htmlspecialchars($booking['petColor']); ?></p>
                        </div>
                    </div>
                    
                    <?php if ($booking['status'] === 'Completed'): ?>
                        <a href="add_pet_ranking.php?booking_id=<?php echo $booking_id; ?>" class="btn btn-warning w-100">
                            <i class="fas fa-star me-1"></i> Rank This Pet
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <!-- For all statuses except Cancelled -->
                        <?php if ($booking['status'] !== 'Cancelled'): ?>
                            <a href="mailto:<?php echo $booking['ownerEmail']; ?>" class="btn btn-outline-primary">
                                <i class="fas fa-envelope me-1"></i> Email Pet Owner
                            </a>
                            
                            <a href="tel:<?php echo $booking['ownerContact']; ?>" class="btn btn-outline-success">
                                <i class="fas fa-phone me-1"></i> Call Pet Owner
                            </a>
                        <?php endif; ?>
                        
                        <!-- For Confirmed bookings -->
                        <?php if ($booking['status'] === 'Confirmed'): ?>
                            <button type="button" class="btn btn-outline-warning" onclick="window.print();">
                                <i class="fas fa-print me-1"></i> Print Booking Details
                            </button>
                        <?php endif; ?>
                        
                        <!-- For Completed bookings -->
                        <?php if ($booking['status'] === 'Completed' && !$booking['petRating']): ?>
                            <a href="add_pet_ranking.php?booking_id=<?php echo $booking_id; ?>" class="btn btn-warning">
                                <i class="fas fa-star me-1"></i> Rank This Pet
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include_once '../includes/footer.php';

// Close database connection
$conn->close();
?>