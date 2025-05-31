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

// Process reply submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_reply'])) {
    $review_id = $_POST['review_id'];
    $reply = trim($_POST['reply']);
    
    // Validate reply
    if (!empty($reply)) {
        // Check if review belongs to this sitter
        $check_sql = "SELECT * FROM reviews WHERE reviewID = ? AND sitterID = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ii", $review_id, $user_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            // Update review with reply
            $update_sql = "UPDATE reviews SET reply = ?, reply_date = NOW() WHERE reviewID = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("si", $reply, $review_id);
            
            if ($update_stmt->execute()) {
                $_SESSION['success_message'] = "Your reply has been submitted successfully.";
                header("Location: reviews.php");
                exit();
            } else {
                $_SESSION['error_message'] = "Error submitting reply: " . $conn->error;
            }
            
            $update_stmt->close();
        } else {
            $_SESSION['error_message'] = "Invalid review or you cannot reply to this review.";
        }
        
        $check_stmt->close();
    } else {
        $_SESSION['error_message'] = "Reply cannot be empty.";
    }
}

// Get all reviews for this sitter
$reviews_sql = "SELECT r.*, po.fullName as ownerName, po.image as ownerImage, b.checkInDate, b.checkOutDate 
                FROM reviews r 
                JOIN pet_owner po ON r.userID = po.userID 
                JOIN booking b ON r.bookingID = b.bookingID
                WHERE r.sitterID = ? 
                ORDER BY r.created_at DESC";
$reviews_stmt = $conn->prepare($reviews_sql);
$reviews_stmt->bind_param("i", $user_id);
$reviews_stmt->execute();
$reviews_result = $reviews_stmt->get_result();
$reviews = [];

while ($row = $reviews_result->fetch_assoc()) {
    $reviews[] = $row;
}

$reviews_stmt->close();

// Calculate average rating
$avg_rating = 0;
$review_count = count($reviews);

if ($review_count > 0) {
    $total_rating = 0;
    foreach ($reviews as $review) {
        $total_rating += $review['rating'];
    }
    $avg_rating = round($total_rating / $review_count, 1);
}

// Include header
include_once '../includes/header.php';
?>

<!-- Page Header -->
<div class="container-fluid bg-light py-4">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="display-5 mb-2">My Reviews</h1>
                <p class="lead">View and respond to reviews from pet owners.</p>
            </div>
            <div class="col-md-4 text-md-end">
                <a href="dashboard.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Reviews Section -->
