<?php
// Include header
include_once 'includes/header.php';

// Include database connection
require_once 'config/db_connect.php';

// Get featured products
$featured_products_sql = "SELECT p.*, 
        (SELECT AVG(r.rating) FROM reviews r WHERE r.productID = p.productID) as avg_rating,
        (SELECT COUNT(r.reviewID) FROM reviews r WHERE r.productID = p.productID) as review_count
        FROM pet_food_and_accessories p 
        ORDER BY p.productID DESC LIMIT 8";
$featured_products_result = $conn->query($featured_products_sql);
$featured_products = [];
while ($row = $featured_products_result->fetch_assoc()) {
    $featured_products[] = $row;
}

// Get featured pet sitters
$featured_sitters_sql = "SELECT ps.*, 
        (SELECT AVG(r.rating) FROM reviews r WHERE r.sitterID = ps.userID) as avg_rating,
        (SELECT COUNT(r.reviewID) FROM reviews r WHERE r.sitterID = ps.userID) as review_count
        FROM pet_sitter ps 
        WHERE ps.approval_status = 'Approved'
        ORDER BY avg_rating DESC, ps.fullName ASC LIMIT 6";
$featured_sitters_result = $conn->query($featured_sitters_sql);
$featured_sitters = [];
while ($row = $featured_sitters_result->fetch_assoc()) {
    $featured_sitters[] = $row;
}
?>

<!-- FIXED: Complete Hero Section with proper CSS -->
<section class="hero-section-modern">
    <div class="hero-particles"></div>
    <div class="container-fluid px-0">
        <div class="container">
            <div class="row align-items-center min-vh-100">
                <div class="col-lg-6">
                    <div class="hero-content">
                        <div class="hero-badge">
                            <span class="badge-icon">🐾</span>
                            #1 Pet Care Platform in Sri Lanka
                        </div>
                        <h1 class="hero-title">
                            Your Pet's <span class="text-gradient">Happiness</span><br>
                            is Our <span class="text-gradient">Priority</span>
                        </h1>
                        <p class="hero-description">
                            Discover premium pet products and connect with trusted pet sitters. 
                            Everything your furry friend needs, all in one place with free delivery across Colombo.
                        </p>
                        <div class="hero-stats">
                            <div class="stat-item">
                                <div class="stat-icon">💝</div>
                                <span class="stat-number">10K+</span>
                                <span class="stat-label">Happy Pets</span>
                            </div>
                            <div class="stat-item">
                                <div class="stat-icon">🛍️</div>
                                <span class="stat-number">500+</span>
                                <span class="stat-label">Products</span>
                            </div>
                            <div class="stat-item">
                                <div class="stat-icon">👥</div>
                                <span class="stat-number">200+</span>
                                <span class="stat-label">Pet Sitters</span>
                            </div>
                        </div>
                        <div class="hero-buttons">
                            <a href="shop.php" class="btn btn-primary-gradient btn-lg">
                                <i class="fas fa-shopping-cart me-2"></i>Shop Now
                            </a>
                            <a href="pet_sitters.php" class="btn btn-outline-light btn-lg">
                                <i class="fas fa-heart me-2"></i>Find Pet Sitter
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="hero-image-container">
                        <div class="hero-main-image">
                            <!-- FIXED: Complete image URL -->
                            <img src="https://images.unsplash.com/photo-1601758228041-f3b2795255f1?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1000&q=80" 
                                 alt="Happy pets with their owners" class="img-fluid hero-image">
                        </div>
                        <div class="hero-floating-elements">
                            <div class="floating-element floating-element-1">
                                <i class="fas fa-heart"></i>
                            </div>
                            <div class="floating-element floating-element-2">
                                <i class="fas fa-paw"></i>
                            </div>
                            <div class="floating-element floating-element-3">
                                <i class="fas fa-bone"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Features Section -->
