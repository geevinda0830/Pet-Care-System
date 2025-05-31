<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require_once 'config/db_connect.php';

// Check if sitter ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "Invalid pet sitter ID.";
    header("Location: pet_sitters.php");
    exit();
}

$sitter_id = $_GET['id'];

// Get pet sitter details
$sitter_sql = "SELECT * FROM pet_sitter WHERE userID = ? AND approval_status = 'Approved'";
$sitter_stmt = $conn->prepare($sitter_sql);
$sitter_stmt->bind_param("i", $sitter_id);
$sitter_stmt->execute();
$sitter_result = $sitter_stmt->get_result();

if ($sitter_result->num_rows === 0) {
    $_SESSION['error_message'] = "Pet sitter not found or not approved.";
    header("Location: pet_sitters.php");
    exit();
}

$sitter = $sitter_result->fetch_assoc();
$sitter_stmt->close();

// Get sitter's average rating and review count
$rating_sql = "SELECT AVG(r.rating) as avg_rating, COUNT(r.reviewID) as review_count 
               FROM reviews r 
               WHERE r.sitterID = ?";
$rating_stmt = $conn->prepare($rating_sql);
$rating_stmt->bind_param("i", $sitter_id);
$rating_stmt->execute();
$rating_result = $rating_stmt->get_result();
$rating_data = $rating_result->fetch_assoc();

$avg_rating = $rating_data['avg_rating'] ? round($rating_data['avg_rating'], 1) : 0;
$review_count = $rating_data['review_count'];
$rating_stmt->close();

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

// Get reviews for this sitter
$reviews_sql = "SELECT r.*, po.fullName as reviewerName, po.image as reviewerImage 
                FROM reviews r 
                JOIN pet_owner po ON r.userID = po.userID 
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

// Check if logged-in user has completed bookings with this sitter
$can_review = false;
if (isset($_SESSION['user_id']) && $_SESSION['user_type'] === 'pet_owner') {
    $booking_check_sql = "SELECT b.bookingID 
                          FROM booking b 
                          LEFT JOIN reviews r ON b.bookingID = r.bookingID 
                          WHERE b.userID = ? 
                          AND b.sitterID = ? 
                          AND b.status = 'Completed' 
                          AND r.reviewID IS NULL";
    $booking_check_stmt = $conn->prepare($booking_check_sql);
    $booking_check_stmt->bind_param("ii", $_SESSION['user_id'], $sitter_id);
    $booking_check_stmt->execute();
    $booking_check_result = $booking_check_stmt->get_result();
    
    if ($booking_check_result->num_rows > 0) {
        $can_review = true;
        $completed_booking = $booking_check_result->fetch_assoc();
        $completed_booking_id = $completed_booking['bookingID'];
    }
    
    $booking_check_stmt->close();
}

// Include header
include_once 'includes/header.php';
?>

