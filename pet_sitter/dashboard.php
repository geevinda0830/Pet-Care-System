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

<!-- Dashboard Header -->
<div class="container-fluid bg-light py-4">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="display-5 mb-2">Welcome, <?php echo htmlspecialchars($user['fullName']); ?>!</h1>
                <p class="lead">Manage your pet sitting services and bookings from your dashboard.</p>
            </div>
            <div class="col-md-4 text-md-end">
                <a href="profile.php" class="btn btn-primary">
                    <i class="fas fa-user-edit me-1"></i> Edit Profile
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Dashboard Content -->
<div class="container py-5">
    <!-- Statistics Cards -->
    <div class="row mb-5">
        <div class="col-md-3">
            <div class="dashboard-stat bg-primary text-white">
                <div class="icon">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <h3><?php echo $active_bookings_count; ?></h3>
                <p>Active Bookings</p>
                <a href="bookings.php?status=active" class="stretched-link"></a>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="dashboard-stat bg-success text-white">
                <div class="icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h3><?php echo $completed_bookings_count; ?></h3>
                <p>Completed Jobs</p>
                <a href="bookings.php?status=completed" class="stretched-link"></a>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="dashboard-stat bg-warning text-white">
                <div class="icon">
                    <i class="fas fa-star"></i>
                </div>
                <h3><?php echo $avg_rating > 0 ? $avg_rating : 'N/A'; ?></h3>
                <p>Average Rating (<?php echo $review_count; ?> reviews)</p>
                <a href="reviews.php" class="stretched-link"></a>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="dashboard-stat bg-info text-white">
                <div class="icon">
                    <i class="fas fa-clipboard-list"></i>
                </div>
                <h3><?php echo $services_count; ?></h3>
                <p>Services Offered</p>
                <a href="services.php" class="stretched-link"></a>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Upcoming Bookings -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Upcoming Bookings</h5>
                    <a href="bookings.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body">
                    <?php if (empty($upcoming_bookings)): ?>
                        <p class="text-muted">No upcoming bookings found.</p>
                    <?php else: ?>
                        <?php foreach ($upcoming_bookings as $booking): ?>
                            <div class="booking-card mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h6><?php echo htmlspecialchars($booking['petName']); ?> (<?php echo htmlspecialchars($booking['petType']); ?>)</h6>
                                    <span class="badge bg-success">Confirmed</span>
                                </div>
                                <p class="mb-1 small">
                                    <i class="fas fa-user me-2"></i>
                                    Owner: <?php echo htmlspecialchars($booking['ownerName']); ?>
                                </p>
                                <p class="mb-1 small">
                                    <i class="fas fa-calendar me-2"></i>
                                    <?php echo date('M d, Y', strtotime($booking['checkInDate'])); ?> at 
                                    <?php echo date('h:i A', strtotime($booking['checkInTime'])); ?> - 
                                    <?php echo date('M d, Y', strtotime($booking['checkOutDate'])); ?> at 
                                    <?php echo date('h:i A', strtotime($booking['checkOutTime'])); ?>
                                </p>
                                <div class="d-flex justify-content-between align-items-center mt-2">
                                    <small class="text-muted">
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
                                    </small>
                                    <a href="booking_details.php?id=<?php echo $booking['bookingID']; ?>" class="btn btn-sm btn-outline-secondary">View Details</a>
                                </div>
                            </div>
                            <?php if ($booking !== end($upcoming_bookings)): ?>
                                <hr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Recent Bookings -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Recent Bookings</h5>
                    <a href="bookings.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_bookings)): ?>
                        <p class="text-muted">No recent bookings found.</p>
                    <?php else: ?>
                        <?php foreach ($recent_bookings as $booking): ?>
                            <div class="booking-card mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h6><?php echo htmlspecialchars($booking['petName']); ?> (<?php echo htmlspecialchars($booking['petType']); ?>)</h6>
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
                                    <span class="badge <?php echo $status_class; ?>"><?php echo $booking['status']; ?></span>
                                </div>
                                <p class="mb-1 small">
                                    <i class="fas fa-user me-2"></i>
                                    Owner: <?php echo htmlspecialchars($booking['ownerName']); ?>
                                </p>
                                <p class="mb-1 small">
                                    <i class="fas fa-calendar me-2"></i>
                                    <?php echo date('M d, Y', strtotime($booking['checkInDate'])); ?> - 
                                    <?php echo date('M d, Y', strtotime($booking['checkOutDate'])); ?>
                                </p>
                                <div class="d-flex justify-content-between align-items-center mt-2">
                                    <small class="text-muted">Booked on <?php echo date('M d, Y', strtotime($booking['created_at'])); ?></small>
                                    <a href="booking_details.php?id=<?php echo $booking['bookingID']; ?>" class="btn btn-sm btn-outline-secondary">View Details</a>
                                </div>
                            </div>
                            <?php if ($booking !== end($recent_bookings)): ?>
                                <hr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Profile Completion -->
    <?php
    // Calculate profile completion percentage
    $total_fields = 9; // fullName, email, contact, address, gender, service, qualifications, experience, specialization
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
    
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Profile Completion</h5>
                </div>
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-3">
                            <div class="text-center mb-3 mb-md-0">
                                <div class="position-relative d-inline-block">
                                    <?php if (!empty($user['image'])): ?>
                                        <img src="../assets/images/pet_sitters/<?php echo htmlspecialchars($user['image']); ?>" class="rounded-circle" width="120" height="120" alt="<?php echo htmlspecialchars($user['fullName']); ?>">
                                    <?php else: ?>
                                        <img src="../assets/images/sitter-placeholder.jpg" class="rounded-circle" width="120" height="120" alt="Placeholder">
                                    <?php endif; ?>
                                    
                                    <div class="position-absolute bottom-0 end-0 bg-white rounded-circle p-1">
                                        <span class="fw-bold text-<?php echo $completion_percentage >= 75 ? 'success' : ($completion_percentage >= 50 ? 'warning' : 'danger'); ?>"><?php echo $completion_percentage; ?>%</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-9">
                            <div class="mb-3">
                                <div class="progress">
                                    <div class="progress-bar bg-<?php echo $completion_percentage >= 75 ? 'success' : ($completion_percentage >= 50 ? 'warning' : 'danger'); ?>" role="progressbar" style="width: <?php echo $completion_percentage; ?>%" aria-valuenow="<?php echo $completion_percentage; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                            </div>
                            
                            <?php if ($completion_percentage < 100): ?>
                                <p>Complete your profile to attract more pet owners and increase your chances of getting booked.</p>
                                <a href="profile.php" class="btn btn-primary">Complete Your Profile</a>
                            <?php else: ?>
                                <p class="text-success">Great job! Your profile is complete. Pet owners can now find all the information they need about your services.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Quick Links -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Quick Links</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 mb-3 mb-md-0">
                            <a href="bookings.php" class="btn btn-outline-primary w-100 py-3">
                                <i class="fas fa-calendar-alt me-2"></i> Manage Bookings
                            </a>
                        </div>
                        <div class="col-md-3 mb-3 mb-md-0">
                            <a href="services.php" class="btn btn-outline-success w-100 py-3">
                                <i class="fas fa-clipboard-list me-2"></i> Manage Services
                            </a>
                        </div>
                        <div class="col-md-3 mb-3 mb-md-0">
                            <a href="reviews.php" class="btn btn-outline-warning w-100 py-3">
                                <i class="fas fa-star me-2"></i> View Reviews
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="../contact.php" class="btn btn-outline-info w-100 py-3">
                                <i class="fas fa-question-circle me-2"></i> Need Help?
                            </a>
                        </div>
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