<section class="features-section-modern">
    <div class="container">
        <div class="row text-center mb-5">
            <div class="col-12">
                <h2 class="section-title">Why Choose PetCare System?</h2>
                <p class="section-subtitle">Everything your pet needs in one trusted platform</p>
            </div>
        </div>
        <div class="row g-4">
            <div class="col-md-4">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <h4>Trusted & Safe</h4>
                    <p>All our pet sitters are verified and background-checked for your peace of mind.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-truck"></i>
                    </div>
                    <h4>Free Delivery</h4>
                    <p>Free delivery across Colombo for all orders above Rs. 2,500. Fast and reliable service.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <h4>24/7 Support</h4>
                    <p>Round-the-clock customer support to help you with any questions or concerns.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Featured Products Section -->
<?php if (!empty($featured_products)): ?>
<section class="featured-products-section">
    <div class="container">
        <div class="row mb-5">
            <div class="col-12 text-center">
                <h2 class="section-title">Featured Products</h2>
                <p class="section-subtitle">Premium quality products for your beloved pets</p>
            </div>
        </div>
        <div class="row g-4">
            <?php foreach (array_slice($featured_products, 0, 4) as $product): ?>
            <div class="col-lg-3 col-md-6">
                <div class="product-card">
                    <div class="product-image-wrapper">
                        <?php if (!empty($product['image']) && file_exists('assets/images/products/' . $product['image'])): ?>
                            <img src="assets/images/products/<?php echo htmlspecialchars($product['image']); ?>" 
                                 alt="<?php echo htmlspecialchars($product['name']); ?>" class="product-image">
                        <?php else: ?>
                            <div class="product-placeholder">
                                <i class="fas fa-box"></i>
                            </div>
                        <?php endif; ?>
                        <div class="product-overlay">
                            <a href="product_details.php?id=<?php echo $product['productID']; ?>" class="btn btn-primary btn-sm">
                                View Details
                            </a>
                        </div>
                    </div>
                    <div class="product-info">
                        <h5 class="product-name"><?php echo htmlspecialchars($product['name']); ?></h5>
                        <p class="product-brand"><?php echo htmlspecialchars($product['brand']); ?></p>
                        <div class="product-rating">
                            <?php if ($product['avg_rating']): ?>
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star <?php echo $i <= round($product['avg_rating']) ? 'text-warning' : 'text-muted'; ?>"></i>
                                <?php endfor; ?>
                                <span class="rating-text">(<?php echo $product['review_count']; ?>)</span>
                            <?php else: ?>
                                <span class="text-muted">No reviews yet</span>
                            <?php endif; ?>
                        </div>
                        <div class="product-price">Rs. <?php echo number_format($product['price'], 2); ?></div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="text-center mt-4">
            <a href="shop.php" class="btn btn-primary btn-lg">View All Products</a>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Featured Pet Sitters Section -->
<?php if (!empty($featured_sitters)): ?>
<section class="featured-sitters-section">
    <div class="container">
        <div class="row mb-5">
            <div class="col-12 text-center">
                <h2 class="section-title">Top Rated Pet Sitters</h2>
                <p class="section-subtitle">Trusted professionals who love caring for pets</p>
            </div>
        </div>
        <div class="row g-4">
            <?php foreach (array_slice($featured_sitters, 0, 3) as $sitter): ?>
            <div class="col-lg-4 col-md-6">
                <div class="sitter-card">
                    <div class="sitter-avatar">
                        <?php if (!empty($sitter['profileImage']) && file_exists('assets/images/sitters/' . $sitter['profileImage'])): ?>
                            <img src="assets/images/sitters/<?php echo htmlspecialchars($sitter['profileImage']); ?>" 
                                 alt="<?php echo htmlspecialchars($sitter['fullName']); ?>">
                        <?php else: ?>
                            <div class="avatar-placeholder">
                                <i class="fas fa-user"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="sitter-info">
                        <h5 class="sitter-name"><?php echo htmlspecialchars($sitter['fullName']); ?></h5>
                        <p class="sitter-location">
                            <i class="fas fa-map-marker-alt"></i>
                            <?php echo htmlspecialchars($sitter['location']); ?>
                        </p>
                        <div class="sitter-rating">
                            <?php if ($sitter['avg_rating']): ?>
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star <?php echo $i <= round($sitter['avg_rating']) ? 'text-warning' : 'text-muted'; ?>"></i>
                                <?php endfor; ?>
                                <span class="rating-text">(<?php echo $sitter['review_count']; ?> reviews)</span>
                            <?php else: ?>
                                <span class="text-muted">New sitter</span>
                            <?php endif; ?>
                        </div>
                        <div class="sitter-rate">Rs. <?php echo number_format($sitter['hourlyRate'], 2); ?>/hour</div>
                        <a href="sitter_profile.php?id=<?php echo $sitter['userID']; ?>" class="btn btn-outline-primary w-100 mt-3">
                            View Profile
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="text-center mt-4">
            <a href="pet_sitters.php" class="btn btn-primary btn-lg">Find More Sitters</a>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- FIXED: Complete CSS Styles for Hero Section -->