<div class="container py-5">
    <div class="row">
        <!-- Pet Sitter Profile -->
        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-body text-center">
                    <?php if (!empty($sitter['image'])): ?>
                        <img src="assets/images/pet_sitters/<?php echo htmlspecialchars($sitter['image']); ?>" class="rounded-circle img-fluid mb-3" width="150" alt="<?php echo htmlspecialchars($sitter['fullName']); ?>">
                    <?php else: ?>
                        <img src="assets/images/sitter-placeholder.jpg" class="rounded-circle img-fluid mb-3" width="150" alt="Placeholder">
                    <?php endif; ?>
                    
                    <h3 class="card-title"><?php echo htmlspecialchars($sitter['fullName']); ?></h3>
                    
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
                    
                    <?php if (isset($_SESSION['user_id']) && $_SESSION['user_type'] === 'pet_owner'): ?>
                        <a href="booking.php?sitter_id=<?php echo $sitter['userID']; ?>" class="btn btn-primary w-100 mb-2">
                            <i class="fas fa-calendar-check me-1"></i> Book Now
                        </a>
                        
                        <?php if ($can_review): ?>
                            <a href="user/add_review.php?sitter_id=<?php echo $sitter['userID']; ?>&booking_id=<?php echo $completed_booking_id; ?>" class="btn btn-outline-warning w-100">
                                <i class="fas fa-star me-1"></i> Write a Review
                            </a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Contact Information</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($sitter['contact'])): ?>
                        <p><i class="fas fa-phone me-2"></i> <?php echo htmlspecialchars($sitter['contact']); ?></p>
                    <?php endif; ?>
                    
                    <?php if (!empty($sitter['email'])): ?>
                        <p><i class="fas fa-envelope me-2"></i> <?php echo htmlspecialchars($sitter['email']); ?></p>
                    <?php endif; ?>
                    
                    <?php if (!empty($sitter['address'])): ?>
                        <p><i class="fas fa-map-marker-alt me-2"></i> <?php echo htmlspecialchars($sitter['address']); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if (!empty($sitter['latitude']) && !empty($sitter['longitude'])): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Location</h5>
                    </div>
                    <div class="card-body p-0">
                        <div id="map-container" class="map-container rounded" style="height: 300px;"></div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Pet Sitter Details -->
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">About <?php echo htmlspecialchars($sitter['fullName']); ?></h5>
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
                    <div class="card-header">
                        <h5 class="mb-0">Services Offered</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($services as $service): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="card h-100">
                                        <?php if (!empty($service['image'])): ?>
                                            <img src="assets/images/services/<?php echo htmlspecialchars($service['image']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($service['name']); ?>" style="height: 150px; object-fit: cover;">
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
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Reviews (<?php echo count($reviews); ?>)</h5>
                    <?php if (isset($_SESSION['user_id']) && $_SESSION['user_type'] === 'pet_owner' && $can_review): ?>
                        <a href="user/add_review.php?sitter_id=<?php echo $sitter['userID']; ?>&booking_id=<?php echo $completed_booking_id; ?>" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-star me-1"></i> Write a Review
                        </a>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (empty($reviews)): ?>
                        <p class="text-center text-muted">No reviews yet. Be the first to review this pet sitter!</p>
                    <?php else: ?>
                        <?php foreach ($reviews as $review): ?>
                            <div class="review mb-4">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="me-3">
                                        <?php if (!empty($review['reviewerImage'])): ?>
                                            <img src="assets/images/users/<?php echo htmlspecialchars($review['reviewerImage']); ?>" class="rounded-circle" width="50" height="50" alt="<?php echo htmlspecialchars($review['reviewerName']); ?>">
                                        <?php else: ?>
                                            <img src="assets/images/user-placeholder.jpg" class="rounded-circle" width="50" height="50" alt="User">
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
                                        <small class="text-muted"><?php echo date('M d, Y', strtotime($review['created_at'])); ?></small>
                                    </div>
                                </div>
                                <p><?php echo nl2br(htmlspecialchars($review['review'])); ?></p>
                                
                                <?php if (!empty($review['reply'])): ?>
                                    <div class="reply-container ms-5 p-3 bg-light rounded">
                                        <h6 class="mb-2">Response from <?php echo htmlspecialchars($sitter['fullName']); ?></h6>
                                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($review['reply'])); ?></p>
                                    </div>
                                <?php endif; ?>
                                
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

<?php if (!empty($sitter['latitude']) && !empty($sitter['longitude'])): ?>
<!-- Google Maps Script -->
<script>
    function initMap() {
        const sitterLocation = {
            lat: <?php echo $sitter['latitude']; ?>,
            lng: <?php echo $sitter['longitude']; ?>
        };
        
        const map = new google.maps.Map(document.getElementById("map-container"), {
            zoom: 14,
            center: sitterLocation,
        });
        
        const marker = new google.maps.Marker({
            position: sitterLocation,
            map: map,
            title: "<?php echo htmlspecialchars($sitter['fullName']); ?>"
        });
    }
</script>
<script src="https://maps.googleapis.com/maps/api/js?key=YOUR_API_KEY&callback=initMap" async defer></script>
<?php endif; ?>

<?php
// Include footer
include_once 'includes/footer.php';

// Close database connection
$conn->close();
?>