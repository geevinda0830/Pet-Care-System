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

// Get pet sitter information
$user_sql = "SELECT * FROM pet_sitter WHERE userID = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user = $user_result->fetch_assoc();
$user_stmt->close();

// Get active bookings count
$active_bookings_sql = "SELECT COUNT(*) as count FROM booking WHERE sitterID = ? AND status IN ('Pending', 'Confirmed')";
$active_bookings_stmt = $conn->prepare($active_bookings_sql);
$active_bookings_stmt->bind_param("i", $user_id);
$active_bookings_stmt->execute();
$active_bookings_result = $active_bookings_stmt->get_result();
$active_bookings_count = $active_bookings_result->fetch_assoc()['count'];
$active_bookings_stmt->close();

// Get completed bookings count
$completed_bookings_sql = "SELECT COUNT(*) as count FROM booking WHERE sitterID = ? AND status = 'Completed'";
$completed_bookings_stmt = $conn->prepare($completed_bookings_sql);
$completed_bookings_stmt->bind_param("i", $user_id);
$completed_bookings_stmt->execute();
$completed_bookings_result = $completed_bookings_stmt->get_result();
$completed_bookings_count = $completed_bookings_result->fetch_assoc()['count'];
$completed_bookings_stmt->close();

// Get average rating
$rating_sql = "SELECT AVG(r.rating) as avg_rating, COUNT(r.reviewID) as review_count 
               FROM reviews r 
               WHERE r.sitterID = ?";
$rating_stmt = $conn->prepare($rating_sql);
$rating_stmt->bind_param("i", $user_id);
$rating_stmt->execute();
$rating_result = $rating_stmt->get_result();
$rating_data = $rating_result->fetch_assoc();
$avg_rating = $rating_data['avg_rating'] ? round($rating_data['avg_rating'], 1) : 0;
$review_count = $rating_data['review_count'];
$rating_stmt->close();

// Get services count
$services_sql = "SELECT COUNT(*) as count FROM pet_service WHERE userID = ?";
$services_stmt = $conn->prepare($services_sql);
$services_stmt->bind_param("i", $user_id);
$services_stmt->execute();
$services_result = $services_stmt->get_result();
$services_count = $services_result->fetch_assoc()['count'];
$services_stmt->close();

// Get recent bookings
$recent_bookings_sql = "SELECT b.*, po.fullName as ownerName, p.petName, p.type as petType
                        FROM booking b 
                        JOIN pet_owner po ON b.userID = po.userID
                        JOIN pet_profile p ON b.petID = p.petID
                        WHERE b.sitterID = ? 
                        ORDER BY b.created_at DESC LIMIT 5";
$recent_bookings_stmt = $conn->prepare($recent_bookings_sql);
$recent_bookings_stmt->bind_param("i", $user_id);
$recent_bookings_stmt->execute();
$recent_bookings_result = $recent_bookings_stmt->get_result();
$recent_bookings = [];
while ($row = $recent_bookings_result->fetch_assoc()) {
    $recent_bookings[] = $row;
}
$recent_bookings_stmt->close();

// Get upcoming bookings
$upcoming_bookings_sql = "SELECT b.*, po.fullName as ownerName, p.petName, p.type as petType
                         FROM booking b 
                         JOIN pet_owner po ON b.userID = po.userID
                         JOIN pet_profile p ON b.petID = p.petID
                         WHERE b.sitterID = ? 
                         AND b.status = 'Confirmed'
                         AND b.checkInDate >= CURDATE()
                         ORDER BY b.checkInDate ASC, b.checkInTime ASC LIMIT 5";
$upcoming_bookings_stmt = $conn->prepare($upcoming_bookings_sql);
$upcoming_bookings_stmt->bind_param("i", $user_id);
$upcoming_bookings_stmt->execute();
$upcoming_bookings_result = $upcoming_bookings_stmt->get_result();
$upcoming_bookings = [];
while ($row = $upcoming_bookings_result->fetch_assoc()) {
    $upcoming_bookings[] = $row;
}
$upcoming_bookings_stmt->close();