<style>
/* Hero Section Styles */
.hero-section-modern {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
    position: relative;
    overflow: hidden;
    display: flex;
    align-items: center;
}

.hero-particles {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="20" cy="20" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="80" cy="40" r="0.5" fill="rgba(255,255,255,0.1)"/><circle cx="40" cy="80" r="1.5" fill="rgba(255,255,255,0.1)"/></svg>');
    animation: float 20s infinite linear;
}

@keyframes float {
    0% { transform: translateY(0px) rotate(0deg); }
    100% { transform: translateY(-20px) rotate(360deg); }
}

.hero-content {
    color: white;
    z-index: 2;
    position: relative;
}

.hero-badge {
    background: rgba(255, 255, 255, 0.2);
    padding: 12px 24px;
    border-radius: 50px;
    font-size: 0.9rem;
    font-weight: 600;
    margin-bottom: 20px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    backdrop-filter: blur(10px);
}

.badge-icon {
    font-size: 1.2rem;
}

.hero-title {
    font-size: 3.5rem;
    font-weight: 800;
    line-height: 1.2;
    margin-bottom: 24px;
}

.text-gradient {
    background: linear-gradient(135deg, #ffd89b 0%, #19547b 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.hero-description {
    font-size: 1.2rem;
    opacity: 0.9;
    margin-bottom: 32px;
    line-height: 1.6;
}

.hero-stats {
    display: flex;
    gap: 32px;
    margin-bottom: 40px;
}

.stat-item {
    text-align: center;
}

.stat-icon {
    font-size: 2rem;
    margin-bottom: 8px;
}

.stat-number {
    display: block;
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 4px;
}

.stat-label {
    font-size: 0.9rem;
    opacity: 0.8;
}

.hero-buttons {
    display: flex;
    gap: 16px;
}

.btn-primary-gradient {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    padding: 14px 28px;
    border-radius: 12px;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-primary-gradient:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
}

.btn-outline-light {
    border: 2px solid rgba(255, 255, 255, 0.3);
    color: white;
    padding: 12px 26px;
    border-radius: 12px;
    font-weight: 600;
    background: transparent;
    transition: all 0.3s ease;
}

.btn-outline-light:hover {
    background: rgba(255, 255, 255, 0.1);
    border-color: rgba(255, 255, 255, 0.5);
    color: white;
    transform: translateY(-2px);
}

.hero-image-container {
    position: relative;
    z-index: 2;
}

.hero-main-image {
    position: relative;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3);
}

.hero-image {
    width: 100%;
    height: auto;
    border-radius: 20px;
}

.hero-floating-elements {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    pointer-events: none;
}

.floating-element {
    position: absolute;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 50%;
    width: 60px;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.5rem;
    backdrop-filter: blur(10px);
    animation: floatUpDown 3s ease-in-out infinite;
}

