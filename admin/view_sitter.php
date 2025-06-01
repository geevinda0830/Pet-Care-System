<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in as admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    $_SESSION['error_message'] = "You must be logged in as an administrator to access this page.";
    header("Location: ../login.php");
    exit();
}

// Check if sitter ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "Invalid pet sitter ID.";
    header("Location: users.php");
    exit();
}

// Include database connection
require_once '../config/db_connect.php';

$sitter_id = $_GET['id'];

// Get pet sitter details
$sitter_sql = "SELECT * FROM pet_sitter WHERE userID = ?";
$sitter_stmt = $conn->prepare($sitter_sql);
$sitter_stmt->bind_param("i", $sitter_id);
$sitter_stmt->execute();
$sitter_result = $sitter_stmt->get_result();

if ($sitter_result->num_rows === 0) {
    $_SESSION['error_message'] = "Pet sitter not found.";
    header("Location: users.php");
    exit();
}

$sitter = $sitter_result->fetch_assoc();
$sitter_stmt->close();

// Get sitter's services
$services_sql = "SELECT * FROM pet_service WHERE userID = ?";
$services_stmt = $conn->prepare($services_sql);
$services_stmt->bind_param("i", $sitter_id);
$services_stmt->execute();
$services_result = $services_stmt->get_result();
$services = [];

while ($row = $services_result->fetch_assoc()) {
    $services[] = $row;
}

$services_stmt->close();

// Get sitter's ratings and reviews
$reviews_sql = "SELECT r.*, po.fullName as reviewerName, po.image as reviewerImage, b.checkInDate, b.checkOutDate 
                FROM reviews r 
                JOIN pet_owner po ON r.userID = po.userID 
                JOIN booking b ON r.bookingID = b.bookingID
                WHERE r.sitterID = ?
                ORDER BY r.created_at DESC";
$reviews_stmt = $conn->prepare($reviews_sql);
$reviews_stmt->bind_param("i", $sitter_id);
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

// Get booking stats
$booking_sql = "SELECT 
                COUNT(*) as total_bookings,
                SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed_bookings,
                SUM(CASE WHEN status = 'Cancelled' THEN 1 ELSE 0 END) as cancelled_bookings,
                SUM(CASE WHEN status IN ('Pending', 'Confirmed') THEN 1 ELSE 0 END) as active_bookings
                FROM booking WHERE sitterID = ?";
$booking_stmt = $conn->prepare($booking_sql);
$booking_stmt->bind_param("i", $sitter_id);
$booking_stmt->execute();
$booking_stats = $booking_stmt->get_result()->fetch_assoc();
$booking_stmt->close();

// Check if form is submitted to update approval status
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_approval_status'])) {
    $approval_status = $_POST['approval_status'];
    
    // Update pet sitter approval status
    $update_sql = "UPDATE pet_sitter SET approval_status = ? WHERE userID = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("si", $approval_status, $sitter_id);
    
    if ($update_stmt->execute()) {
        $_SESSION['success_message'] = "Pet sitter approval status updated successfully.";
        // Update the sitter variable to reflect the change
        $sitter['approval_status'] = $approval_status;
    } else {
        $_SESSION['error_message'] = "Failed to update approval status: " . $conn->error;
    }
    
    $update_stmt->close();
}

// Include header
include_once '../includes/header.php';
?>

<!-- Modern Sitter Profile Styles -->
<style>
.sitter-profile-section {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    position: relative;
    overflow: hidden;
    padding: 80px 0 60px;
}

.profile-particles {
    position: absolute;
    width: 100%;
    height: 100%;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="20" cy="20" r="2" fill="rgba(255,255,255,0.1)"/><circle cx="80" cy="40" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="40" cy="80" r="1.5" fill="rgba(255,255,255,0.1)"/></svg>');
    animation: float 20s infinite linear;
}

@keyframes float {
    0% { transform: translateY(0px) rotate(0deg); }
    100% { transform: translateY(-100px) rotate(360deg); }
}

.profile-header-content {
    position: relative;
    z-index: 2;
}

.profile-badge {
    display: inline-flex;
    align-items: center;
    background: rgba(255, 255, 255, 0.2);
    padding: 8px 16px;
    border-radius: 50px;
    margin-bottom: 16px;
    backdrop-filter: blur(10px);
    font-size: 0.9rem;
    font-weight: 600;
}

.profile-title {
    font-size: 3rem;
    font-weight: 800;
    line-height: 1.2;
    margin-bottom: 16px;
}

.profile-subtitle {
    font-size: 1.2rem;
    opacity: 0.9;
    margin-bottom: 0;
}