// Include header
include_once '../includes/header.php';
?>

<!-- Modern Dashboard Header -->
<section class="dashboard-header-section">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <div class="dashboard-welcome">
                    <span class="welcome-badge">🎉 Welcome Back!</span>
                    <h1 class="dashboard-title">Hello, <span class="text-gradient"><?php echo htmlspecialchars($user['fullName']); ?></span></h1>
                    <p class="dashboard-subtitle">Manage your pet sitting services and grow your business with our platform.</p>
                </div>
            </div>
            <div class="col-lg-4 text-lg-end">
                <a href="profile.php" class="btn btn-primary-gradient btn-lg">
                    <i class="fas fa-user-edit me-2"></i> Edit Profile
                </a>
            </div>
        </div>
    </div>
</section>

<!-- Modern Statistics Cards -->
<section class="dashboard-stats-section">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-3 col-md-6">
                <div class="stat-card-modern primary">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $active_bookings_count; ?></h3>
                        <p>Active Bookings</p>
                        <div class="stat-trend">
                            <i class="fas fa-arrow-up"></i>
                            <span>+12% this month</span>
                        </div>
                    </div>
                    <a href="bookings.php?status=active" class="stat-link"></a>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <div class="stat-card-modern success">
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $completed_bookings_count; ?></h3>
                        <p>Completed Jobs</p>
                        <div class="stat-trend">
                            <i class="fas fa-arrow-up"></i>
                            <span>+8% this month</span>
                        </div>
                    </div>
                    <a href="bookings.php?status=completed" class="stat-link"></a>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <div class="stat-card-modern warning">
                    <div class="stat-icon">
                        <i class="fas fa-star"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $avg_rating > 0 ? $avg_rating : 'N/A'; ?></h3>
                        <p>Average Rating</p>
                        <div class="stat-trend">
                            <span><?php echo $review_count; ?> reviews</span>
                        </div>
                    </div>
                    <a href="reviews.php" class="stat-link"></a>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <div class="stat-card-modern info">
                    <div class="stat-icon">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $services_count; ?></h3>
                        <p>Services Offered</p>
                        <div class="stat-trend">
                            <i class="fas fa-plus"></i>
                            <span>Add more</span>
                        </div>
                    </div>
                    <a href="services.php" class="stat-link"></a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Dashboard Content -->
