<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in as pet owner
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'pet_owner') {
    $_SESSION['error_message'] = "You must be logged in as a pet owner to leave a review.";
    header("Location: ../login.php");
    exit();
}

// Check if sitter_id and booking_id are provided
if (!isset($_GET['sitter_id']) || empty($_GET['sitter_id']) || !isset($_GET['booking_id']) || empty($_GET['booking_id'])) {
    $_SESSION['error_message'] = "Invalid pet sitter or booking.";
    header("Location: dashboard.php");
    exit();
}

// Include database connection
require_once '../config/db_connect.php';

$user_id = $_SESSION['user_id'];
$sitter_id = $_GET['sitter_id'];
$booking_id = $_GET['booking_id'];

// Verify that booking belongs to this user, is for this sitter, and is completed
$booking_sql = "SELECT b.*, ps.fullName as sitterName, ps.image as sitterImage
                FROM booking b 
                JOIN pet_sitter ps ON b.sitterID = ps.userID
                WHERE b.bookingID = ? 
                AND b.userID = ? 
                AND b.sitterID = ? 
                AND b.status = 'Completed'";
$booking_stmt = $conn->prepare($booking_sql);
$booking_stmt->bind_param("iii", $booking_id, $user_id, $sitter_id);
$booking_stmt->execute();
$booking_result = $booking_stmt->get_result();

if ($booking_result->num_rows === 0) {
    $_SESSION['error_message'] = "Invalid booking or you cannot review this pet sitter yet.";
    header("Location: bookings.php");
    exit();
}

$booking = $booking_result->fetch_assoc();
$booking_stmt->close();

// Check if user has already reviewed this booking
$check_review_sql = "SELECT reviewID FROM reviews WHERE bookingID = ?";
$check_review_stmt = $conn->prepare($check_review_sql);
$check_review_stmt->bind_param("i", $booking_id);
$check_review_stmt->execute();
$check_review_result = $check_review_stmt->get_result();

if ($check_review_result->num_rows > 0) {
    $_SESSION['error_message'] = "You have already reviewed this booking.";
    header("Location: bookings.php");
    exit();
}

$check_review_stmt->close();

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $rating = $_POST['rating'];
    $review = trim($_POST['review']);
    
    // Validate inputs
    $errors = array();
    
    if (empty($rating) || !is_numeric($rating) || $rating < 1 || $rating > 5) {
        $errors[] = "Please provide a rating between 1 and 5.";
    }
    
    if (empty($review)) {
        $errors[] = "Please write a review.";
    }
    
    // If no errors, insert review
    if (empty($errors)) {
        $insert_sql = "INSERT INTO reviews (userID, sitterID, bookingID, rating, review, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("iiiis", $user_id, $sitter_id, $booking_id, $rating, $review);
        
        if ($insert_stmt->execute()) {
            $_SESSION['success_message'] = "Your review has been submitted successfully.";
            header("Location: bookings.php");
            exit();
        } else {
            $errors[] = "Error submitting review: " . $conn->error;
        }
        
        $insert_stmt->close();
    }
}

// Include header
include_once '../includes/header.php';
?>

<!-- Page Header -->
<div class="container-fluid bg-light py-4">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="display-5 mb-2">Write a Review</h1>
                <p class="lead">Share your experience with this pet sitter.</p>
            </div>
            <div class="col-md-4 text-md-end">
                <a href="bookings.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-1"></i> Back to Bookings
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Review Form -->
<div class="container py-5">
    <div class="row">
        <!-- Pet Sitter Info -->
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-body text-center">
                    <?php if (!empty($booking['sitterImage'])): ?>
                        <img src="../assets/images/pet_sitters/<?php echo htmlspecialchars($booking['sitterImage']); ?>" class="rounded-circle img-fluid mb-3" width="150" alt="<?php echo htmlspecialchars($booking['sitterName']); ?>">
                    <?php else: ?>
                        <img src="../assets/images/sitter-placeholder.jpg" class="rounded-circle img-fluid mb-3" width="150" alt="Placeholder">
                    <?php endif; ?>
                    
                    <h4 class="card-title"><?php echo htmlspecialchars($booking['sitterName']); ?></h4>
                </div>
            </div>
            
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Booking Details</h5>
                </div>
                <div class="card-body">
                    <p><strong>Booking ID:</strong> #<?php echo $booking['bookingID']; ?></p>
                    <p><strong>Dates:</strong> <?php echo date('M d, Y', strtotime($booking['checkInDate'])); ?> - <?php echo date('M d, Y', strtotime($booking['checkOutDate'])); ?></p>
                    <p><strong>Status:</strong> <span class="badge bg-info"><?php echo $booking['status']; ?></span></p>
                </div>
            </div>
        </div>
        
        <!-- Review Form -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title mb-4">Your Review</h4>
                    
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach($errors as $error): ?>
                                    <li><?php echo $error; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . "?sitter_id=" . $sitter_id . "&booking_id=" . $booking_id); ?>" method="post">
                        <div class="mb-4">
                            <label class="form-label">Rating <span class="text-danger">*</span></label>
                            <div class="rating-select mb-2">
                                <i class="far fa-star fs-3"></i>
                                <i class="far fa-star fs-3"></i>
                                <i class="far fa-star fs-3"></i>
                                <i class="far fa-star fs-3"></i>
                                <i class="far fa-star fs-3"></i>
                            </div>
                            <input type="hidden" id="rating-input" name="rating" value="">
                            <div class="form-text">Click on the stars to rate your experience.</div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="review" class="form-label">Your Review <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="review" name="review" rows="5" placeholder="Share your experience with this pet sitter..." required></textarea>
                            <div class="form-text">Please provide details about your experience. What did you like? What could be improved?</div>
                        </div>
                        
                        <div class="alert alert-info mb-4">
                            <i class="fas fa-info-circle me-2"></i>
                            Your review will be public and help other pet owners make informed decisions.
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Submit Review</button>
                    </form>
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