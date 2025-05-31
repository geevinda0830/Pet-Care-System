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

<!-- Modern Shop Header -->
<section class="shop-header-section">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <div class="shop-header-content">
                    <span class="section-badge">🛍️ Premium Pet Store</span>
                    <h1 class="shop-title">Everything Your <span class="text-gradient">Pet Needs</span></h1>
                    <p class="shop-subtitle">Discover premium pet food, toys, and accessories from trusted brands. Free delivery on orders over Rs. 5,000!</p>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="shop-stats">
                    <div class="stat-card">
                        <div class="stat-icon">📦</div>
                        <div class="stat-number">500+</div>
                        <div class="stat-label">Products</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">🚚</div>
                        <div class="stat-number">Free</div>
                        <div class="stat-label">Delivery</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">⭐</div>
                        <div class="stat-number">4.8</div>
                        <div class="stat-label">Rating</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Modern Search and Filter Section -->
<section class="filter-section">
    <div class="container">
        <div class="filter-card">
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="get" class="filter-form">
                <div class="row g-3 align-items-end">
                    <div class="col-lg-4">
                        <label class="filter-label">Search Products</label>
                        <div class="search-group">
                            <i class="fas fa-search search-icon"></i>
                            <input type="text" class="form-control search-input" name="search" placeholder="Search by name, brand..." value="<?php echo htmlspecialchars($search_query); ?>">
                        </div>
                    </div>
                    
                    <div class="col-lg-2">
                        <label class="filter-label">Category</label>
                        <select class="form-select modern-select" name="category">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>" <?php if($category === $cat) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($cat); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-lg-3">
                        <label class="filter-label">Price Range</label>
                        <div class="price-range-group">
                            <input type="number" class="form-control price-input" name="min_price" placeholder="Min" value="<?php echo $min_price; ?>">
                            <span class="price-separator">-</span>
                            <input type="number" class="form-control price-input" name="max_price" placeholder="Max" value="<?php echo $max_price; ?>">
                        </div>
                    </div>
                    
                    <div class="col-lg-2">
                        <label class="filter-label">Sort By</label>
                        <select class="form-select modern-select" name="sort_by">
                            <option value="newest" <?php if($sort_by === 'newest') echo 'selected'; ?>>Newest</option>
                            <option value="price_low" <?php if($sort_by === 'price_low') echo 'selected'; ?>>Price: Low to High</option>
                            <option value="price_high" <?php if($sort_by === 'price_high') echo 'selected'; ?>>Price: High to Low</option>
                            <option value="rating" <?php if($sort_by === 'rating') echo 'selected'; ?>>Top Rated</option>
                        </select>
                    </div>
                    
                    <div class="col-lg-1">
                        <button type="submit" class="btn btn-primary-gradient filter-btn">
                            <i class="fas fa-filter"></i>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</section>

<!-- Modern Products Section -->
<section class="products-section">
    <div class="container">
        <?php if (empty($products)): ?>
            <div class="no-products-card">
                <div class="no-products-icon">
                    <i class="fas fa-search"></i>
                </div>
                <h4>No Products Found</h4>
                <p>Try adjusting your search criteria or check back later for new products.</p>
                <a href="shop.php" class="btn btn-outline-primary">Reset Filters</a>
            </div>
        <?php else: ?>
            <div class="products-header">
                <h3>Products <span class="product-count">(<?php echo count($products); ?> items)</span></h3>
                <a href="shop.php" class="reset-link">Reset All Filters</a>
            </div>
            
            <div class="row g-4">
                <?php foreach ($products as $product): ?>
                    <div class="col-xl-3 col-lg-4 col-md-6">
                        <div class="product-card-modern">
                            <?php if ($product['stock'] <= 5): ?>
                                <div class="product-badge urgent">⚡ Low Stock</div>
                            <?php elseif (rand(1, 3) === 1): ?>
                                <div class="product-badge hot">🔥 Popular</div>
                            <?php endif; ?>
                            
                            <div class="product-image">
                                <?php if (!empty($product['image'])): ?>
                                    <img src="assets/images/products/<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                                <?php else: ?>
                                    <img src="https://images.unsplash.com/photo-1589924691995-400dc9ecc119?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80" alt="Product">
                                <?php endif; ?>
                                
                                <div class="product-overlay">
                                    <button class="quick-view-btn" onclick="quickView(<?php echo $product['productID']; ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="wishlist-btn">
                                        <i class="far fa-heart"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="product-info">
                                <div class="product-category"><?php echo htmlspecialchars($product['category']); ?></div>
                                <h5 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h5>
                                <p class="product-brand"><?php echo htmlspecialchars($product['brand']); ?></p>
                                
                                <div class="product-rating">
                                    <div class="stars">
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
                                    </div>
                                    <span class="rating-text">(<?php echo $product['review_count']; ?>)</span>
                                </div>
                                
                                <?php if (!empty($product['weight'])): ?>
                                    <div class="product-weight"><?php echo htmlspecialchars($product['weight']); ?></div>
                                <?php endif; ?>
                                
                                <div class="product-price">
                                    <span class="current-price">Rs. <?php echo number_format($product['price'], 2); ?></span>
                                    <?php if (rand(1, 4) === 1): ?>
                                        <span class="original-price">Rs. <?php echo number_format($product['price'] * 1.2, 2); ?></span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="product-actions">
                                    <a href="product.php?id=<?php echo $product['productID']; ?>" class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-info-circle me-1"></i> Details
                                    </a>
                                    <?php if($product['stock'] > 0): ?>
                                        <button class="btn btn-primary-gradient btn-sm" onclick="addToCart(<?php echo $product['productID']; ?>)">
                                            <i class="fas fa-cart-plus me-1"></i> Add to Cart
                                        </button>
                                    <?php else: ?>
                                        <button class="btn btn-secondary btn-sm" disabled>
                                            <i class="fas fa-times me-1"></i> Out of Stock
                                        </button>
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