<section class="dashboard-content-section">
    <div class="container">
        <div class="row g-4">
            <!-- Upcoming Bookings -->
            <div class="col-lg-6">
                <div class="dashboard-card">
                    <div class="card-header-modern">
                        <h5 class="card-title-modern">
                            <i class="fas fa-calendar-alt me-2"></i>
                            Upcoming Bookings
                        </h5>
                        <a href="bookings.php" class="btn btn-outline-primary btn-sm">View All</a>
                    </div>
                    <div class="card-body-modern">
                        <?php if (empty($upcoming_bookings)): ?>
                            <div class="empty-state">
                                <i class="fas fa-calendar-plus"></i>
                                <h6>No upcoming bookings</h6>
                                <p>Your next confirmed bookings will appear here.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($upcoming_bookings as $booking): ?>
                                <div class="booking-item-modern">
                                    <div class="booking-avatar">
                                        <div class="pet-initial"><?php echo strtoupper(substr($booking['petName'], 0, 1)); ?></div>
                                    </div>
                                    <div class="booking-details">
                                        <h6><?php echo htmlspecialchars($booking['petName']); ?></h6>
                                        <p class="booking-owner"><?php echo htmlspecialchars($booking['ownerName']); ?></p>
                                        <div class="booking-date">
                                            <i class="fas fa-clock me-1"></i>
                                            <?php echo date('M d', strtotime($booking['checkInDate'])); ?> at 
                                            <?php echo date('h:i A', strtotime($booking['checkInTime'])); ?>
                                        </div>
                                    </div>
                                    <div class="booking-status">
                                        <span class="status-badge confirmed">Confirmed</span>
                                        <div class="booking-countdown">
                                            <?php 
                                            $now = new DateTime();
                                            $checkIn = new DateTime($booking['checkInDate'] . ' ' . $booking['checkInTime']);
                                            $interval = $now->diff($checkIn);
                                            
                                            if ($interval->days == 0) {
                                                echo 'Today';
                                            } elseif ($interval->days == 1) {
                                                echo 'Tomorrow';
                                            } else {
                                                echo 'In ' . $interval->days . ' days';
                                            }
                                            ?>
                                        </div>
                                    </div>
                                    <a href="booking_details.php?id=<?php echo $booking['bookingID']; ?>" class="booking-link"></a>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Recent Activity -->
            <div class="col-lg-6">
                <div class="dashboard-card">
                    <div class="card-header-modern">
                        <h5 class="card-title-modern">
                            <i class="fas fa-history me-2"></i>
                            Recent Activity
                        </h5>
                        <a href="bookings.php" class="btn btn-outline-primary btn-sm">View All</a>
                    </div>
                    <div class="card-body-modern">
                        <?php if (empty($recent_bookings)): ?>
                            <div class="empty-state">
                                <i class="fas fa-history"></i>
                                <h6>No recent activity</h6>
                                <p>Your booking history will appear here.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($recent_bookings as $booking): ?>
                                <div class="activity-item-modern">
                                    <div class="activity-icon <?php echo strtolower($booking['status']); ?>">
                                        <?php
                                        switch ($booking['status']) {
                                            case 'Pending':
                                                echo '<i class="fas fa-clock"></i>';
                                                break;
                                            case 'Confirmed':
                                                echo '<i class="fas fa-check"></i>';
                                                break;
                                            case 'Completed':
                                                echo '<i class="fas fa-check-circle"></i>';
                                                break;
                                            case 'Cancelled':
                                                echo '<i class="fas fa-times"></i>';
                                                break;
                                        }
                                        ?>
                                    </div>
                                    <div class="activity-content">
                                        <h6>Booking for <?php echo htmlspecialchars($booking['petName']); ?></h6>
                                        <p>Owner: <?php echo htmlspecialchars($booking['ownerName']); ?></p>
                                        <div class="activity-meta">
                                            <span class="status-badge <?php echo strtolower($booking['status']); ?>">
                                                <?php echo $booking['status']; ?>
                                            </span>
                                            <span class="activity-date">
                                                <?php echo date('M d, Y', strtotime($booking['created_at'])); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <a href="booking_details.php?id=<?php echo $booking['bookingID']; ?>" class="activity-link"></a>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Profile Completion -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="profile-completion-card">
                    <?php
                    // Calculate profile completion percentage
                    $total_fields = 9;
                    $filled_fields = 0;
                    
                    if (!empty($user['fullName'])) $filled_fields++;
                    if (!empty($user['email'])) $filled_fields++;
                    if (!empty($user['contact'])) $filled_fields++;
                    if (!empty($user['address'])) $filled_fields++;
                    if (!empty($user['gender'])) $filled_fields++;
                    if (!empty($user['service'])) $filled_fields++;
                    if (!empty($user['qualifications'])) $filled_fields++;
                    if (!empty($user['experience'])) $filled_fields++;
                    if (!empty($user['specialization'])) $filled_fields++;
                    
                    $completion_percentage = round(($filled_fields / $total_fields) * 100);
                    ?>
                    
                    <div class="profile-completion-content">
                        <div class="profile-avatar">
                            <?php if (!empty($user['image'])): ?>
                                <img src="../assets/images/pet_sitters/<?php echo htmlspecialchars($user['image']); ?>" alt="<?php echo htmlspecialchars($user['fullName']); ?>">
                            <?php else: ?>
                                <div class="avatar-placeholder">
                                    <i class="fas fa-user"></i>
                                </div>
                            <?php endif; ?>
                            <div class="completion-badge">
                                <?php echo $completion_percentage; ?>%
                            </div>
                        </div>
                        
                        <div class="completion-details">
                            <h5>Profile Completion</h5>
                            <div class="progress-container">
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?php echo $completion_percentage; ?>%"></div>
                                </div>
                                <span class="progress-text"><?php echo $completion_percentage; ?>% Complete</span>
                            </div>
                            <?php if ($completion_percentage < 100): ?>
                                <p>Complete your profile to attract more pet owners and increase bookings.</p>
                            <?php else: ?>
                                <p class="text-success">Excellent! Your profile is complete and attractive to pet owners.</p>
                            <?php endif; ?>
                        </div>
                        
                        <div class="completion-actions">
                            <a href="profile.php" class="btn btn-primary-gradient">
                                <i class="fas fa-edit me-2"></i>
                                <?php echo $completion_percentage < 100 ? 'Complete Profile' : 'Edit Profile'; ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="dashboard-card">
                    <div class="card-header-modern">
                        <h5 class="card-title-modern">
                            <i class="fas fa-rocket me-2"></i>
                            Quick Actions
                        </h5>
                    </div>
                    <div class="card-body-modern">
                        <div class="row g-3">
                            <div class="col-lg-3 col-md-6">
                                <a href="bookings.php" class="quick-action-btn">
                                    <div class="action-icon primary">
                                        <i class="fas fa-calendar-alt"></i>
                                    </div>
                                    <span>Manage Bookings</span>
                                </a>
                            </div>
                            <div class="col-lg-3 col-md-6">
                                <a href="services.php" class="quick-action-btn">
                                    <div class="action-icon success">
                                        <i class="fas fa-clipboard-list"></i>
                                    </div>
                                    <span>Manage Services</span>
                                </a>
                            </div>
                            <div class="col-lg-3 col-md-6">
                                <a href="reviews.php" class="quick-action-btn">
                                    <div class="action-icon warning">
                                        <i class="fas fa-star"></i>
                                    </div>
                                    <span>View Reviews</span>
                                </a>
                            </div>
                            <div class="col-lg-3 col-md-6">
                                <a href="../contact.php" class="quick-action-btn">
                                    <div class="action-icon info">
                                        <i class="fas fa-question-circle"></i>
                                    </div>
                                    <span>Get Help</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