.floating-element-1 {
    top: 20%;
    right: -30px;
    animation-delay: 0s;
}

.floating-element-2 {
    top: 50%;
    left: -30px;
    animation-delay: 1s;
}

.floating-element-3 {
    bottom: 20%;
    right: -20px;
    animation-delay: 2s;
}

@keyframes floatUpDown {
    0%, 100% { transform: translateY(0px); }
    50% { transform: translateY(-20px); }
}

/* Features Section */
.features-section-modern {
    padding: 80px 0;
    background: white;
}

.section-title {
    font-size: 2.5rem;
    font-weight: 700;
    color: #2d3748;
    margin-bottom: 16px;
}

.section-subtitle {
    font-size: 1.2rem;
    color: #718096;
    margin-bottom: 0;
}

.feature-card {
    text-align: center;
    padding: 40px 20px;
    border-radius: 20px;
    background: white;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
    height: 100%;
}

.feature-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.15);
}

.feature-icon {
    width: 80px;
    height: 80px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 24px;
    color: white;
    font-size: 2rem;
}

.feature-card h4 {
    font-size: 1.5rem;
    font-weight: 600;
    color: #2d3748;
    margin-bottom: 16px;
}

.feature-card p {
    color: #718096;
    line-height: 1.6;
}

/* Product and Sitter Sections */
.featured-products-section,
.featured-sitters-section {
    padding: 80px 0;
    background: #f8f9ff;
}

.featured-sitters-section {
    background: white;
}

.product-card,
.sitter-card {
    background: white;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
    height: 100%;
}

.product-card:hover,
.sitter-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.15);
}

.product-image-wrapper {
    position: relative;
    height: 200px;
    overflow: hidden;
}

.product-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.product-placeholder {
    width: 100%;
    height: 100%;
    background: #f1f5f9;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #94a3b8;
    font-size: 3rem;
}

.product-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: all 0.3s ease;
}

.product-card:hover .product-overlay {
    opacity: 1;
}

.product-info,
.sitter-info {
    padding: 24px;
}

.product-name,
.sitter-name {
    font-size: 1.2rem;
    font-weight: 600;
    color: #2d3748;
    margin-bottom: 8px;
}

.product-brand {
    color: #718096;
    font-size: 0.9rem;
    margin-bottom: 12px;
}

.product-price,
.sitter-rate {
    font-size: 1.3rem;
    font-weight: 700;
    color: #667eea;
}

.sitter-avatar {
    text-align: center;
    padding: 24px 24px 0;
}

.sitter-avatar img {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    object-fit: cover;
    border: 4px solid #f1f5f9;
}

.avatar-placeholder {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    background: #f1f5f9;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #94a3b8;
    font-size: 2rem;
    border: 4px solid #e2e8f0;
}

.sitter-location {
    color: #718096;
    font-size: 0.9rem;
    margin-bottom: 12px;
}

.product-rating,
.sitter-rating {
    margin-bottom: 12px;
}

.rating-text {
    font-size: 0.9rem;
    color: #718096;
    margin-left: 8px;
}

/* Responsive Design */
@media (max-width: 768px) {
    .hero-title {
        font-size: 2.5rem;
    }
    
    .hero-stats {
        flex-direction: column;
        gap: 16px;
        align-items: center;
    }
    
    .hero-buttons {
        flex-direction: column;
        align-items: center;
    }
    
    .hero-buttons .btn {
        width: 100%;
        max-width: 300px;
    }
    
    .section-title {
        font-size: 2rem;
    }
    
    .floating-element {
        display: none;
    }
}

@media (max-width: 576px) {
    .hero-title {
        font-size: 2rem;
    }
    
    .feature-card {
        padding: 30px 15px;
    }
    
    .hero-stats {
        gap: 12px;
    }
}
</style>

<?php
// Include footer
include_once 'includes/footer.php';

// Close database connection
$conn->close();
?>