<!-- Modern Categories Showcase -->
<section class="categories-showcase">
    <div class="container">
        <div class="section-header text-center">
            <span class="section-badge">🐾 Categories</span>
            <h2>Shop by Pet Type</h2>
            <p>Find everything your pet needs in one place</p>
        </div>
        
        <div class="row g-4">
            <div class="col-lg-4 col-md-6">
                <div class="category-showcase-card dog-category">
                    <div class="category-overlay"></div>
                    <div class="category-content">
                        <div class="category-icon">🐕</div>
                        <h3>Dogs</h3>
                        <p>Premium food, toys & accessories</p>
                        <a href="shop.php?category=Dog Food" class="btn btn-light">Shop Now</a>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="category-showcase-card cat-category">
                    <div class="category-overlay"></div>
                    <div class="category-content">
                        <div class="category-icon">🐱</div>
                        <h3>Cats</h3>
                        <p>Nutrition & care for felines</p>
                        <a href="shop.php?category=Cat Food" class="btn btn-light">Shop Now</a>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="category-showcase-card bird-category">
                    <div class="category-overlay"></div>
                    <div class="category-content">
                        <div class="category-icon">🐦</div>
                        <h3>Birds</h3>
                        <p>Seeds, cages & bird care</p>
                        <a href="shop.php?category=Bird Food" class="btn btn-light">Shop Now</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
.shop-header-section {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 80px 0;
}

.shop-header-content {
    padding-right: 40px;
}

.shop-title {
    font-size: 3.5rem;
    font-weight: 800;
    line-height: 1.2;
    margin-bottom: 24px;
}

.shop-subtitle {
    font-size: 1.2rem;
    opacity: 0.9;
    margin-bottom: 0;
}

.shop-stats {
    display: flex;
    gap: 24px;
    justify-content: center;
}

.stat-card {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    border-radius: 20px;
    padding: 24px;
    text-align: center;
    border: 1px solid rgba(255, 255, 255, 0.2);
    flex: 1;
    max-width: 120px;
}

.stat-icon {
    font-size: 2rem;
    margin-bottom: 8px;
}

.stat-number {
    font-size: 1.8rem;
    font-weight: 700;
    display: block;
}

.stat-label {
    font-size: 0.9rem;
    opacity: 0.8;
}

.filter-section {
    padding: 40px 0;
    background: #f8f9ff;
}

.filter-card {
    background: white;
    border-radius: 20px;
    padding: 32px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(0, 0, 0, 0.05);
}

.filter-label {
    font-weight: 600;
    color: #374151;
    margin-bottom: 8px;
    display: block;
    font-size: 0.9rem;
}

.search-group {
    position: relative;
}

.search-icon {
    position: absolute;
    left: 16px;
    top: 50%;
    transform: translateY(-50%);
    color: #9ca3af;
    z-index: 2;
}

.search-input {
    padding: 12px 16px 12px 44px;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    font-size: 1rem;
    transition: all 0.3s ease;
}

.search-input:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.modern-select {
    padding: 12px 16px;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    transition: all 0.3s ease;
}