.dashboard-header-section {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 80px 0 40px;
    position: relative;
    overflow: hidden;
}

.dashboard-header-section::before {
    content: '';
    position: absolute;
    width: 100%;
    height: 100%;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="20" cy="20" r="2" fill="rgba(255,255,255,0.1)"/><circle cx="80" cy="40" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="40" cy="80" r="1.5" fill="rgba(255,255,255,0.1)"/></svg>');
    animation: float 20s infinite linear;
}

.welcome-badge {
    display: inline-block;
    background: rgba(255, 255, 255, 0.2);
    padding: 8px 16px;
    border-radius: 50px;
    font-size: 0.9rem;
    font-weight: 600;
    margin-bottom: 16px;
    backdrop-filter: blur(10px);
}

.dashboard-title {
    font-size: 3rem;
    font-weight: 800;
    margin-bottom: 16px;
    line-height: 1.2;
}

.dashboard-subtitle {
    font-size: 1.2rem;
    opacity: 0.9;
    margin-bottom: 0;
}

.dashboard-stats-section {
    padding: 0;
    margin-top: -40px;
    position: relative;
    z-index: 2;
}

.stat-card-modern {
    background: white;
    border-radius: 20px;
    padding: 32px 24px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    border: 1px solid rgba(0, 0, 0, 0.05);
    position: relative;
    overflow: hidden;
    transition: all 0.3s ease;
    cursor: pointer;
}

.stat-card-modern:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
}

.stat-card-modern::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 4px;
}