.profile-actions {
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
}

.btn-glass {
    background: rgba(255, 255, 255, 0.2);
    border: 1px solid rgba(255, 255, 255, 0.3);
    color: white;
    padding: 12px 24px;
    border-radius: 50px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
    backdrop-filter: blur(10px);
}

.btn-glass:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: translateY(-2px);
    color: white;
}

.profile-content {
    padding: 80px 0;
    background: #f8f9ff;
    margin-top: -40px;
    position: relative;
    z-index: 3;
}

.approval-panel-modern {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    border-radius: 24px;
    padding: 32px;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.2);
    margin-bottom: 40px;
    position: relative;
    overflow: hidden;
}

.approval-panel-modern::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 4px;
    background: linear-gradient(90deg, #667eea, #764ba2);
}

.approval-status-card {
    background: white;
    border-radius: 20px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(0, 0, 0, 0.05);
    overflow: hidden;
}

.approval-status-header {
    background: linear-gradient(135deg, #f8f9ff, #f1f5f9);
    padding: 24px 32px;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
}

.approval-status-body {
    padding: 32px;
}

.status-indicator {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 24px;
}

.status-icon {
    width: 60px;
    height: 60px;
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
}

.status-approved { background: linear-gradient(135deg, #10b981, #059669); }
.status-pending { background: linear-gradient(135deg, #f59e0b, #d97706); }
.status-rejected { background: linear-gradient(135deg, #ef4444, #dc2626); }

.status-text h5 {
    margin: 0;
    font-weight: 700;
    color: #1e293b;
}

.status-text p {
    margin: 0;
    color: #64748b;
    font-size: 0.9rem;
}

.approval-form {
    display: flex;
    gap: 16px;
    align-items: center;
    flex-wrap: wrap;
}

.modern-select {
    padding: 12px 16px;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    transition: all 0.3s ease;
    background: rgba(255, 255, 255, 0.8);
    min-width: 150px;
}

.modern-select:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    background: white;
}

.btn-update {
    background: linear-gradient(135deg, #667eea, #764ba2);
    border: none;
    color: white;
    padding: 12px 24px;
    border-radius: 12px;
    font-weight: 600;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
}

.btn-update:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.6);
    color: white;
}

.quick-actions {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}

.action-btn-modern {
    background: linear-gradient(135deg, #f1f5f9, #e2e8f0);
    border: 1px solid #e5e7eb;
    color: #667eea;
    padding: 10px 20px;
    border-radius: 50px;
    text-decoration: none;
    font-weight: 600;
    font-size: 0.9rem;
    transition: all 0.3s ease;
}

.action-btn-modern:hover {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
}

.profile-card-modern {
    background: white;
    border-radius: 20px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(0, 0, 0, 0.05);
    overflow: hidden;
    height: 100%;
}

.profile-card-header {
    background: linear-gradient(135deg, #f8f9ff, #f1f5f9);
    padding: 24px 32px;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
}

.profile-card-body {
    padding: 32px;
}

.sitter-avatar {
    width: 120px;
    height: 120px;
    border-radius: 20px;
    object-fit: cover;
    margin-bottom: 24px;
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
}

.sitter-avatar-placeholder {
    width: 120px;
    height: 120px;
    background: linear-gradient(135deg, #f1f5f9, #e2e8f0);
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 3rem;
    color: #667eea;
    margin-bottom: 24px;
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
}

.sitter-name {
    font-size: 1.5rem;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 8px;
}

.sitter-username {
    color: #9ca3af;
    margin-bottom: 16px;
}

.rating-display {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 16px;
}

.stars {
    color: #fbbf24;
}

.rating-text {
    color: #64748b;
    font-weight: 500;
}

.service-badge {
    display: inline-block;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    padding: 6px 12px;
    border-radius: 50px;
    font-size: 0.8rem;
    font-weight: 600;
    margin-bottom: 16px;
}

.price-display {
    font-size: 1.3rem;
    font-weight: 700;
    color: #667eea;
    margin-bottom: 16px;
}

.contact-info {
    margin-top: 24px;
}

.contact-item {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 12px;
    color: #64748b;
}

.contact-item i {
    width: 20px;
    color: #667eea;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 16px;
    margin-top: 24px;
}

.stat-item {
    text-align: center;
    padding: 16px;
    background: linear-gradient(135deg, #f8f9ff, #f1f5f9);
    border-radius: 12px;
}

.stat-number {
    display: block;
    font-size: 1.5rem;
    font-weight: 700;
    color: #667eea;
}

.stat-label {
    font-size: 0.8rem;
    color: #64748b;
    margin-top: 4px;
}

.details-section {
    margin-bottom: 32px;
}

.section-title {
    font-size: 1.2rem;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.section-title i {
    color: #667eea;
}

.section-content {
    color: #64748b;
    line-height: 1.6;
}

.services-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
}

.service-card {
    background: white;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(0, 0, 0, 0.05);
    transition: all 0.3s ease;
}

.service-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
}

.service-image {
    height: 150px;
    overflow: hidden;
}

.service-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.service-info {
    padding: 20px;
}

.service-name {
    font-size: 1.1rem;
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 8px;
}

.service-type {
    display: inline-block;
    background: linear-gradient(135deg, #3b82f6, #2563eb);
    color: white;
    padding: 4px 8px;
    border-radius: 50px;
    font-size: 0.7rem;
    font-weight: 600;
    margin-bottom: 8px;
}

.service-description {
    color: #64748b;
    font-size: 0.9rem;
    margin-bottom: 12px;
    line-height: 1.4;
}

.service-price {
    font-size: 1.1rem;
    font-weight: 700;
    color: #667eea;
}

.reviews-section {
    max-height: 600px;
    overflow-y: auto;
}

.review-item {
    padding: 24px 0;
    border-bottom: 1px solid #f1f5f9;
}

.review-item:last-child {
    border-bottom: none;
}

.review-header {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 16px;
}

.reviewer-avatar {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    object-fit: cover;
}

.reviewer-avatar-placeholder {
    width: 50px;
    height: 50px;
    background: linear-gradient(135deg, #f1f5f9, #e2e8f0);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #667eea;
}

.reviewer-info h6 {
    margin: 0;
    font-weight: 600;
    color: #1e293b;
}

.review-rating {
    color: #fbbf24;
    margin: 4px 0;
}

.review-date {
    font-size: 0.8rem;
    color: #9ca3af;
}

.review-text {
    color: #64748b;
    line-height: 1.6;
    margin-bottom: 16px;
}

.review-reply {
    background: linear-gradient(135deg, #f8f9ff, #f1f5f9);
    padding: 16px;
    border-radius: 12px;
    margin-top: 12px;
}

.reply-header {
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 8px;
}

.reply-text {
    color: #64748b;
    margin: 0;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #9ca3af;
}

.empty-state i {
    font-size: 3rem;
    margin-bottom: 16px;
    color: #d1d5db;
}

.empty-state h4 {
    color: #374151;
    margin-bottom: 8px;
}

@media (max-width: 991px) {
    .profile-title {
        font-size: 2.5rem;
    }
    
    .profile-actions {
        justify-content: center;
        margin-top: 20px;
    }
    
    .approval-form {
        flex-direction: column;
        align-items: stretch;
    }
    
    .quick-actions {
        justify-content: center;
    }
    
    .stats-grid {
        grid-template-columns: repeat(4, 1fr);
    }
}

@media (max-width: 768px) {
    .approval-panel-modern {
        padding: 24px;
    }
    
    .profile-card-body {
        padding: 24px;
    }
    
    .services-grid {
        grid-template-columns: 1fr;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>

<!-- Modern Sitter Profile Header -->
<section class="sitter-profile-section">
    <div class="profile-particles"></div>
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <div class="profile-header-content">
                    <div class="profile-badge">
                        <i class="fas fa-user-check me-2"></i>
                        Pet Sitter Profile
                    </div>
                    <h1 class="profile-title"><?php echo htmlspecialchars($sitter['fullName']); ?></h1>
                    <p class="profile-subtitle">Comprehensive profile management and approval controls for pet sitter applications.</p>
                </div>
            </div>
            <div class="col-lg-4 text-lg-end">
                <div class="profile-actions">
                    <a href="users.php" class="btn btn-glass">
                        <i class="fas fa-arrow-left me-2"></i>Back to Users
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Modern Profile Content -->
<section class="profile-content">
    <div class="container">
        <!-- Approval Status Panel -->
        <div class="approval-panel-modern">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <div class="status-indicator">
                        <div class="status-icon status-<?php echo strtolower($sitter['approval_status']); ?>">
                            <?php
                            switch($sitter['approval_status']) {
                                case 'Approved':
                                    echo '<i class="fas fa-check"></i>';
                                    break;
                                case 'Pending':
                                    echo '<i class="fas fa-clock"></i>';
                                    break;
                                case 'Rejected':
                                    echo '<i class="fas fa-times"></i>';
                                    break;
                            }
                            ?>
                        </div>
                        <div class="status-text">
                            <h5>Account Status: <?php echo $sitter['approval_status']; ?></h5>
                            <p>
                                <?php
                                switch($sitter['approval_status']) {
                                    case 'Approved':
                                        echo 'This pet sitter is verified and can accept bookings.';
                                        break;
                                    case 'Pending':
                                        echo 'Application is awaiting admin review and approval.';
                                        break;
                                    case 'Rejected':
                                        echo 'Application has been rejected and requires review.';
                                        break;
                                }
                                ?>
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-6">
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . "?id=" . $sitter_id); ?>" method="post" class="approval-form">
                        <select class="form-select modern-select" name="approval_status">
                            <option value="Approved" <?php echo ($sitter['approval_status'] === 'Approved') ? 'selected' : ''; ?>>Approved</option>
                            <option value="Pending" <?php echo ($sitter['approval_status'] === 'Pending') ? 'selected' : ''; ?>>Pending</option>
                            <option value="Rejected" <?php echo ($sitter['approval_status'] === 'Rejected') ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                        <button type="submit" name="update_approval_status" class="btn btn-update">
                            <i class="fas fa-save me-2"></i>Update Status
                        </button>
                    </form>
                </div>
            </div>
            
            <div class="quick-actions mt-3">
                <a href="mailto:<?php echo $sitter['email']; ?>" class="action-btn-modern">
                    <i class="fas fa-envelope me-1"></i> Email Sitter
                </a>
                <a href="edit_sitter.php?id=<?php echo $sitter_id; ?>" class="action-btn-modern">
                    <i class="fas fa-edit me-1"></i> Edit Profile
                </a>
                <a href="sitter_bookings.php?sitter_id=<?php echo $sitter_id; ?>" class="action-btn-modern">
                    <i class="fas fa-calendar-alt me-1"></i> View Bookings
                </a>
            </div>
        </div>
        
        <div class="row g-4">
            <!-- Sitter Information -->
            <div class="col-lg-4">
                <div class="profile-card-modern">
                    <div class="profile-card-header">
                        <h5 class="mb-0">Sitter Information</h5>
                    </div>
                    <div class="profile-card-body text-center">
                        <?php if (!empty($sitter['image'])): ?>
                            <img src="../assets/images/pet_sitters/<?php echo htmlspecialchars($sitter['image']); ?>" class="sitter-avatar" alt="<?php echo htmlspecialchars($sitter['fullName']); ?>">
                        <?php else: ?>
                            <div class="sitter-avatar-placeholder">
                                <i class="fas fa-user"></i>
                            </div>
                        <?php endif; ?>
                        
                        <h3 class="sitter-name"><?php echo htmlspecialchars($sitter['fullName']); ?></h3>
                        <p class="sitter-username">@<?php echo htmlspecialchars($sitter['username']); ?></p>
                        
                        <div class="rating-display">
                            <div class="stars">
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
                            <span class="rating-text"><?php echo $avg_rating; ?> (<?php echo $review_count; ?> reviews)</span>
                        </div>
                        
                        <div class="service-badge"><?php echo htmlspecialchars($sitter['service']); ?></div>
                        
                        <div class="price-display">Rs. <?php echo number_format($sitter['price'], 2); ?> / hour</div>
                        
                        <div class="contact-info">
                            <div class="contact-item">
                                <i class="fas fa-envelope"></i>
                                <span><?php echo htmlspecialchars($sitter['email']); ?></span>
                            </div>
                            <div class="contact-item">
                                <i class="fas fa-phone"></i>
                                <span><?php echo htmlspecialchars($sitter['contact']); ?></span>
                            </div>
                            <div class="contact-item">
                                <i class="fas fa-map-marker-alt"></i>
                                <span><?php echo htmlspecialchars($sitter['address']); ?></span>
                            </div>
                            <div class="contact-item">
                                <i class="fas fa-calendar-plus"></i>
                                <span>Joined <?php echo date('M d, Y', strtotime($sitter['created_at'])); ?></span>
                            </div>
                        </div>
                        
                        <div class="stats-grid">
                            <div class="stat-item">
                                <span class="stat-number"><?php echo $booking_stats['total_bookings']; ?></span>
                                <div class="stat-label">Total Bookings</div>
                            </div>
                            <div class="stat-item">
                                <span class="stat-number"><?php echo $booking_stats['completed_bookings']; ?></span>
                                <div class="stat-label">Completed</div>
                            </div>
                            <div class="stat-item">
                                <span class="stat-number"><?php echo $booking_stats['active_bookings']; ?></span>
                                <div class="stat-label">Active</div>
                            </div>
                            <div class="stat-item">
                                <span class="stat-number"><?php echo $booking_stats['cancelled_bookings']; ?></span>
                                <div class="stat-label">Cancelled</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Sitter Details -->
            <div class="col-lg-8">
                <div class="profile-card-modern">
                    <div class="profile-card-header">
                        <h5 class="mb-0">Profile Details</h5>
                    </div>
                    <div class="profile-card-body">
                        <!-- Experience Section -->
                        <?php if (!empty($sitter['experience'])): ?>
                            <div class="details-section">
                                <div class="section-title">
                                    <i class="fas fa-briefcase"></i>
                                    Experience
                                </div>
                                <div class="section-content">
                                    <?php echo nl2br(htmlspecialchars($sitter['experience'])); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Qualifications Section -->
                        <?php if (!empty($sitter['qualifications'])): ?>
                            <div class="details-section">
                                <div class="section-title">
                                    <i class="fas fa-certificate"></i>
                                    Qualifications
                                </div>
                                <div class="section-content">
                                    <?php echo nl2br(htmlspecialchars($sitter['qualifications'])); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Specialization Section -->
                        <?php if (!empty($sitter['specialization'])): ?>
                            <div class="details-section">
                                <div class="section-title">
                                    <i class="fas fa-paw"></i>
                                    Specialization
                                </div>
                                <div class="section-content">
                                    <?php echo nl2br(htmlspecialchars($sitter['specialization'])); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Services Offered -->
                <?php if (!empty($services)): ?>
                    <div class="profile-card-modern mt-4">
                        <div class="profile-card-header">
                            <h5 class="mb-0">Services Offered (<?php echo count($services); ?>)</h5>
                        </div>
                        <div class="profile-card-body">
                            <div class="services-grid">
                                <?php foreach ($services as $service): ?>
                                    <div class="service-card">
                                        <?php if (!empty($service['image'])): ?>
                                            <div class="service-image">
                                                <img src="../assets/images/services/<?php echo htmlspecialchars($service['image']); ?>" alt="<?php echo htmlspecialchars($service['name']); ?>">
                                            </div>
                                        <?php endif; ?>
                                        <div class="service-info">
                                            <div class="service-name"><?php echo htmlspecialchars($service['name']); ?></div>
                                            <div class="service-type"><?php echo htmlspecialchars($service['type']); ?></div>
                                            <div class="service-description"><?php echo substr(htmlspecialchars($service['description']), 0, 100) . '...'; ?></div>
                                            <div class="service-price">Rs. <?php echo number_format($service['price'], 2); ?> / hour</div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Reviews Section -->
                <div class="profile-card-modern mt-4">
                    <div class="profile-card-header">
                        <h5 class="mb-0">Reviews (<?php echo count($reviews); ?>)</h5>
                    </div>
                    <div class="profile-card-body">
                        <?php if (empty($reviews)): ?>
                            <div class="empty-state">
                                <i class="fas fa-star"></i>
                                <h4>No Reviews Yet</h4>
                                <p>This pet sitter hasn't received any reviews from customers yet.</p>
                            </div>
                        <?php else: ?>
                            <div class="reviews-section">
                                <?php foreach ($reviews as $review): ?>
                                    <div class="review-item">
                                        <div class="review-header">
                                            <div>
                                                <?php if (!empty($review['reviewerImage'])): ?>
                                                    <img src="../assets/images/users/<?php echo htmlspecialchars($review['reviewerImage']); ?>" class="reviewer-avatar" alt="<?php echo htmlspecialchars($review['reviewerName']); ?>">
                                                <?php else: ?>
                                                    <div class="reviewer-avatar-placeholder">
                                                        <i class="fas fa-user"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="reviewer-info">
                                                <h6><?php echo htmlspecialchars($review['reviewerName']); ?></h6>
                                                <div class="review-rating">
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
                                                <div class="review-date">
                                                    <?php echo date('M d, Y', strtotime($review['created_at'])); ?> | 
                                                    Booking: <?php echo date('M d', strtotime($review['checkInDate'])); ?> - <?php echo date('M d, Y', strtotime($review['checkOutDate'])); ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="review-text">
                                            <?php echo nl2br(htmlspecialchars($review['review'])); ?>
                                        </div>
                                        
                                        <?php if (!empty($review['reply'])): ?>
                                            <div class="review-reply">
                                                <div class="reply-header">Response from <?php echo htmlspecialchars($sitter['fullName']); ?></div>
                                                <div class="reply-text"><?php echo nl2br(htmlspecialchars($review['reply'])); ?></div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
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