.modern-select:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.price-range-group {
    display: flex;
    align-items: center;
    gap: 8px;
}

.price-input {
    padding: 12px 16px;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    transition: all 0.3s ease;
}

.price-input:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.price-separator {
    color: #9ca3af;
    font-weight: 500;
}

.filter-btn {
    width: 48px;
    height: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
}

.products-section {
    padding: 80px 0;
    background: white;
}

.no-products-card {
    text-align: center;
    padding: 80px 40px;
    background: #f8f9ff;
    border-radius: 20px;
    border: 2px dashed #d1d5db;
}

.no-products-icon {
    font-size: 4rem;
    color: #9ca3af;
    margin-bottom: 24px;
}

.no-products-card h4 {
    color: #374151;
    margin-bottom: 16px;
}

.no-products-card p {
    color: #6b7280;
    margin-bottom: 32px;
}

.products-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 40px;
}

.products-header h3 {
    font-size: 2rem;
    font-weight: 700;
    color: #1e293b;
    margin: 0;
}

.product-count {
    color: #64748b;
    font-weight: 500;
    font-size: 1rem;
}

.reset-link {
    color: #667eea;
    text-decoration: none;
    font-weight: 500;
}

.reset-link:hover {
    color: #764ba2;
}

.product-badge.urgent {
    background: #ef4444;
}

.product-badge.hot {
    background: #f97316;
}

.product-brand {
    color: #9ca3af;
    font-size: 0.9rem;
    margin-bottom: 8px;
}

.product-weight {
    font-size: 0.8rem;
    color: #6b7280;
    margin-bottom: 12px;
    background: #f1f5f9;
    padding: 4px 8px;
    border-radius: 6px;
    display: inline-block;
}

.product-actions {
    display: flex;
    gap: 8px;
    margin-top: 16px;
}

.product-actions .btn {
    flex: 1;
    font-size: 0.85rem;
    padding: 8px 12px;
}

.categories-showcase {
    padding: 80px 0;
    background: #f8f9ff;
}

.category-showcase-card {
    height: 300px;
    border-radius: 20px;
    position: relative;
    overflow: hidden;
    cursor: pointer;
    transition: transform 0.3s ease;
    background-size: cover;
    background-position: center;
}

.category-showcase-card:hover {
    transform: scale(1.02);
}

.dog-category {
    background-image: linear-gradient(135deg, rgba(239, 68, 68, 0.8), rgba(249, 115, 22, 0.8)), url('https://images.unsplash.com/photo-1552053831-71594a27632d?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80');
}

.cat-category {
    background-image: linear-gradient(135deg, rgba(139, 92, 246, 0.8), rgba(236, 72, 153, 0.8)), url('https://images.unsplash.com/photo-1514888286974-6c03e2ca1dba?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80');
}

.bird-category {
    background-image: linear-gradient(135deg, rgba(34, 197, 94, 0.8), rgba(20, 184, 166, 0.8)), url('https://images.unsplash.com/photo-1452570053594-1b985d6ea890?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80');
}

.category-content {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    padding: 32px;
    color: white;
    z-index: 2;
}

.category-content .category-icon {
    font-size: 3rem;
    margin-bottom: 16px;
}

.category-content h3 {
    font-size: 1.8rem;
    font-weight: 700;
    margin-bottom: 12px;
}

.category-content p {
    margin-bottom: 20px;
    opacity: 0.9;
}

@media (max-width: 991px) {
    .shop-title {
        font-size: 2.5rem;
    }
    
    .shop-header-content {
        padding-right: 0;
        margin-bottom: 40px;
    }
    
    .shop-stats {
        justify-content: space-around;
    }
    
    .filter-card {
        padding: 24px;
    }
    
    .price-range-group {
        flex-direction: column;
        gap: 4px;
    }
    
    .price-separator {
        display: none;
    }
}

@media (max-width: 768px) {
    .product-actions {
        flex-direction: column;
    }
    
    .products-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 16px;
    }
}
</style>

<script>
function quickView(productId) {
    // Add quick view functionality
    console.log('Quick view for product:', productId);
}

// Wishlist functionality
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.wishlist-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const icon = this.querySelector('i');
            if (icon.classList.contains('far')) {
                icon.classList.remove('far');
                icon.classList.add('fas');
                this.style.color = '#ef4444';
            } else {
                icon.classList.remove('fas');
                icon.classList.add('far');
                this.style.color = '';
            }
        });
    });
});
</script>

<?php
// Include footer
include_once 'includes/footer.php';

// Close database connection
$conn->close();
?>