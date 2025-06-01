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

// Calculate average rating and statistics
$avg_rating = 0;
$review_count = count($reviews);
$rating_breakdown = [0, 0, 0, 0, 0]; // 1-star to 5-star count

if ($review_count > 0) {
    $total_rating = 0;
    foreach ($reviews as $review) {
        $total_rating += $review['rating'];
        $rating_breakdown[$review['rating'] - 1]++;
    }
    $avg_rating = round($total_rating / $review_count, 1);
}

// Include header
include_once '../includes/header.php';
?>

<!-- Modern Reviews Header -->
<section class="reviews-header-section">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <div class="reviews-header-content">
                    <span class="section-badge">⭐ Customer Feedback</span>
                    <h1 class="reviews-title">My <span class="text-gradient">Reviews</span></h1>
                    <p class="reviews-subtitle">Build trust and credibility by responding to client feedback professionally.</p>
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

<!-- Reviews Analytics -->
<section class="reviews-analytics-section">
    <div class="container">
        <div class="analytics-card">
            <div class="row">
                <!-- Overall Rating -->
                <div class="col-lg-4">
                    <div class="rating-overview">
                        <div class="rating-score">
                            <h1 class="score-number"><?php echo $avg_rating; ?></h1>
                            <div class="rating-stars">
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
                            <p class="rating-text">Based on <?php echo $review_count; ?> reviews</p>
                        </div>
                    </div>
                </div>
                
                <!-- Rating Breakdown -->
                <div class="col-lg-5">
                    <div class="rating-breakdown">
                        <h6 class="breakdown-title">Rating Distribution</h6>
                        <?php for ($i = 5; $i >= 1; $i--): ?>
                            <?php
                            $count = $rating_breakdown[$i - 1];
                            $percentage = $review_count > 0 ? ($count / $review_count) * 100 : 0;
                            ?>
                            <div class="breakdown-row">
                                <div class="star-label">
                                    <?php echo $i; ?> <i class="fas fa-star"></i>
                                </div>
                                <div class="progress-container">
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo $percentage; ?>%"></div>
                                    </div>
                                </div>
                                <div class="count-label"><?php echo $count; ?></div>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>
                
                <!-- Quick Stats -->
                <div class="col-lg-3">
                    <div class="quick-stats">
                        <div class="stat-item">
                            <div class="stat-icon positive">
                                <i class="fas fa-thumbs-up"></i>
                            </div>
                            <div class="stat-content">
                                <h4><?php echo array_sum(array_slice($rating_breakdown, 3)); ?></h4>
                                <p>Positive Reviews</p>
                            </div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-icon response">
                                <i class="fas fa-reply"></i>
                            </div>
                            <div class="stat-content">
                                <h4><?php echo count(array_filter($reviews, function($r) { return !empty($r['reply']); })); ?></h4>
                                <p>Responses Given</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Reviews Content -->
