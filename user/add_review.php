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
$booking_sql = "SELECT b.*, ps.fullName as sitterName, ps.image as sitterImage, p.petName
                FROM booking b 
                JOIN pet_sitter ps ON b.sitterID = ps.userID
                JOIN pet_profile p ON b.petID = p.petID
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

<!-- Modern Page Header -->
<section class="page-header-modern">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <div class="page-header-content">
                    <span class="page-badge">⭐ Write Review</span>
                    <h1 class="page-title">Share Your <span class="text-gradient">Experience</span></h1>
                    <p class="page-subtitle">Help other pet owners by sharing your experience with this pet sitter</p>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="page-actions">
                    <a href="bookings.php" class="btn btn-outline-light">
                        <i class="fas fa-arrow-left me-2"></i> Back to Bookings
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Review Form Section -->
<section class="review-section-modern">
    <div class="container">
        <div class="row">
            <!-- Pet Sitter Info Card -->
            <div class="col-lg-4">
                <div class="sitter-info-card">
                    <div class="sitter-avatar">
                        <?php if (!empty($booking['sitterImage'])): ?>
                            <img src="../assets/images/pet_sitters/<?php echo htmlspecialchars($booking['sitterImage']); ?>" alt="<?php echo htmlspecialchars($booking['sitterName']); ?>">
                        <?php else: ?>
                            <div class="avatar-placeholder">
                                <i class="fas fa-user"></i>
                            </div>
                        <?php endif; ?>
                        <div class="verified-badge">
                            <i class="fas fa-check-circle"></i>
                            Verified
                        </div>
                    </div>
                    
                    <div class="sitter-details">
                        <h4 class="sitter-name"><?php echo htmlspecialchars($booking['sitterName']); ?></h4>
                        <p class="sitter-title">Pet Care Professional</p>
                    </div>
                    
                    <div class="booking-summary">
                        <h6>Booking Summary</h6>
                        <div class="summary-item">
                            <span class="label">Booking ID:</span>
                            <span class="value">#<?php echo $booking['bookingID']; ?></span>
                        </div>
                        <div class="summary-item">
                            <span class="label">Pet:</span>
                            <span class="value"><?php echo htmlspecialchars($booking['petName']); ?></span>
                        </div>
                        <div class="summary-item">
                            <span class="label">Dates:</span>
                            <span class="value">
                                <?php echo date('M d', strtotime($booking['checkInDate'])); ?> - 
                                <?php echo date('M d, Y', strtotime($booking['checkOutDate'])); ?>
                            </span>
                        </div>
                        <div class="summary-item">
                            <span class="label">Status:</span>
                            <span class="value status-completed">Completed</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Review Form -->
            <div class="col-lg-8">
                <div class="review-form-card">
                    <div class="form-header">
                        <div class="header-icon">
                            <i class="fas fa-star"></i>
                        </div>
                        <h4>Rate Your Experience</h4>
                        <p>Your honest feedback helps other pet owners make informed decisions</p>
                    </div>
                    
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger alert-modern">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <ul class="mb-0">
                                <?php foreach($errors as $error): ?>
                                    <li><?php echo $error; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . "?sitter_id=" . $sitter_id . "&booking_id=" . $booking_id); ?>" method="post" class="review-form">
                        <!-- Rating Section -->
                        <div class="rating-section">
                            <label class="section-label">Overall Rating <span class="required">*</span></label>
                            <div class="rating-interactive">
                                <div class="stars-container">
                                    <div class="rating-select">
                                        <i class="far fa-star" data-rating="1"></i>
                                        <i class="far fa-star" data-rating="2"></i>
                                        <i class="far fa-star" data-rating="3"></i>
                                        <i class="far fa-star" data-rating="4"></i>
                                        <i class="far fa-star" data-rating="5"></i>
                                    </div>
                                    <div class="rating-text">
                                        <span id="ratingText">Click to rate</span>
                                    </div>
                                </div>
                                <input type="hidden" id="rating-input" name="rating" value="">
                            </div>
                            
                            <div class="rating-guide">
                                <div class="guide-item">
                                    <div class="guide-stars">
                                        <i class="fas fa-star"></i>
                                    </div>
                                    <span>Poor</span>
                                </div>
                                <div class="guide-item">
                                    <div class="guide-stars">
                                        <i class="fas fa-star"></i>
                                        <i class="fas fa-star"></i>
                                    </div>
                                    <span>Fair</span>
                                </div>
                                <div class="guide-item">
                                    <div class="guide-stars">
                                        <i class="fas fa-star"></i>
                                        <i class="fas fa-star"></i>
                                        <i class="fas fa-star"></i>
                                    </div>
                                    <span>Good</span>
                                </div>
                                <div class="guide-item">
                                    <div class="guide-stars">
                                        <i class="fas fa-star"></i>
                                        <i class="fas fa-star"></i>
                                        <i class="fas fa-star"></i>
                                        <i class="fas fa-star"></i>
                                    </div>
                                    <span>Great</span>
                                </div>
                                <div class="guide-item">
                                    <div class="guide-stars">
                                        <i class="fas fa-star"></i>
                                        <i class="fas fa-star"></i>
                                        <i class="fas fa-star"></i>
                                        <i class="fas fa-star"></i>
                                        <i class="fas fa-star"></i>
                                    </div>
                                    <span>Excellent</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Written Review -->
                        <div class="review-text-section">
                            <label for="review" class="section-label">Written Review <span class="required">*</span></label>
                            <div class="textarea-container">
                                <textarea class="review-textarea" id="review" name="review" rows="6" placeholder="Share your experience with this pet sitter. What did you like? How was the communication? Would you recommend them to other pet owners?" required></textarea>
                                <div class="char-counter">
                                    <span id="charCount">0</span> / 500 characters
                                </div>
                            </div>
                            
                            <div class="review-tips">
                                <h6>💡 Tips for a helpful review:</h6>
                                <ul>
                                    <li>Mention specific services provided</li>
                                    <li>Comment on communication and reliability</li>
                                    <li>Share how your pet responded to the sitter</li>
                                    <li>Be honest and constructive</li>
                                </ul>
                            </div>
                        </div>
                        
                        <!-- Privacy Notice -->
                        <div class="privacy-notice">
                            <div class="notice-icon">
                                <i class="fas fa-info-circle"></i>
                            </div>
                            <div class="notice-content">
                                <h6>Review Guidelines</h6>
                                <p>Your review will be public and help other pet owners make informed decisions. Please be honest, respectful, and constructive in your feedback.</p>
                            </div>
                        </div>
                        
                        <!-- Submit Button -->
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary-gradient btn-lg submit-review-btn" disabled>
                                <i class="fas fa-paper-plane me-2"></i> Submit Review
                            </button>
                            <a href="bookings.php" class="btn btn-outline-secondary btn-lg">
                                <i class="fas fa-times me-2"></i> Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
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