.stat-card-modern.primary::before { background: linear-gradient(90deg, #667eea, #764ba2); }
.stat-card-modern.success::before { background: linear-gradient(90deg, #10b981, #059669); }
.stat-card-modern.warning::before { background: linear-gradient(90deg, #f59e0b, #d97706); }
.stat-card-modern.info::before { background: linear-gradient(90deg, #3b82f6, #1d4ed8); }

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
    margin-bottom: 20px;
}

.stat-card-modern.primary .stat-icon { background: linear-gradient(135deg, #667eea, #764ba2); }
.stat-card-modern.success .stat-icon { background: linear-gradient(135deg, #10b981, #059669); }
.stat-card-modern.warning .stat-icon { background: linear-gradient(135deg, #f59e0b, #d97706); }
.stat-card-modern.info .stat-icon { background: linear-gradient(135deg, #3b82f6, #1d4ed8); }

.stat-content h3 {
    font-size: 2.5rem;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 8px;
}

.stat-content p {
    color: #64748b;
    font-weight: 500;
    margin-bottom: 12px;
}

.stat-trend {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.85rem;
    color: #10b981;
}

.stat-link {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 2;
}

.dashboard-content-section {
    padding: 60px 0;
}

.dashboard-card {
    background: white;
    border-radius: 20px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(0, 0, 0, 0.05);
    overflow: hidden;
    height: 100%;
}

.card-header-modern {
    padding: 24px 32px;
    border-bottom: 1px solid #f1f5f9;
    display: flex;
    justify-content: between;
    align-items: center;
    background: #f8f9ff;
}

.card-title-modern {
    font-size: 1.2rem;
    font-weight: 600;
    color: #1e293b;
    margin: 0;
}

.card-body-modern {
    padding: 32px;
}

.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: #64748b;
}

.empty-state i {
    font-size: 3rem;
    margin-bottom: 16px;
    color: #cbd5e1;
}

.booking-item-modern {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 16px;
    border-radius: 12px;
    background: #f8f9ff;
    margin-bottom: 16px;
    position: relative;
    transition: all 0.3s ease;
    cursor: pointer;
}

.booking-item-modern:hover {
    background: #f1f5f9;
    transform: translateX(5px);
}

.booking-avatar {
    position: relative;
}

.pet-initial {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 1.2rem;
}

.booking-details {
    flex: 1;
}

.booking-details h6 {
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 4px;
}

.booking-owner {
    color: #64748b;
    font-size: 0.9rem;
    margin-bottom: 8px;
}

.booking-date {
    font-size: 0.85rem;
    color: #667eea;
    font-weight: 500;
}

.booking-status {
    text-align: right;
}

.status-badge {
    padding: 4px 12px;
    border-radius: 50px;
    font-size: 0.8rem;
    font-weight: 600;
    margin-bottom: 8px;
    display: inline-block;
}

.status-badge.confirmed { background: #dcfce7; color: #166534; }
.status-badge.pending { background: #fef3c7; color: #92400e; }
.status-badge.completed { background: #dbeafe; color: #1e40af; }
.status-badge.cancelled { background: #fee2e2; color: #991b1b; }

.booking-countdown {
    font-size: 0.8rem;
    color: #64748b;
}

.booking-link {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 2;
}

.activity-item-modern {
    display: flex;
    align-items: flex-start;
    gap: 16px;
    padding: 16px;
    border-radius: 12px;
    margin-bottom: 16px;
    position: relative;
    transition: all 0.3s ease;
    cursor: pointer;
    border-left: 3px solid transparent;
}

.activity-item-modern:hover {
    background: #f8f9ff;
    border-left-color: #667eea;
}

.activity-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    flex-shrink: 0;
}

.activity-icon.pending { background: linear-gradient(135deg, #f59e0b, #d97706); }
.activity-icon.confirmed { background: linear-gradient(135deg, #10b981, #059669); }
.activity-icon.completed { background: linear-gradient(135deg, #3b82f6, #1d4ed8); }
.activity-icon.cancelled { background: linear-gradient(135deg, #ef4444, #dc2626); }

.activity-content h6 {
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 4px;
}

.activity-content p {
    color: #64748b;
    font-size: 0.9rem;
    margin-bottom: 8px;
}

.activity-meta {
    display: flex;
    align-items: center;
    gap: 12px;
}

.activity-date {
    font-size: 0.8rem;
    color: #9ca3af;
}

.activity-link {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 2;
}

.profile-completion-card {
    background: linear-gradient(135deg, #f8f9ff, #f1f5f9);
    border-radius: 20px;
    padding: 32px;
    border: 1px solid rgba(0, 0, 0, 0.05);
}

.profile-completion-content {
    display: flex;
    align-items: center;
    gap: 24px;
}

.profile-avatar {
    position: relative;
    flex-shrink: 0;
}

.profile-avatar img {
    width: 80px;
    height: 80px;
    border-radius: 20px;
    object-fit: cover;
    border: 3px solid white;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
}

.avatar-placeholder {
    width: 80px;
    height: 80px;
    border-radius: 20px;
    background: linear-gradient(135deg, #e2e8f0, #cbd5e1);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    color: #64748b;
    border: 3px solid white;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
}

.completion-badge {
    position: absolute;
    bottom: -5px;
    right: -5px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.7rem;
    font-weight: 600;
    border: 2px solid white;
}

.completion-details {
    flex: 1;
}

.completion-details h5 {
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 12px;
}

.progress-container {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 12px;
}

.progress-bar {
    flex: 1;
    height: 8px;
    background: #e2e8f0;
    border-radius: 50px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #667eea, #764ba2);
    border-radius: 50px;
    transition: width 0.3s ease;
}

.progress-text {
    font-weight: 600;
    color: #667eea;
    font-size: 0.9rem;
}

.completion-details p {
    color: #64748b;
    margin-bottom: 0;
}

.completion-actions {
    flex-shrink: 0;
}

.quick-action-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 12px;
    padding: 24px 16px;
    background: white;
    border-radius: 16px;
    text-decoration: none;
    color: #64748b;
    border: 2px solid #f1f5f9;
    transition: all 0.3s ease;
    height: 100%;
}

.quick-action-btn:hover {
    color: #667eea;
    border-color: #667eea;
    transform: translateY(-2px);
    box-shadow: 0 10px 25px rgba(102, 126, 234, 0.15);
}

.action-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    color: white;
}

.action-icon.primary { background: linear-gradient(135deg, #667eea, #764ba2); }
.action-icon.success { background: linear-gradient(135deg, #10b981, #059669); }
.action-icon.warning { background: linear-gradient(135deg, #f59e0b, #d97706); }
.action-icon.info { background: linear-gradient(135deg, #3b82f6, #1d4ed8); }

.quick-action-btn span {
    font-weight: 500;
    text-align: center;
}

@media (max-width: 991px) {
    .dashboard-title {
        font-size: 2rem;
    }
    
    .profile-completion-content {
        flex-direction: column;
        text-align: center;
        gap: 20px;
    }
    
    .completion-actions {
        align-self: stretch;
    }
}

@media (max-width: 768px) {
    .dashboard-header-section {
        padding: 60px 0 30px;
    }
    
    .dashboard-stats-section {
        margin-top: -30px;
    }
    
    .card-header-modern {
        padding: 20px 24px;
        flex-direction: column;
        gap: 12px;
        align-items: flex-start;
    }
    
    .card-body-modern {
        padding: 24px;
    }
    
    .booking-item-modern {
        flex-direction: column;
        align-items: flex-start;
        gap: 12px;
    }
    
    .booking-status {
        text-align: left;
        align-self: stretch;
    }
}
</style>

<?php
// Include footer
include_once '../includes/footer.php';

// Close database connection
$conn->close();
?>