<section class="reviews-content-section">
    <div class="container">
        <?php if (empty($reviews)): ?>
            <div class="empty-reviews-state">
                <div class="empty-icon">
                    <i class="fas fa-star"></i>
                </div>
                <h3>No Reviews Yet</h3>
                <p>Your client reviews will appear here once you complete your first booking.</p>
                <div class="empty-actions">
                    <a href="bookings.php" class="btn btn-primary-gradient">
                        <i class="fas fa-calendar me-2"></i> View Bookings
                    </a>
                    <a href="services.php" class="btn btn-outline-primary">
                        <i class="fas fa-plus me-2"></i> Add Services
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="reviews-header-bar">
                <h3>Client Reviews <span class="review-count">(<?php echo $review_count; ?> reviews)</span></h3>
                <div class="filter-options">
                    <select class="form-select modern-select" id="reviewFilter">
                        <option value="all">All Reviews</option>
                        <option value="5">5 Star Reviews</option>
                        <option value="4">4 Star Reviews</option>
                        <option value="3">3 Star Reviews</option>
                        <option value="2">2 Star Reviews</option>
                        <option value="1">1 Star Reviews</option>
                        <option value="no-reply">Unanswered</option>
                        <option value="replied">Replied</option>
                    </select>
                </div>
            </div>
            
            <div class="reviews-list">
                <?php foreach ($reviews as $review): ?>
                    <div class="review-card-modern" data-rating="<?php echo $review['rating']; ?>" data-replied="<?php echo !empty($review['reply']) ? 'true' : 'false'; ?>">
                        <div class="review-header">
                            <div class="reviewer-info">
                                <div class="reviewer-avatar">
                                    <?php if (!empty($review['ownerImage'])): ?>
                                        <img src="../assets/images/users/<?php echo htmlspecialchars($review['ownerImage']); ?>" alt="<?php echo htmlspecialchars($review['ownerName']); ?>">
                                    <?php else: ?>
                                        <div class="avatar-placeholder">
                                            <i class="fas fa-user"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="reviewer-details">
                                    <h6><?php echo htmlspecialchars($review['ownerName']); ?></h6>
                                    <div class="review-meta">
                                        <span class="review-date"><?php echo date('M d, Y', strtotime($review['created_at'])); ?></span>
                                        <span class="booking-period">
                                            Booking: <?php echo date('M d', strtotime($review['checkInDate'])); ?> - <?php echo date('M d, Y', strtotime($review['checkOutDate'])); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="review-rating">
                                <div class="rating-stars">
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
                                <span class="rating-badge rating-<?php echo $review['rating']; ?>"><?php echo $review['rating']; ?>.0</span>
                            </div>
                        </div>
                        
                        <div class="review-content">
                            <p><?php echo nl2br(htmlspecialchars($review['review'])); ?></p>
                        </div>
                        
                        <?php if (!empty($review['reply'])): ?>
                            <div class="reply-section">
                                <div class="reply-header">
                                    <div class="reply-author">
                                        <i class="fas fa-reply me-2"></i>
                                        <strong>Your Response</strong>
                                    </div>
                                    <span class="reply-date"><?php echo date('M d, Y', strtotime($review['reply_date'])); ?></span>
                                </div>
                                <div class="reply-content">
                                    <p><?php echo nl2br(htmlspecialchars($review['reply'])); ?></p>
                                </div>
                                <div class="reply-actions">
                                    <button type="button" class="btn btn-outline-primary btn-sm edit-reply-btn" 
                                            data-review-id="<?php echo $review['reviewID']; ?>" 
                                            data-reply="<?php echo htmlspecialchars($review['reply']); ?>">
                                        <i class="fas fa-edit me-1"></i> Edit Response
                                    </button>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="no-reply-section">
                                <div class="no-reply-message">
                                    <i class="fas fa-comment-dots me-2"></i>
                                    <span>This review hasn't been responded to yet</span>
                                </div>
                                <button type="button" class="btn btn-primary-gradient btn-sm reply-btn" data-review-id="<?php echo $review['reviewID']; ?>">
                                    <i class="fas fa-reply me-1"></i> Respond to Review
                                </button>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Reply Form (Hidden by default) -->
                        <div class="reply-form-container" id="reply-form-<?php echo $review['reviewID']; ?>" style="display: none;">
                            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="reply-form">
                                <input type="hidden" name="review_id" value="<?php echo $review['reviewID']; ?>">
                                <div class="form-group">
                                    <label for="reply-<?php echo $review['reviewID']; ?>" class="form-label">Your Response</label>
                                    <textarea class="form-control modern-textarea" 
                                              id="reply-<?php echo $review['reviewID']; ?>" 
                                              name="reply" 
                                              rows="4" 
                                              placeholder="Write a professional response to this review..."
                                              required></textarea>
                                    <div class="form-hint">Your response will be public and visible to all potential clients.</div>
                                </div>
                                <div class="form-actions">
                                    <button type="button" class="btn btn-outline-secondary cancel-reply-btn" data-review-id="<?php echo $review['reviewID']; ?>">
                                        Cancel
                                    </button>
                                    <button type="submit" name="submit_reply" class="btn btn-primary-gradient">
                                        <i class="fas fa-paper-plane me-1"></i> Submit Response
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Response Guidelines -->
<section class="response-guidelines-section">
    <div class="container">
        <div class="guidelines-card">
            <div class="guidelines-header">
                <h4><i class="fas fa-lightbulb me-2"></i> How to Respond to Reviews</h4>
                <p>Professional responses help build trust and show that you value client feedback</p>
            </div>
            
            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="guideline-section do-section">
                        <h6><i class="fas fa-check-circle me-2"></i> Best Practices</h6>
                        <ul class="guideline-list">
                            <li>Thank the client for their feedback</li>
                            <li>Respond promptly (within 24-48 hours)</li>
                            <li>Address specific points mentioned</li>
                            <li>Keep responses concise and professional</li>
                            <li>Show appreciation for their business</li>
                            <li>Invite them to book again</li>
                            <li>Use a warm, friendly tone</li>
                        </ul>
                    </div>
                </div>
                
                <div class="col-lg-6">
                    <div class="guideline-section dont-section">
                        <h6><i class="fas fa-times-circle me-2"></i> Things to Avoid</h6>
                        <ul class="guideline-list">
                            <li>Getting defensive or argumentative</li>
                            <li>Making excuses or blaming the client</li>
                            <li>Writing overly long responses</li>
                            <li>Sharing personal or confidential details</li>
                            <li>Ignoring negative reviews</li>
                            <li>Using unprofessional language</li>
                            <li>Taking criticism personally</li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="example-responses">
                <h6>Example Responses</h6>
                <div class="row g-3">
                    <div class="col-lg-6">
                        <div class="example-response positive">
                            <div class="example-header">
                                <span class="example-type">For Positive Reviews</span>
                                <div class="example-rating">
                                    <i class="fas fa-star"></i>
                                    <i class="fas fa-star"></i>
                                    <i class="fas fa-star"></i>
                                    <i class="fas fa-star"></i>
                                    <i class="fas fa-star"></i>
                                </div>
                            </div>
                            <p>"Thank you so much for this wonderful review! I'm delighted that Max had such a great time and that you felt comfortable leaving him with me. It was a pleasure caring for such a well-behaved pup. I'd love to help out again anytime!"</p>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="example-response constructive">
                            <div class="example-header">
                                <span class="example-type">For Constructive Feedback</span>
                                <div class="example-rating">
                                    <i class="fas fa-star"></i>
                                    <i class="fas fa-star"></i>
                                    <i class="fas fa-star"></i>
                                    <i class="far fa-star"></i>
                                    <i class="far fa-star"></i>
                                </div>
                            </div>
                            <p>"Thank you for your feedback. I appreciate you bringing this to my attention and I'm sorry the experience wasn't perfect. I've taken your suggestions to heart and will ensure better communication in future bookings. I'd welcome the opportunity to provide better service next time."</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
