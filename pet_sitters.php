<?php
// Include header
include_once 'includes/header.php';

// Include database connection
require_once 'config/db_connect.php';

// Initialize search parameters
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$service_type = isset($_GET['service_type']) ? $_GET['service_type'] : '';
$min_price = isset($_GET['min_price']) ? (float)$_GET['min_price'] : 0;
$max_price = isset($_GET['max_price']) ? (float)$_GET['max_price'] : 1000;
$rating = isset($_GET['rating']) ? (int)$_GET['rating'] : 0;

// Prepare the SQL query base - only include approved pet sitters
$sql = "SELECT ps.*, 
        (SELECT AVG(r.rating) FROM reviews r WHERE r.sitterID = ps.userID) as avg_rating,
        (SELECT COUNT(r.reviewID) FROM reviews r WHERE r.sitterID = ps.userID) as review_count
        FROM pet_sitter ps
        WHERE ps.approval_status = 'Approved'";

// Add conditions based on search parameters
if (!empty($search_query)) {
    $search_query = "%{$search_query}%";
    $sql .= " AND (ps.fullName LIKE ? OR ps.service LIKE ? OR ps.specialization LIKE ?)";
}

if (!empty($service_type)) {
    $sql .= " AND ps.service LIKE ?";
}

$sql .= " AND ps.price BETWEEN ? AND ?";

// Rating filter will be applied after fetching results
$sql .= " ORDER BY avg_rating DESC, ps.fullName ASC";

// Prepare and execute the query
$stmt = $conn->prepare($sql);

// Bind parameters based on search conditions
if (!empty($search_query) && !empty($service_type)) {
    $stmt->bind_param("ssssdd", $search_query, $search_query, $search_query, $service_type, $min_price, $max_price);
} elseif (!empty($search_query)) {
    $stmt->bind_param("sssdd", $search_query, $search_query, $search_query, $min_price, $max_price);
} elseif (!empty($service_type)) {
    $stmt->bind_param("sdd", $service_type, $min_price, $max_price);
} else {
    $stmt->bind_param("dd", $min_price, $max_price);
}

$stmt->execute();
$result = $stmt->get_result();
$pet_sitters = [];

// Fetch results and filter by rating if needed
while ($row = $result->fetch_assoc()) {
    // If rating filter is set, only include sitters with that minimum rating
    if ($rating > 0 && $row['avg_rating'] < $rating) {
        continue;
    }
    $pet_sitters[] = $row;
}

$stmt->close();
?>

<!-- Page Header -->
<div class="bg-light py-5">
    <div class="container">
        <h1 class="display-4">Find a Pet Sitter</h1>
        <p class="lead">Browse our network of professional and trusted pet sitters in your area.</p>
    </div>
</div>

<!-- Search and Filter Section -->
<section class="py-4 bg-light-gray">
    <div class="container">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title mb-3">Search & Filter</h5>
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="get">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                <input type="text" class="form-control" name="search" placeholder="Search by name or service" value="<?php echo htmlspecialchars($search_query); ?>">
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <select class="form-select" name="service_type">
                                <option value="">All Services</option>
                                <option value="Pet Sitting" <?php if($service_type === 'Pet Sitting') echo 'selected'; ?>>Pet Sitting</option>
                                <option value="Dog Walking" <?php if($service_type === 'Dog Walking') echo 'selected'; ?>>Dog Walking</option>
                                <option value="Pet Boarding" <?php if($service_type === 'Pet Boarding') echo 'selected'; ?>>Pet Boarding</option>
                                <option value="Pet Grooming" <?php if($service_type === 'Pet Grooming') echo 'selected'; ?>>Pet Grooming</option>
                                <option value="Pet Training" <?php if($service_type === 'Pet Training') echo 'selected'; ?>>Pet Training</option>
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <select class="form-select" name="rating">
                                <option value="0">Any Rating</option>
                                <option value="4" <?php if($rating === 4) echo 'selected'; ?>>4+ Stars</option>
                                <option value="3" <?php if($rating === 3) echo 'selected'; ?>>3+ Stars</option>
                                <option value="2" <?php if($rating === 2) echo 'selected'; ?>>2+ Stars</option>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <div class="d-flex">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control" name="min_price" placeholder="Min" value="<?php echo $min_price; ?>">
                                <span class="input-group-text">-</span>
                                <input type="number" class="form-control" name="max_price" placeholder="Max" value="<?php echo $max_price; ?>">
                            </div>
                        </div>
                        
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">Apply Filters</button>
                            <a href="pet_sitters.php" class="btn btn-outline-secondary">Reset</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>

<!-- Pet Sitters Listing -->
<section class="py-5">
    <div class="container">
        <?php if (empty($pet_sitters)): ?>
            <div class="alert alert-info text-center">
                <h4>No pet sitters found</h4>
                <p>Try adjusting your search criteria or check back later for new pet sitters.</p>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($pet_sitters as $sitter): ?>
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="card sitter-card h-100">
                            <?php if (!empty($sitter['image'])): ?>
                                <img src="assets/images/pet_sitters/<?php echo htmlspecialchars($sitter['image']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($sitter['fullName']); ?>">
                            <?php else: ?>
                                <img src="assets/images/sitter-placeholder.jpg" class="card-img-top" alt="Placeholder">
                            <?php endif; ?>
                            
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($sitter['fullName']); ?></h5>
                                
                                <div class="rating mb-2">
                                    <?php
                                    $avg_rating = $sitter['avg_rating'] ? round($sitter['avg_rating'], 1) : 0;
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
                                    <span class="ms-1"><?php echo $avg_rating; ?> (<?php echo $sitter['review_count']; ?> reviews)</span>
                                </div>
                                
                                <p class="badge bg-primary mb-2"><?php echo htmlspecialchars($sitter['service']); ?></p>
                                
                                <?php if (!empty($sitter['specialization'])): ?>
                                    <p class="small mb-2"><strong>Specializes in:</strong> <?php echo htmlspecialchars($sitter['specialization']); ?></p>
                                <?php endif; ?>
                                
                                <?php if (!empty($sitter['experience'])): ?>
                                    <p class="small mb-2"><strong>Experience:</strong> <?php echo htmlspecialchars($sitter['experience']); ?></p>
                                <?php endif; ?>
                                
                                <p class="price mb-3">$<?php echo number_format($sitter['price'], 2); ?> / hour</p>
                                
                                <div class="d-grid gap-2">
                                    <a href="sitter_profile.php?id=<?php echo $sitter['userID']; ?>" class="btn btn-outline-primary">View Profile</a>
                                    <a href="booking.php?sitter_id=<?php echo $sitter['userID']; ?>" class="btn btn-primary">Book Now</a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Become a Pet Sitter CTA -->
<section class="py-5 bg-primary text-white">
    <div class="container text-center">
        <h2>Become a Pet Sitter</h2>
        <p class="lead mb-4">Join our community of pet sitters and earn money doing what you love - caring for pets! All applications are reviewed by our admin team.</p>
        <a href="register.php?type=pet_sitter" class="btn btn-light btn-lg">Sign Up as a Pet Sitter</a>
    </div>
</section>

<?php
// Include footer
include_once 'includes/footer.php';

// Close database connection
$conn->close();
?>