.review-section-modern {
    padding: 80px 0;
    background: white;
}

.sitter-info-card {
    background: white;
    border-radius: 20px;
    padding: 32px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(0, 0, 0, 0.05);
    height: fit-content;
    position: sticky;
    top: 100px;
}

.sitter-avatar {
    position: relative;
    width: 120px;
    height: 120px;
    margin: 0 auto 20px;
}

.sitter-avatar img {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
    border: 4px solid #f1f5f9;
}

.avatar-placeholder {
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, #f1f5f9, #e2e8f0);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    color: #9ca3af;
}

.verified-badge {
    position: absolute;
    bottom: 0;
    right: 0;
    background: #10b981;
    color: white;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.7rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 4px;
}

.sitter-details {
    text-align: center;
    margin-bottom: 24px;
}

.sitter-name {
    font-size: 1.4rem;
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 4px;
}

.sitter-title {
    color: #64748b;
    margin: 0;
}

.booking-summary {
    background: #f8fafc;
    padding: 20px;
    border-radius: 12px;
}

.booking-summary h6 {
    color: #1e293b;
    font-weight: 600;
    margin-bottom: 16px;
}

.summary-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}

.summary-item:last-child {
    margin-bottom: 0;
}

.summary-item .label {
    color: #64748b;
    font-size: 0.9rem;
}

.summary-item .value {
    color: #1e293b;
    font-weight: 500;
}

.status-completed {
    background: #dcfce7;
    color: #166534;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 600;
}

.review-form-card {
    background: white;
    border-radius: 20px;
    padding: 40px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(0, 0, 0, 0.05);
}

.form-header {
    text-align: center;
    margin-bottom: 40px;
}

.header-icon {
    width: 80px;
    height: 80px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    color: white;
    margin: 0 auto 20px;
}

.form-header h4 {
    font-size: 1.8rem;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 8px;
}

.form-header p {
    color: #64748b;
    font-size: 1rem;
}

.alert-modern {
    border: none;
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 32px;
    border-left: 4px solid #ef4444;
}

.rating-section {
    margin-bottom: 32px;
}

.section-label {
    font-weight: 600;
    color: #374151;
    margin-bottom: 16px;
    display: block;
    font-size: 1.1rem;
}

.required {
    color: #ef4444;
}

.rating-interactive {
    background: #f8fafc;
    padding: 32px;
    border-radius: 16px;
    border: 2px solid #e2e8f0;
    text-align: center;
    margin-bottom: 20px;
}

.stars-container {
    margin-bottom: 16px;
}

.rating-select {
    display: flex;
    justify-content: center;
    gap: 8px;
    margin-bottom: 12px;
}

.rating-select i {
    font-size: 2.5rem;
    color: #d1d5db;
    cursor: pointer;
    transition: all 0.3s ease;
}

.rating-select i:hover,
.rating-select i.active {
    color: #fbbf24;
    transform: scale(1.1);
}

.rating-text {
    font-size: 1.1rem;
    font-weight: 600;
    color: #64748b;
}

.rating-guide {
    display: flex;
    justify-content: space-between;
    padding: 0 20px;
}