.reviews-header-section {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 80px 0;
    position: relative;
    overflow: hidden;
}

.reviews-header-section::before {
    content: '';
    position: absolute;
    width: 100%;
    height: 100%;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="20" cy="20" r="2" fill="rgba(255,255,255,0.1)"/><circle cx="80" cy="40" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="40" cy="80" r="1.5" fill="rgba(255,255,255,0.1)"/></svg>');
    animation: float 20s infinite linear;
}

.reviews-title {
    font-size: 3.5rem;
    font-weight: 800;
    line-height: 1.2;
    margin-bottom: 24px;
}

.reviews-subtitle {
    font-size: 1.2rem;
    opacity: 0.9;
}

.reviews-analytics-section {
    padding: 0;
    margin-top: -40px;
    position: relative;
    z-index: 2;
}

.analytics-card {
    background: white;
    border-radius: 24px;
    padding: 48px;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
    border: 1px solid rgba(0, 0, 0, 0.05);
    margin: 0 15px;
}

.rating-overview {
    text-align: center;
    padding-right: 40px;
    border-right: 1px solid #f1f5f9;
}

.score-number {
    font-size: 4rem;
    font-weight: 800;
    color: #667eea;
    margin-bottom: 16px;
    line-height: 1;
}

.rating-stars {
    font-size: 1.5rem;
    color: #fbbf24;
    margin-bottom: 12px;
}

