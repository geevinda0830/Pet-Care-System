<?php
// Include header
include_once 'includes/header.php';

// Include database connection
require_once 'config/db_connect.php';

// Initialize search and filter parameters
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$category = isset($_GET['category']) ? $_GET['category'] : '';
$min_price = isset($_GET['min_price']) ? (float)$_GET['min_price'] : 0;
$max_price = isset($_GET['max_price']) ? (float)$_GET['max_price'] : 1000;
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'newest';

// Prepare the SQL query base
$sql = "SELECT p.*, 
        (SELECT AVG(r.rating) FROM reviews r WHERE r.productID = p.productID) as avg_rating,
        (SELECT COUNT(r.reviewID) FROM reviews r WHERE r.productID = p.productID) as review_count
        FROM pet_food_and_accessories p
        WHERE 1=1";

// Add conditions based on search parameters
if (!empty($search_query)) {
    $search_query = "%{$search_query}%";
    $sql .= " AND (p.name LIKE ? OR p.description LIKE ? OR p.brand LIKE ?)";
}

if (!empty($category)) {
    $sql .= " AND p.category = ?";
}

$sql .= " AND p.price BETWEEN ? AND ?";

// Add sorting
switch ($sort_by) {
    case 'price_low':
        $sql .= " ORDER BY p.price ASC";
        break;
    case 'price_high':
        $sql .= " ORDER BY p.price DESC";
        break;
    case 'rating':
        $sql .= " ORDER BY avg_rating DESC";
        break;
    case 'newest':
    default:
        $sql .= " ORDER BY p.productID DESC";
        break;
}

// Prepare and execute the query
$stmt = $conn->prepare($sql);

// Bind parameters based on search conditions
if (!empty($search_query) && !empty($category)) {
    $stmt->bind_param("ssssdd", $search_query, $search_query, $search_query, $category, $min_price, $max_price);
} elseif (!empty($search_query)) {
    $stmt->bind_param("sssdd", $search_query, $search_query, $search_query, $min_price, $max_price);
} elseif (!empty($category)) {
    $stmt->bind_param("sdd", $category, $min_price, $max_price);
} else {
    $stmt->bind_param("dd", $min_price, $max_price);
}

$stmt->execute();
$result = $stmt->get_result();
$products = [];

// Fetch results
while ($row = $result->fetch_assoc()) {
    $products[] = $row;
}

$stmt->close();

// Get categories for filter dropdown
$category_sql = "SELECT DISTINCT category FROM pet_food_and_accessories ORDER BY category";
$category_result = $conn->query($category_sql);
$categories = [];

if ($category_result->num_rows > 0) {
    while ($row = $category_result->fetch_assoc()) {
        $categories[] = $row['category'];
    }
}
?>

<!-- Page Header -->
<div class="bg-light py-5">
    <div class="container">
        <h1 class="display-4">Pet Shop</h1>
        <p class="lead">Browse our wide selection of premium pet food, toys, accessories, and more for your furry friends.</p>
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
                                <input type="text" class="form-control" name="search" placeholder="Search products" value="<?php echo htmlspecialchars($search_query); ?>">
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <select class="form-select" name="category">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat); ?>" <?php if($category === $cat) echo 'selected'; ?>>
                                        <?php echo htmlspecialchars($cat); ?>
                                    </option>
                                <?php endforeach; ?>
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
                        
                        <div class="col-md-2">
                            <select class="form-select" name="sort_by">
                                <option value="newest" <?php if($sort_by === 'newest') echo 'selected'; ?>>Newest</option>
                                <option value="price_low" <?php if($sort_by === 'price_low') echo 'selected'; ?>>Price: Low to High</option>
                                <option value="price_high" <?php if($sort_by === 'price_high') echo 'selected'; ?>>Price: High to Low</option>
                                <option value="rating" <?php if($sort_by === 'rating') echo 'selected'; ?>>Top Rated</option>
                            </select>
                        </div>
                        
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">Apply Filters</button>
                            <a href="shop.php" class="btn btn-outline-secondary">Reset</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>