.guide-item {
    text-align: center;
    font-size: 0.8rem;
    color: #9ca3af;
}

.guide-stars {
    margin-bottom: 4px;
}

.guide-stars i {
    font-size: 0.7rem;
    color: #fbbf24;
}

.review-text-section {
    margin-bottom: 32px;
}

.textarea-container {
    position: relative;
}

.review-textarea {
    width: 100%;
    padding: 20px;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    font-size: 1rem;
    line-height: 1.6;
    transition: all 0.3s ease;
    resize: vertical;
    min-height: 150px;
}

.review-textarea:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    outline: none;
}

.char-counter {
    position: absolute;
    bottom: 12px;
    right: 16px;
    font-size: 0.8rem;
    color: #9ca3af;
    background: white;
    padding: 2px 6px;
    border-radius: 4px;
}

.review-tips {
    background: #f0f9ff;
    border: 1px solid #bae6fd;
    border-radius: 12px;
    padding: 20px;
    margin-top: 16px;
}

.review-tips h6 {
    color: #0369a1;
    margin-bottom: 12px;
    font-weight: 600;
}

.review-tips ul {
    margin: 0;
    padding-left: 20px;
    color: #0c4a6e;
}

.review-tips li {
    margin-bottom: 4px;
    font-size: 0.9rem;
}

.privacy-notice {
    background: #fef3c7;
    border: 1px solid #fcd34d;
    border-radius: 12px;
    padding: 20px;
    display: flex;
    gap: 16px;
    margin-bottom: 32px;
}

.notice-icon {
    color: #d97706;
    font-size: 1.2rem;
    flex-shrink: 0;
    margin-top: 2px;
}

.notice-content h6 {
    color: #92400e;
    font-weight: 600;
    margin-bottom: 8px;
}

.notice-content p {
    color: #a16207;
    margin: 0;
    font-size: 0.9rem;
    line-height: 1.5;
}

.form-actions {
    display: flex;
    gap: 16px;
    justify-content: center;
}

.submit-review-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

@media (max-width: 768px) {
    .page-title {
        font-size: 2.5rem;
    }
    
    .page-actions {
        text-align: left;
        margin-top: 24px;
    }
    
    .review-form-card {
        padding: 24px;
        margin-top: 24px;
    }
    
    .sitter-info-card {
        position: static;
        margin-bottom: 24px;
    }
    
    .rating-interactive {
        padding: 20px;
    }
    
    .rating-select i {
        font-size: 2rem;
    }
    
    .rating-guide {
        flex-wrap: wrap;
        gap: 8px;
        justify-content: center;
    }
    
    .form-actions {
        flex-direction: column;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const ratingStars = document.querySelectorAll('.rating-select i');
    const ratingInput = document.getElementById('rating-input');
    const ratingText = document.getElementById('ratingText');
    const reviewTextarea = document.getElementById('review');
    const charCount = document.getElementById('charCount');
    const submitBtn = document.querySelector('.submit-review-btn');
    
    const ratingTexts = [
        'Poor - Very disappointed',
        'Fair - Below expectations', 
        'Good - Satisfactory service',
        'Great - Exceeded expectations',
        'Excellent - Outstanding service!'
    ];
    
    // Rating interaction
    ratingStars.forEach((star, index) => {
        star.addEventListener('mouseover', function() {
            highlightStars(index + 1);
        });
        
        star.addEventListener('click', function() {
            const rating = index + 1;
            ratingInput.value = rating;
            ratingText.textContent = ratingTexts[index];
            ratingText.style.color = '#667eea';
            highlightStars(rating);
            validateForm();
        });
    });
    
    // Reset on mouse leave
    document.querySelector('.rating-select').addEventListener('mouseleave', function() {
        const currentRating = parseInt(ratingInput.value) || 0;
        highlightStars(currentRating);
    });
    
    function highlightStars(count) {
        ratingStars.forEach((star, index) => {
            if (index < count) {
                star.className = 'fas fa-star active';
            } else {
                star.className = 'far fa-star';
            }
        });
    }
    
    // Character counter
    reviewTextarea.addEventListener('input', function() {
        const length = this.value.length;
        charCount.textContent = length;
        
        if (length > 500) {
            charCount.style.color = '#ef4444';
            this.value = this.value.substring(0, 500);
            charCount.textContent = '500';
        } else {
            charCount.style.color = '#9ca3af';
        }
        
        validateForm();
    });
    
    function validateForm() {
        const hasRating = ratingInput.value !== '';
        const hasReview = reviewTextarea.value.trim().length > 0;
        
        submitBtn.disabled = !(hasRating && hasReview);
    }
    
    // Form submission
    document.querySelector('.review-form').addEventListener('submit', function(e) {
        if (!ratingInput.value || !reviewTextarea.value.trim()) {
            e.preventDefault();
            alert('Please provide both a rating and written review.');
        }
    });
});
</script>

<?php
// Include footer
include_once '../includes/footer.php';

// Close database connection
$conn->close();
?>