.rating-text {
    color: #64748b;
    font-size: 1.1rem;
    margin: 0;
}

.rating-breakdown {
    padding: 0 20px;
}

.breakdown-title {
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 24px;
}

.breakdown-row {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 12px;
}

.star-label {
    width: 60px;
    font-size: 0.9rem;
    color: #64748b;
    display: flex;
    align-items: center;
    gap: 4px;
}

.star-label i {
    color: #fbbf24;
    font-size: 0.8rem;
}

.progress-container {
    flex: 1;
}

.progress-bar {
    height: 8px;
    background: #f1f5f9;
    border-radius: 4px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #667eea, #764ba2);
    border-radius: 4px;
    transition: width 0.3s ease;
}

.count-label {
    width: 30px;
    text-align: right;
    font-size: 0.9rem;
    color: #64748b;
}

.quick-stats {
    display: flex;
    flex-direction: column;
    gap: 24px;
    padding-left: 20px;
}

.stat-item {
    display: flex;
    align-items: center;
    gap: 16px;
}

.stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    color: white;
}

.stat-icon.positive { background: linear-gradient(135deg, #10b981, #059669); }
.stat-icon.response { background: linear-gradient(135deg, #3b82f6, #1d4ed8); }

.stat-content h4 {
    font-size: 1.5rem;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 4px;
}

.stat-content p {
    color: #64748b;
    font-size: 0.9rem;
    margin: 0;
}

.reviews-content-section {
    padding: 80px 0;
    background: #f8f9ff;
}

.empty-reviews-state {
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

.empty-reviews-state h3 {
    font-size: 2rem;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 16px;
}

.empty-reviews-state p {
    font-size: 1.1rem;
    color: #64748b;
    margin-bottom: 32px;
}

.empty-actions {
    display: flex;
    gap: 16px;
    justify-content: center;
}

.reviews-header-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 32px;
    background: white;
    padding: 24px 32px;
    border-radius: 16px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
}

.reviews-header-bar h3 {
    font-size: 1.5rem;
    font-weight: 700;
    color: #1e293b;
    margin: 0;
}

.review-count {
    color: #64748b;
    font-weight: 500;
    font-size: 1rem;
}

.modern-select {
    padding: 12px 16px;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    transition: all 0.3s ease;
    min-width: 200px;
}

.modern-select:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.reviews-list {
    display: flex;
    flex-direction: column;
    gap: 24px;
}

.review-card-modern {
    background: white;
    border-radius: 20px;
    padding: 32px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(0, 0, 0, 0.05);
    transition: all 0.3s ease;
}

.review-card-modern:hover {
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
}

.review-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 20px;
}

.reviewer-info {
    display: flex;
    align-items: center;
    gap: 16px;
}

.reviewer-avatar {
    width: 60px;
    height: 60px;
    border-radius: 15px;
    overflow: hidden;
    background: linear-gradient(135deg, #f1f5f9, #e2e8f0);
    display: flex;
    align-items: center;
    justify-content: center;
}

.reviewer-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.avatar-placeholder {
    color: #9ca3af;
    font-size: 1.5rem;
}

.reviewer-details h6 {
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 4px;
}

.review-meta {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.review-date {
    color: #64748b;
    font-size: 0.9rem;
    font-weight: 500;
}

.booking-period {
    color: #9ca3af;
    font-size: 0.8rem;
}

.review-rating {
    text-align: right;
}

.review-rating .rating-stars {
    color: #fbbf24;
    font-size: 1.2rem;
    margin-bottom: 8px;
}

.rating-badge {
    padding: 6px 12px;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 600;
    color: white;
}

.rating-badge.rating-5 { background: linear-gradient(135deg, #10b981, #059669); }
.rating-badge.rating-4 { background: linear-gradient(135deg, #3b82f6, #1d4ed8); }
.rating-badge.rating-3 { background: linear-gradient(135deg, #f59e0b, #d97706); }
.rating-badge.rating-2 { background: linear-gradient(135deg, #ef4444, #dc2626); }
.rating-badge.rating-1 { background: linear-gradient(135deg, #991b1b, #7f1d1d); }

.review-content {
    margin-bottom: 24px;
}

.review-content p {
    color: #374151;
    line-height: 1.6;
    font-size: 1.05rem;
    margin: 0;
}

.reply-section {
    background: #f8f9ff;
    border-radius: 16px;
    padding: 24px;
    border-left: 4px solid #667eea;
}

.reply-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}

.reply-author {
    display: flex;
    align-items: center;
    color: #667eea;
    font-weight: 600;
}

.reply-date {
    color: #9ca3af;
    font-size: 0.8rem;
}

.reply-content p {
    color: #374151;
    line-height: 1.6;
    margin: 0;
    margin-bottom: 16px;
}

.reply-actions {
    text-align: right;
}

.no-reply-section {
    background: #fef3c7;
    border-radius: 16px;
    padding: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-left: 4px solid #f59e0b;
}

.no-reply-message {
    color: #92400e;
    font-weight: 500;
}

.reply-form-container {
    margin-top: 24px;
    padding-top: 24px;
    border-top: 1px solid #f1f5f9;
}

.reply-form .form-group {
    margin-bottom: 20px;
}

.form-label {
    font-weight: 600;
    color: #374151;
    margin-bottom: 8px;
    display: block;
}

.modern-textarea {
    width: 100%;
    padding: 16px;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    font-size: 1rem;
    transition: all 0.3s ease;
    resize: vertical;
}

.modern-textarea:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.form-hint {
    font-size: 0.85rem;
    color: #6b7280;
    margin-top: 6px;
}

.form-actions {
    display: flex;
    gap: 12px;
    justify-content: flex-end;
}

.response-guidelines-section {
    padding: 80px 0;
    background: white;
}

.guidelines-card {
    background: #f8f9ff;
    border-radius: 24px;
    padding: 48px;
    border: 1px solid rgba(0, 0, 0, 0.05);
}

.guidelines-header {
    text-align: center;
    margin-bottom: 48px;
}

.guidelines-header h4 {
    font-size: 2rem;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 12px;
}

.guidelines-header p {
    color: #64748b;
    font-size: 1.1rem;
}

.guideline-section {
    background: white;
    border-radius: 16px;
    padding: 32px;
    height: 100%;
}

.do-section {
    border-left: 4px solid #10b981;
}

.dont-section {
    border-left: 4px solid #ef4444;
}

.guideline-section h6 {
    font-weight: 600;
    margin-bottom: 20px;
    color: #1e293b;
}

.do-section h6 i {
    color: #10b981;
}

.dont-section h6 i {
    color: #ef4444;
}

.guideline-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.guideline-list li {
    padding: 8px 0;
    color: #374151;
    position: relative;
    padding-left: 24px;
}

.do-section .guideline-list li::before {
    content: '✓';
    position: absolute;
    left: 0;
    color: #10b981;
    font-weight: bold;
}

.dont-section .guideline-list li::before {
    content: '✗';
    position: absolute;
    left: 0;
    color: #ef4444;
    font-weight: bold;
}

.example-responses {
    margin-top: 40px;
    padding-top: 40px;
    border-top: 1px solid #e5e7eb;
}

.example-responses h6 {
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 24px;
    text-align: center;
}

.example-response {
    background: white;
    border-radius: 16px;
    padding: 24px;
    height: 100%;
}

.example-response.positive {
    border-left: 4px solid #10b981;
}

.example-response.constructive {
    border-left: 4px solid #f59e0b;
}

.example-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}

.example-type {
    font-weight: 600;
    color: #374151;
}

.example-rating {
    color: #fbbf24;
    font-size: 0.9rem;
}

.example-response p {
    color: #64748b;
    font-style: italic;
    line-height: 1.5;
    margin: 0;
}

@media (max-width: 991px) {
    .reviews-title {
        font-size: 2.5rem;
    }
    
    .analytics-card {
        padding: 32px 24px;
    }
    
    .rating-overview {
        padding-right: 0;
        border-right: none;
        border-bottom: 1px solid #f1f5f9;
        padding-bottom: 32px;
        margin-bottom: 32px;
    }
    
    .rating-breakdown {
        padding: 0;
    }
    
    .quick-stats {
        padding-left: 0;
        flex-direction: row;
        justify-content: space-around;
        margin-top: 32px;
    }
    
    .reviews-header-bar {
        flex-direction: column;
        gap: 16px;
        align-items: flex-start;
    }
}

@media (max-width: 768px) {
    .reviews-header-section {
        text-align: center;
    }
    
    .review-header {
        flex-direction: column;
        gap: 16px;
        align-items: flex-start;
    }
    
    .review-rating {
        text-align: left;
    }
    
    .no-reply-section {
        flex-direction: column;
        gap: 16px;
        align-items: flex-start;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .empty-actions {
        flex-direction: column;
        align-items: center;
    }
    
    .guidelines-card {
        padding: 32px 20px;
    }
    
    .quick-stats {
        flex-direction: column;
        gap: 16px;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Review filtering
    const reviewFilter = document.getElementById('reviewFilter');
    const reviewCards = document.querySelectorAll('.review-card-modern');
    
    if (reviewFilter) {
        reviewFilter.addEventListener('change', function() {
            const filterValue = this.value;
            
            reviewCards.forEach(card => {
                const rating = card.getAttribute('data-rating');
                const hasReply = card.getAttribute('data-replied') === 'true';
                
                let shouldShow = false;
                
                switch (filterValue) {
                    case 'all':
                        shouldShow = true;
                        break;
                    case 'no-reply':
                        shouldShow = !hasReply;
                        break;
                    case 'replied':
                        shouldShow = hasReply;
                        break;
                    default:
                        shouldShow = rating === filterValue;
                        break;
                }
                
                card.style.display = shouldShow ? 'block' : 'none';
            });
        });
    }
    
    // Reply form handling
    const replyButtons = document.querySelectorAll('.reply-btn');
    const editReplyButtons = document.querySelectorAll('.edit-reply-btn');
    const cancelButtons = document.querySelectorAll('.cancel-reply-btn');
    
    // Show reply form
    replyButtons.forEach(button => {
        button.addEventListener('click', function() {
            const reviewId = this.getAttribute('data-review-id');
            const form = document.getElementById(`reply-form-${reviewId}`);
            form.style.display = 'block';
            this.style.display = 'none';
        });
    });
    
    // Edit reply
    editReplyButtons.forEach(button => {
        button.addEventListener('click', function() {
            const reviewId = this.getAttribute('data-review-id');
            const reply = this.getAttribute('data-reply');
            const form = document.getElementById(`reply-form-${reviewId}`);
            const textarea = document.getElementById(`reply-${reviewId}`);
            
            textarea.value = reply;
            form.style.display = 'block';
            this.style.display = 'none';
        });
    });
    
    // Cancel reply
    cancelButtons.forEach(button => {
        button.addEventListener('click', function() {
            const reviewId = this.getAttribute('data-review-id');
            const form = document.getElementById(`reply-form-${reviewId}`);
            const replyBtn = document.querySelector(`.reply-btn[data-review-id="${reviewId}"]`);
            const editBtn = document.querySelector(`.edit-reply-btn[data-review-id="${reviewId}"]`);
            
            form.style.display = 'none';
            
            if (replyBtn) replyBtn.style.display = 'inline-block';
            if (editBtn) editBtn.style.display = 'inline-block';
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