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

<!-- Page Header -->
<div class="container-fluid bg-primary text-white py-4">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="display-4 mb-2">Pet Sitter Profile</h1>
                <p class="lead">View and manage <?php echo htmlspecialchars($sitter['fullName']); ?>'s profile.</p>
            </div>
            <div class="col-md-4 text-md-end">
                <a href="users.php" class="btn btn-light">
                    <i class="fas fa-arrow-left me-1"></i> Back to Users
                </a>
            </div>
        </div>
    </div>
</div>

<div class="container py-5">
    <!-- Action Panel -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h5 class="mb-0">Account Status</h5>
                    <div class="d-flex align-items-center mt-2">
                        <div class="me-3">
                            <span class="badge <?php 
                                switch ($sitter['approval_status']) {
                                    case 'Approved':
                                        echo 'bg-success';
                                        break;
                                    case 'Pending':
                                        echo 'bg-warning';
                                        break;
                                    case 'Rejected':
                                        echo 'bg-danger';
                                        break;
                                }
                            ?> p-2"><?php echo $sitter['approval_status']; ?></span>
                        </div>
                        
                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . "?id=" . $sitter_id); ?>" method="post" class="d-flex">
                            <select class="form-select form-select-sm me-2" name="approval_status">
                                <option value="Approved" <?php echo ($sitter['approval_status'] === 'Approved') ? 'selected' : ''; ?>>Approved</option>
                                <option value="Pending" <?php echo ($sitter['approval_status'] === 'Pending') ? 'selected' : ''; ?>>Pending</option>
                                <option value="Rejected" <?php echo ($sitter['approval_status'] === 'Rejected') ? 'selected' : ''; ?>>Rejected</option>
                            </select>
                            <button type="submit" name="update_approval_status" class="btn btn-sm btn-primary">Update Status</button>
                        </form>
                    </div>
                </div>
                
                <div class="col-md-6 text-md-end mt-3 mt-md-0">
                    <a href="mailto:<?php echo $sitter['email']; ?>" class="btn btn-outline-primary me-2">
                        <i class="fas fa-envelope me-1"></i> Email Pet Sitter
                    </a>
                    <a href="edit_sitter.php?id=<?php echo $sitter_id; ?>" class="btn btn-outline-success">
                        <i class="fas fa-edit me-1"></i> Edit Profile
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Pet Sitter Information -->
        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-body text-center">
                    <?php if (!empty($sitter['image'])): ?>
                        <img src="../assets/images/pet_sitters/<?php echo htmlspecialchars($sitter['image']); ?>" class="rounded-circle img-fluid mb-3" width="150" alt="<?php echo htmlspecialchars($sitter['fullName']); ?>">
                    <?php else: ?>
                        <img src="../assets/images/sitter-placeholder.jpg" class="rounded-circle img-fluid mb-3" width="150" alt="Placeholder">
                    <?php endif; ?>
                    
                    <h3 class="card-title"><?php echo htmlspecialchars($sitter['fullName']); ?></h3>
                    <p class="text-muted"><?php echo htmlspecialchars($sitter['username']); ?></p>
                    
                    <div class="rating mb-3">
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
                        <span class="ms-1"><?php echo $avg_rating; ?> (<?php echo $review_count; ?> reviews)</span>
                    </div>
                    
                    <p class="badge bg-primary mb-3"><?php echo htmlspecialchars($sitter['service']); ?></p>
                    
                    <p class="price mb-3">$<?php echo number_format($sitter['price'], 2); ?> / hour</p>
                    
                    <hr>
                    
                    <div class="text-start">
                        <p><strong>Contact:</strong> <?php echo htmlspecialchars($sitter['contact']); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($sitter['email']); ?></p>
                        <p><strong>Address:</strong> <?php echo htmlspecialchars($sitter['address']); ?></p>
                        <p><strong>Joined:</strong> <?php echo date('M d, Y', strtotime($sitter['created_at'])); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Booking Statistics</h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-6 mb-3">
                            <h5><?php echo $booking_stats['total_bookings']; ?></h5>
                            <p class="text-muted small">Total Bookings</p>
                        </div>
                        <div class="col-6 mb-3">
                            <h5><?php echo $booking_stats['completed_bookings']; ?></h5>
                            <p class="text-muted small">Completed</p>
                        </div>
                        <div class="col-6">
                            <h5><?php echo $booking_stats['active_bookings']; ?></h5>
                            <p class="text-muted small">Active</p>
                        </div>
                        <div class="col-6">
                            <h5><?php echo $booking_stats['cancelled_bookings']; ?></h5>
                            <p class="text-muted small">Cancelled</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Pet Sitter Details -->
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Profile Details</h5>
                </div>
                <div class="card-body">
                    <!-- Experience Section -->
                    <?php if (!empty($sitter['experience'])): ?>
                        <div class="mb-4">
                            <h6><i class="fas fa-briefcase me-2"></i> Experience</h6>
                            <p><?php echo nl2br(htmlspecialchars($sitter['experience'])); ?></p>
                        </div>
                        <hr>
                    <?php endif; ?>
                    
                    <!-- Qualifications Section -->
                    <?php if (!empty($sitter['qualifications'])): ?>
                        <div class="mb-4">
                            <h6><i class="fas fa-certificate me-2"></i> Qualifications</h6>
                            <p><?php echo nl2br(htmlspecialchars($sitter['qualifications'])); ?></p>
                        </div>
                        <hr>
                    <?php endif; ?>
                    
                    <!-- Specialization Section -->
                    <?php if (!empty($sitter['specialization'])): ?>
                        <div class="mb-4">
                            <h6><i class="fas fa-paw me-2"></i> Specialization</h6>
                            <p><?php echo nl2br(htmlspecialchars($sitter['specialization'])); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Services Offered -->
            <?php if (!empty($services)): ?>
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Services Offered (<?php echo count($services); ?>)</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($services as $service): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="card h-100">
                                        <?php if (!empty($service['image'])): ?>
                                            <img src="../assets/images/services/<?php echo htmlspecialchars($service['image']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($service['name']); ?>" style="height: 150px; object-fit: cover;">
                                        <?php endif; ?>
                                        <div class="card-body">
                                            <h6 class="card-title"><?php echo htmlspecialchars($service['name']); ?></h6>
                                            <span class="badge bg-primary mb-2"><?php echo htmlspecialchars($service['type']); ?></span>
                                            <p class="card-text small"><?php echo substr(htmlspecialchars($service['description']), 0, 100) . '...'; ?></p>
                                            <p class="price mb-0">$<?php echo number_format($service['price'], 2); ?> / hour</p>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Reviews Section -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Reviews (<?php echo count($reviews); ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($reviews)): ?>
                        <p class="text-center text-muted">No reviews yet for this pet sitter.</p>
                    <?php else: ?>
                        <?php foreach ($reviews as $review): ?>
                            <div class="review mb-4">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="me-3">
                                        <?php if (!empty($review['reviewerImage'])): ?>
                                            <img src="../assets/images/users/<?php echo htmlspecialchars($review['reviewerImage']); ?>" class="rounded-circle" width="50" height="50" alt="<?php echo htmlspecialchars($review['reviewerName']); ?>">
                                        <?php else: ?>
                                            <img src="../assets/images/user-placeholder.jpg" class="rounded-circle" width="50" height="50" alt="User">
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <h6 class="mb-0"><?php echo htmlspecialchars($review['reviewerName']); ?></h6>
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
                                <p><?php echo nl2br(htmlspecialchars($review['review'])); ?></p>
                                
                                <?php if (!empty($review['reply'])): ?>
                                    <div class="reply-container ms-5 p-3 bg-light rounded">
                                        <h6 class="mb-2">Response from <?php echo htmlspecialchars($sitter['fullName']); ?></h6>
                                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($review['reply'])); ?></p>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="text-end">
                                    <a href="review_details.php?id=<?php echo $review['reviewID']; ?>" class="btn btn-sm btn-outline-primary">View Details</a>
                                </div>
                                
                                <?php if ($review !== end($reviews)): ?>
                                    <hr>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
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