<!-- Products Listing -->
<section class="py-5">
    <div class="container">
        <?php if (empty($products)): ?>
            <div class="alert alert-info text-center">
                <h4>No products found</h4>
                <p>Try adjusting your search criteria or check back later for new products.</p>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($products as $product): ?>
                    <div class="col-lg-3 col-md-4 col-sm-6 mb-4">
                        <div class="card product-card h-100">
                            <?php if (!empty($product['image'])): ?>
                                <img src="assets/images/products/<?php echo htmlspecialchars($product['image']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($product['name']); ?>">
                            <?php else: ?>
                                <img src="assets/images/product-placeholder.jpg" class="card-img-top" alt="Placeholder">
                            <?php endif; ?>
                            
                            <div class="card-body d-flex flex-column">
                                <span class="badge bg-secondary mb-2"><?php echo htmlspecialchars($product['category']); ?></span>
                                <h5 class="card-title"><?php echo htmlspecialchars($product['name']); ?></h5>
                                <p class="text-muted small mb-2"><?php echo htmlspecialchars($product['brand']); ?></p>
                                
                                <div class="rating mb-2">
                                    <?php
                                    $avg_rating = $product['avg_rating'] ? round($product['avg_rating'], 1) : 0;
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
                                    <span class="ms-1">(<?php echo $product['review_count']; ?>)</span>
                                </div>
                                
                                <?php if (!empty($product['weight'])): ?>
                                    <p class="small mb-2"><?php echo htmlspecialchars($product['weight']); ?></p>
                                <?php endif; ?>
                                
                                <p class="price mt-auto mb-3">$<?php echo number_format($product['price'], 2); ?></p>
                                
                                <div class="d-grid gap-2 mt-auto">
                                    <a href="product.php?id=<?php echo $product['productID']; ?>" class="btn btn-sm btn-outline-primary">View Details</a>
                                    <?php if($product['stock'] > 0): ?>
                                        <button type="button" class="btn btn-sm btn-success" onclick="addToCart(<?php echo $product['productID']; ?>)">
                                            <i class="fas fa-cart-plus me-1"></i> Add to Cart
                                        </button>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-sm btn-secondary" disabled>Out of Stock</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Featured Categories -->
<section class="py-5 bg-light-gray">
    <div class="container">
        <h2 class="text-center mb-4">Shop by Category</h2>
        <div class="row">
            <div class="col-md-4 mb-4">
                <div class="card h-100">
                    <img src="assets/images/dog-food-category.jpg" class="card-img-top" alt="Dog Food">
                    <div class="card-body text-center">
                        <h5 class="card-title">Dog Food</h5>
                        <p class="card-text">Premium nutrition for your canine companion</p>
                        <a href="shop.php?category=Dog Food" class="btn btn-primary">Browse Dog Food</a>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4 mb-4">
                <div class="card h-100">
                    <img src="assets/images/cat-food-category.jpg" class="card-img-top" alt="Cat Food">
                    <div class="card-body text-center">
                        <h5 class="card-title">Cat Food</h5>
                        <p class="card-text">Delicious meals for your feline friend</p>
                        <a href="shop.php?category=Cat Food" class="btn btn-primary">Browse Cat Food</a>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4 mb-4">
                <div class="card h-100">
                    <img src="assets/images/pet-toys-category.jpg" class="card-img-top" alt="Pet Toys">
                    <div class="card-body text-center">
                        <h5 class="card-title">Pet Toys</h5>
                        <p class="card-text">Fun and engaging toys for all pets</p>
                        <a href="shop.php?category=Pet Toys" class="btn btn-primary">Browse Pet Toys</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Special Offers -->
<section class="py-5">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h2>Special Offers</h2>
                <p class="lead">Get 10% off your first order with code: <span class="text-primary fw-bold">WELCOME10</span></p>
                <p>Sign up for our newsletter to receive exclusive offers, pet care tips, and updates on new products.</p>
                <form class="row g-3">
                    <div class="col-md-8">
                        <input type="email" class="form-control" placeholder="Enter your email">
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-primary w-100">Subscribe</button>
                    </div>
                </form>
            </div>
            <div class="col-md-6">
                <img src="assets/images/special-offer.jpg" alt="Special Offer" class="img-fluid rounded">
            </div>
        </div>
    </div>
</section>

<?php
// Include footer
include_once 'includes/footer.php';

// Close database connection
$conn->close();
?>