<div class="container py-5">
    <!-- Rating Summary -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md-4 text-center">
                    <h1 class="display-4 mb-0"><?php echo $avg_rating; ?></h1>
                    <div class="rating mb-2">
                        <?php
                        $full_stars = floor($avg_rating);
                        $half_star = $avg_rating - $full_stars >= 0.5;
                        
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
                    </div>
                    <p class="text-muted"><?php echo $review_count; ?> reviews</p>
                </div>
                
                <div class="col-md-8">
                    <div class="rating-breakdown">
                        <?php
                        // Calculate rating breakdown
                        $ratings = [0, 0, 0, 0, 0]; // 1-star to 5-star count
                        foreach ($reviews as $review) {
                            $ratings[$review['rating'] - 1]++;
                        }
                        
                        for ($i = 5; $i >= 1; $i--) {
                            $count = $ratings[$i - 1];
                            $percentage = $review_count > 0 ? ($count / $review_count) * 100 : 0;
                        ?>
                            <div class="d-flex align-items-center mb-2">
                                <div class="me-2" style="width: 60px;"><?php echo $i; ?> stars</div>
                                <div class="progress flex-grow-1 me-2">
                                    <div class="progress-bar bg-warning" role="progressbar" style="width: <?php echo $percentage; ?>%" aria-valuenow="<?php echo $percentage; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                                <div style="width: 40px;"><?php echo $count; ?></div>
                            </div>
                        <?php
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Reviews List -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">All Reviews (<?php echo $review_count; ?>)</h5>
        </div>
        <div class="card-body">
            <?php if (empty($reviews)): ?>
                <p class="text-center text-muted">You haven't received any reviews yet.</p>
            <?php else: ?>
                <?php foreach ($reviews as $review): ?>
                    <div class="review mb-4">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div class="d-flex align-items-center">
                                <div class="me-3">
                                    <?php if (!empty($review['ownerImage'])): ?>
                                        <img src="../assets/images/users/<?php echo htmlspecialchars($review['ownerImage']); ?>" class="rounded-circle" width="50" height="50" alt="<?php echo htmlspecialchars($review['ownerName']); ?>">
                                    <?php else: ?>
                                        <img src="../assets/images/user-placeholder.jpg" class="rounded-circle" width="50" height="50" alt="User">
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <h6 class="mb-0"><?php echo htmlspecialchars($review['ownerName']); ?></h6>
                                    <div class="rating">
                                        <?php
                                        for ($i = 1; $i <= 5; $i++) {
                                            if ($i <= $review['rating']) {
                                                echo '<i class="fas fa-star"></i>';
                                            } else {
                                                echo '<i class="far fa-star"></i>';
                                            }
                                        }
                                        ?>
                                    </div>
                                    <small class="text-muted">
                                        <?php echo date('M d, Y', strtotime($review['created_at'])); ?> | 
                                        Booking: <?php echo date('M d', strtotime($review['checkInDate'])); ?> - <?php echo date('M d, Y', strtotime($review['checkOutDate'])); ?>
                                    </small>
                                </div>
                            </div>
                            <span class="badge bg-<?php echo ($review['rating'] >= 4) ? 'success' : (($review['rating'] >= 3) ? 'warning' : 'danger'); ?>"><?php echo $review['rating']; ?>.0</span>
                        </div>
                        
                        <div class="review-content mb-3">
                            <p><?php echo nl2br(htmlspecialchars($review['review'])); ?></p>
                        </div>
                        
                        <?php if (!empty($review['reply'])): ?>
                            <div class="reply-container mb-3 ms-5 p-3 bg-light rounded">
                                <div class="d-flex justify-content-between align-items-start">
                                    <h6 class="mb-2">Your Response</h6>
                                    <small class="text-muted"><?php echo date('M d, Y', strtotime($review['reply_date'])); ?></small>
                                </div>
                                <p class="mb-0"><?php echo nl2br(htmlspecialchars($review['reply'])); ?></p>
                            </div>
                            
                            <div class="text-end">
                                <button type="button" class="btn btn-sm btn-outline-primary edit-reply-btn" data-review-id="<?php echo $review['reviewID']; ?>" data-reply="<?php echo htmlspecialchars($review['reply']); ?>">
                                    <i class="fas fa-edit me-1"></i> Edit Response
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="text-end">
                                <button type="button" class="btn btn-sm btn-primary reply-btn" data-review-id="<?php echo $review['reviewID']; ?>">
                                    <i class="fas fa-reply me-1"></i> Respond to Review
                                </button>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Reply Form (Hidden by default) -->
                        <div class="reply-form mt-3" id="reply-form-<?php echo $review['reviewID']; ?>" style="display: none;">
                            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                                <input type="hidden" name="review_id" value="<?php echo $review['reviewID']; ?>">
                                <div class="mb-3">
                                    <label for="reply-<?php echo $review['reviewID']; ?>" class="form-label">Your Response</label>
                                    <textarea class="form-control" id="reply-<?php echo $review['reviewID']; ?>" name="reply" rows="3" required></textarea>
                                    <div class="form-text">Your response will be public and visible to all users.</div>
                                </div>
                                <div class="text-end">
                                    <button type="button" class="btn btn-outline-secondary me-2 cancel-reply-btn" data-review-id="<?php echo $review['reviewID']; ?>">Cancel</button>
                                    <button type="submit" name="submit_reply" class="btn btn-primary">Submit Response</button>
                                </div>
                            </form>
                        </div>
                        
                        <?php if ($review !== end($reviews)): ?>
                            <hr class="my-4">
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Review Tips Section -->
<div class="container mb-5">
    <div class="card bg-light">
        <div class="card-body">
            <h5 class="card-title">Tips for Responding to Reviews</h5>
            <div class="row">
                <div class="col-md-6">
                    <h6 class="text-primary"><i class="fas fa-check-circle me-2"></i> Do:</h6>
                    <ul class="list-unstyled">
                        <li><i class="fas fa-angle-right me-2"></i> Thank the reviewer for their feedback</li>
                        <li><i class="fas fa-angle-right me-2"></i> Respond promptly and professionally</li>
                        <li><i class="fas fa-angle-right me-2"></i> Address specific points mentioned in the review</li>
                        <li><i class="fas fa-angle-right me-2"></i> Be concise and respectful</li>
                        <li><i class="fas fa-angle-right me-2"></i> Show that you value their business</li>
                    </ul>
                </div>
                <div class="col-md-6">
                    <h6 class="text-danger"><i class="fas fa-times-circle me-2"></i> Don't:</h6>
                    <ul class="list-unstyled">
                        <li><i class="fas fa-angle-right me-2"></i> Get defensive or argumentative</li>
                        <li><i class="fas fa-angle-right me-2"></i> Make excuses or blame the customer</li>
                        <li><i class="fas fa-angle-right me-2"></i> Write lengthy responses</li>
                        <li><i class="fas fa-angle-right me-2"></i> Include personal or confidential information</li>
                        <li><i class="fas fa-angle-right me-2"></i> Ignore negative reviews</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Show reply form when reply button is clicked
        const replyButtons = document.querySelectorAll('.reply-btn');
        replyButtons.forEach(button => {
            button.addEventListener('click', function() {
                const reviewId = this.getAttribute('data-review-id');
                document.getElementById(`reply-form-${reviewId}`).style.display = 'block';
                this.style.display = 'none';
            });
        });
        
        // Hide reply form when cancel button is clicked
        const cancelButtons = document.querySelectorAll('.cancel-reply-btn');
        cancelButtons.forEach(button => {
            button.addEventListener('click', function() {
                const reviewId = this.getAttribute('data-review-id');
                document.getElementById(`reply-form-${reviewId}`).style.display = 'none';
                document.querySelector(`.reply-btn[data-review-id="${reviewId}"]`).style.display = 'inline-block';
            });
        });
        
        // Pre-fill and show reply form when edit reply button is clicked
        const editReplyButtons = document.querySelectorAll('.edit-reply-btn');
        editReplyButtons.forEach(button => {
            button.addEventListener('click', function() {
                const reviewId = this.getAttribute('data-review-id');
                const reply = this.getAttribute('data-reply');
                
                const replyForm = document.getElementById(`reply-form-${reviewId}`);
                const replyTextarea = document.getElementById(`reply-${reviewId}`);
                
                replyTextarea.value = reply;
                replyForm.style.display = 'block';
                this.style.display = 'none';
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