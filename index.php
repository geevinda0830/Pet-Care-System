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

<!-- Hero Section -->
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
                        <img src="https://images.unsplash.com/photo-1601758228041-f3b2795255f1?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1000&q=80" alt="Happy Pets" class="hero-pet-image">
                    </div>
                    <div class="floating-card card-1">
                        <div class="card-icon">🐕</div>
                        <div class="card-content">
                            <strong>Dog Walking</strong>
                            <small>From $15/hour</small>
                        </div>
                    </div>
                    <div class="floating-card card-2">
                        <div class="card-icon">🛍️</div>
                        <div class="card-content">
                            <strong>Premium Food</strong>
                            <small>Free Delivery</small>
                        </div>
                    </div>
                    <div class="floating-card card-3">
                        <div class="card-icon">⭐</div>
                        <div class="card-content">
                            <strong>5-Star Rating</strong>
                            <small>Trusted Service</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Quick Actions -->
<section class="quick-actions-section">
    <div class="container-fluid px-0">
        <div class="container">
            <div class="row g-4">
            <div class="col-lg-3 col-md-6">
                <div class="quick-action-card purple">
                    <div class="action-icon">
                        <i class="fas fa-shopping-bag"></i>
                    </div>
                    <h4>Shop Products</h4>
                    <p>Premium pet food, toys & accessories delivered to your door</p>
                    <a href="shop.php" class="action-btn">Browse Products →</a>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="quick-action-card blue">
                    <div class="action-icon">
                        <i class="fas fa-user-friends"></i>
                    </div>
                    <h4>Find Pet Sitters</h4>
                    <p>Trusted & verified pet care providers in your area</p>
                    <a href="pet_sitters.php" class="action-btn">Find Sitters →</a>
                </div>
            </div> 
            <div class="col-lg-3 col-md-6">
                <div class="quick-action-card green">
                    <div class="action-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <h4>Book Services</h4>
                    <p>Schedule pet sitting, walking & grooming services</p>
                    <a href="pet_sitters.php" class="action-btn">Book Now →</a>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="quick-action-card orange">
                    <div class="action-icon">
                        <i class="fas fa-truck"></i>
                    </div>
                    <h4>Free Delivery</h4>
                    <p>Fast & free shipping on orders over Rs. 5,000</p>
                    <a href="shop.php" class="action-btn">Order Now →</a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Featured Products -->
<section class="featured-products-section">
    <div class="container-fluid px-0">
        <div class="container">
            <div class="section-header text-center">
            <span class="section-badge">🏆 Best Sellers</span>
            <h2 class="section-title">Featured <span class="text-gradient">Products</span></h2>
            <p class="section-subtitle">Handpicked products your pets will absolutely love</p>
        </div>
        
        <div class="row g-4">
            <!-- Product 1 -->
            <div class="col-lg-3 col-md-6">
                <div class="product-card-modern">
                    <div class="product-badge">🔥 Hot</div>
                    <div class="product-image">
                        <img src="https://images.unsplash.com/photo-1589924691995-400dc9ecc119?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80" alt="Premium Dog Food">
                        <div class="product-overlay">
                            <button class="quick-view-btn" onclick="addToCart(1)">
                                <i class="fas fa-shopping-cart"></i>
                            </button>
                        </div>
                    </div>
                    <div class="product-info">
                        <div class="product-category">Dog Food</div>
                        <h5 class="product-title">Premium Dry Dog Food</h5>
                        <div class="product-rating">
                            <div class="stars">
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                            </div>
                            <span class="rating-text">(124)</span>
                        </div>
                        <div class="product-price">
                            <span class="current-price">Rs. 2,999</span>
                            <span class="original-price">Rs. 3,999</span>
                        </div>
                        <button class="btn btn-primary-gradient w-100" onclick="addToCart(1)">Add to Cart</button>
                    </div>
                </div>
            </div>

            <!-- Product 2 -->
            <div class="col-lg-3 col-md-6">
                <div class="product-card-modern">
                    <div class="product-badge new">✨ New</div>
                    <div class="product-image">
                        <img src="https://images.unsplash.com/photo-1541781408260-3c61143b63d5?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80" alt="Cat Toy">
                        <div class="product-overlay">
                            <button class="quick-view-btn" onclick="addToCart(2)">
                                <i class="fas fa-shopping-cart"></i>
                            </button>
                        </div>
                    </div>
                    <div class="product-info">
                        <div class="product-category">Cat Toys</div>
                        <h5 class="product-title">Interactive Cat Toy Set</h5>
                        <div class="product-rating">
                            <div class="stars">
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="far fa-star"></i>
                            </div>
                            <span class="rating-text">(89)</span>
                        </div>
                        <div class="product-price">
                            <span class="current-price">Rs. 1,299</span>
                        </div>
                        <button class="btn btn-primary-gradient w-100" onclick="addToCart(2)">Add to Cart</button>
                    </div>
                </div>
            </div>

            <!-- Product 3 -->
            <div class="col-lg-3 col-md-6">
                <div class="product-card-modern">
                    <div class="product-image">
                        <img src="https://images.unsplash.com/photo-1583337687581-3f6ba0a9c709?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80" alt="Pet Accessories">
                        <div class="product-overlay">
                            <button class="quick-view-btn" onclick="addToCart(3)">
                                <i class="fas fa-shopping-cart"></i>
                            </button>
                        </div>
                    </div>
                    <div class="product-info">
                        <div class="product-category">Accessories</div>
                        <h5 class="product-title">Stylish Pet Collar</h5>
                        <div class="product-rating">
                            <div class="stars">
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                            </div>
                            <span class="rating-text">(67)</span>
                        </div>
                        <div class="product-price">
                            <span class="current-price">Rs. 899</span>
                        </div>
                        <button class="btn btn-primary-gradient w-100" onclick="addToCart(3)">Add to Cart</button>
                    </div>
                </div>
            </div>

            <!-- Product 4 -->
            <div class="col-lg-3 col-md-6">
                <div class="product-card-modern">
                    <div class="product-image">
                        <img src="https://images.unsplash.com/photo-1548199973-03cce0bbc87b?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80" alt="Pet Care">
                        <div class="product-overlay">
                            <button class="quick-view-btn" onclick="addToCart(4)">
                                <i class="fas fa-shopping-cart"></i>
                            </button>
                        </div>
                    </div>
                    <div class="product-info">
                        <div class="product-category">Health Care</div>
                        <h5 class="product-title">Pet Grooming Kit</h5>
                        <div class="product-rating">
                            <div class="stars">
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star-half-alt"></i>
                            </div>
                            <span class="rating-text">(156)</span>
                        </div>
                        <div class="product-price">
                            <span class="current-price">Rs. 2,499</span>
                        </div>
                        <button class="btn btn-primary-gradient w-100" onclick="addToCart(4)">Add to Cart</button>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="text-center mt-5">
            <a href="shop.php" class="btn btn-outline-primary btn-lg">
                View All Products <i class="fas fa-arrow-right ms-2"></i>
            </a>
        </div>
    </div>
</section>

<!-- Pet Categories -->
<section class="categories-section">
    <div class="container">
        <div class="section-header text-center">
            <span class="section-badge">🐾 Categories</span>
            <h2 class="section-title">Shop by <span class="text-gradient">Pet Type</span></h2>
            <p class="section-subtitle">Find everything your pet needs in one place</p>
        </div>
        
        <div class="row g-4">
            <div class="col-lg-4 col-md-6">
                <div class="category-card" style="background-image: linear-gradient(135deg, rgba(239, 68, 68, 0.8), rgba(249, 115, 22, 0.8)), url('https://images.unsplash.com/photo-1552053831-71594a27632d?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80');">
                    <div class="category-overlay"></div>
                    <div class="category-content">
                        <div class="category-icon">🐕</div>
                        <h3>Dogs</h3>
                        <p>Food, toys, accessories & more for your loyal companion</p>
                        <a href="shop.php?category=Dog" class="btn btn-light">Shop Dogs</a>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="category-card" style="background-image: linear-gradient(135deg, rgba(139, 92, 246, 0.8), rgba(236, 72, 153, 0.8)), url('https://images.unsplash.com/photo-1514888286974-6c03e2ca1dba?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80');">
                    <div class="category-overlay"></div>
                    <div class="category-content">
                        <div class="category-icon">🐱</div>
                        <h3>Cats</h3>
                        <p>Premium cat food & accessories for your feline friend</p>
                        <a href="shop.php?category=Cat" class="btn btn-light">Shop Cats</a>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="category-card" style="background-image: linear-gradient(135deg, rgba(34, 197, 94, 0.8), rgba(20, 184, 166, 0.8)), url('https://images.unsplash.com/photo-1452570053594-1b985d6ea890?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80');">
                    <div class="category-overlay"></div>
                    <div class="category-content">
                        <div class="category-icon">🐦</div>
                        <h3>Birds</h3>
                        <p>Seeds, cages & bird care items for your chirpy friends</p>
                        <a href="shop.php?category=Bird" class="btn btn-light">Shop Birds</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Pet Sitters Section -->
<section class="pet-sitters-section">
    <div class="container">
        <div class="section-header text-center">
            <span class="section-badge">💖 Trusted Care</span>
            <h2 class="section-title">Meet Our <span class="text-gradient">Pet Sitters</span></h2>
            <p class="section-subtitle">Verified and loving pet care professionals ready to help</p>
        </div>
        
        <div class="row g-4">
            <!-- Sitter 1 -->
            <div class="col-lg-4 col-md-6">
                <div class="sitter-card-modern">
                    <div class="sitter-image">
                        <img src="https://images.unsplash.com/photo-1494790108755-2616c96e5e21?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80" alt="Sarah Johnson">
                        <div class="sitter-badge verified">✓ Verified</div>
                    </div>
                    <div class="sitter-info">
                        <h5 class="sitter-name">Sarah Johnson</h5>
                        <div class="sitter-rating">
                            <div class="stars">
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                            </div>
                            <span class="rating-text">5.0 (28 reviews)</span>
                        </div>
                        <p class="sitter-service">🐕 Dog Walking • 🏠 Pet Sitting</p>
                        <div class="sitter-location">📍 Colombo 07</div>
                        <div class="sitter-price">Rs. 2,500/hour</div>
                        <a href="pet_sitters.php" class="btn btn-primary-gradient w-100">View Profile</a>
                    </div>
                </div>
            </div>

            <!-- Sitter 2 -->
            <div class="col-lg-4 col-md-6">
                <div class="sitter-card-modern">
                    <div class="sitter-image">
                        <img src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80" alt="Michael Davis">
                        <div class="sitter-badge verified">✓ Verified</div>
                    </div>
                    <div class="sitter-info">
                        <h5 class="sitter-name">Michael Davis</h5>
                        <div class="sitter-rating">
                            <div class="stars">
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                            </div>
                            <span class="rating-text">5.0 (42 reviews)</span>
                        </div>
                        <p class="sitter-service">🐱 Cat Care • 🛁 Pet Grooming</p>
                        <div class="sitter-location">📍 Kandy</div>
                        <div class="sitter-price">Rs. 3,000/hour</div>
                        <a href="pet_sitters.php" class="btn btn-primary-gradient w-100">View Profile</a>
                    </div>
                </div>
            </div>

            <!-- Sitter 3 -->
            <div class="col-lg-4 col-md-6">
                <div class="sitter-card-modern">
                    <div class="sitter-image">
                        <img src="https://images.unsplash.com/photo-1438761681033-6461ffad8d80?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80" alt="Emily Wilson">
                        <div class="sitter-badge verified">✓ Verified</div>
                    </div>
                    <div class="sitter-info">
                        <h5 class="sitter-name">Emily Wilson</h5>
                        <div class="sitter-rating">
                            <div class="stars">
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="far fa-star"></i>
                            </div>
                            <span class="rating-text">4.8 (15 reviews)</span>
                        </div>
                        <p class="sitter-service">🏥 Pet Health • 💊 Medication</p>
                        <div class="sitter-location">📍 Galle</div>
                        <div class="sitter-price">Rs. 2,800/hour</div>
                        <a href="pet_sitters.php" class="btn btn-primary-gradient w-100">View Profile</a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="text-center mt-5">
            <a href="pet_sitters.php" class="btn btn-outline-primary btn-lg">
                View All Pet Sitters <i class="fas fa-arrow-right ms-2"></i>
            </a>
        </div>
    </div>
</section>

<!-- Why Choose Us -->
<section class="why-choose-section">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <div class="why-choose-content">
                    <span class="section-badge">🌟 Why Choose Us</span>
                    <h2 class="section-title">We Care About Your <span class="text-gradient">Pet's Happiness</span></h2>
                    <p class="section-subtitle">Trusted by thousands of pet owners across Sri Lanka for quality and reliability</p>
                    
                    <div class="feature-list">
                        <div class="feature-item">
                            <div class="feature-icon bg-gradient-1">
                                <i class="fas fa-shield-alt"></i>
                            </div>
                            <div class="feature-content">
                                <h5>100% Verified Pet Sitters</h5>
                                <p>All our pet sitters are background-checked, trained, and verified for your complete peace of mind.</p>
                            </div>
                        </div>
                        <div class="feature-item">
                            <div class="feature-icon bg-gradient-2">
                                <i class="fas fa-heart"></i>
                            </div>
                            <div class="feature-content">
                                <h5>Premium Quality Products</h5>
                                <p>Carefully curated selection of high-quality, safe pet food and accessories from trusted brands.</p>
                            </div>
                        </div>
                        <div class="feature-item">
                            <div class="feature-icon bg-gradient-3">
                                <i class="fas fa-truck"></i>
                            </div>
                            <div class="feature-content">
                                <h5>Fast & Free Delivery</h5>
                                <p>Same-day delivery on orders over Rs. 5,000 within Colombo. Island-wide delivery available.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="why-choose-image">
                    <img src="https://images.unsplash.com/photo-1601758228041-f3b2795255f1?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80" alt="Happy Pets" class="img-fluid rounded-3">
                    <div class="floating-stat">
                        <div class="stat-icon">😊</div>
                        <div class="stat-number">98%</div>
                        <div class="stat-label">Happy Customers</div>
                    </div>
                    <div class="floating-stat-2">
                        <div class="stat-icon">🏆</div>
                        <div class="stat-number">5⭐</div>
                        <div class="stat-label">Average Rating</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Testimonials -->
<section class="testimonials-section">
    <div class="container">
        <div class="section-header text-center">
            <span class="section-badge">💬 Reviews</span>
            <h2 class="section-title">What Pet Owners <span class="text-gradient">Say About Us</span></h2>
            <p class="section-subtitle">Real stories from real pet parents who trust us</p>
        </div>
        
        <div class="row g-4">
            <div class="col-lg-4 col-md-6">
                <div class="testimonial-card">
                    <div class="testimonial-rating">
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                    </div>
                    <p class="testimonial-text">"Amazing service! Sarah took excellent care of my dog while I was away. The daily updates with photos gave me so much peace of mind. Highly recommended!"</p>
                    <div class="testimonial-author">
                        <img src="https://images.unsplash.com/photo-1494790108755-2616c96e5e21?ixlib=rb-4.0.3&auto=format&fit=crop&w=100&q=80" alt="Amanda Thompson" class="author-image">
                        <div class="author-info">
                            <h6 class="author-name">Amanda Thompson</h6>
                            <span class="author-role">🐕 Dog Owner, Colombo</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="testimonial-card">
                    <div class="testimonial-rating">
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                    </div>
                    <p class="testimonial-text">"The premium cat food I ordered arrived quickly and my picky eater absolutely loves it! Great quality products and excellent customer service. Will order again!"</p>
                    <div class="testimonial-author">
                        <img src="https://images.unsplash.com/photo-1438761681033-6461ffad8d80?ixlib=rb-4.0.3&auto=format&fit=crop&w=100&q=80" alt="Jennifer Lee" class="author-image">
                        <div class="author-info">
                            <h6 class="author-name">Jennifer Lee</h6>
                            <span class="author-role">🐱 Cat Owner, Kandy</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="testimonial-card">
                    <div class="testimonial-rating">
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                    </div>
                    <p class="testimonial-text">"Professional and reliable! Michael's dog walking service is fantastic. My energetic Lab gets the exercise he needs, and I get peace of mind."</p>
                    <div class="testimonial-author">
                        <img src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?ixlib=rb-4.0.3&auto=format&fit=crop&w=100&q=80" alt="Robert Johnson" class="author-image">
                        <div class="author-info">
                            <h6 class="author-name">Robert Johnson</h6>
                            <span class="author-role">🐕 Dog Owner, Galle</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Newsletter CTA -->
<section class="newsletter-section-improved">
    <div class="container">
        <div class="newsletter-card-improved">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <div class="newsletter-content-improved">
                        <div class="newsletter-icon-improved">🎉</div>
                        <h3>Get 10% Off Your First Order!</h3>
                        <p>Subscribe to our newsletter for exclusive deals, pet care tips, and new product updates. Join over 10,000 happy pet parents!</p>
                        <div class="newsletter-benefits-improved">
                            <span class="benefit-improved">✨ Exclusive Discounts</span>
                            <span class="benefit-improved">🐾 Pet Care Tips</span>
                            <span class="benefit-improved">🆕 New Product Alerts</span>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <form class="newsletter-form-improved">
                        <div class="input-group newsletter-input-group">
                            <input type="email" class="form-control newsletter-input-improved" placeholder="Enter your email address" required>
                            <button class="btn newsletter-btn-improved" type="submit">
                                Subscribe Now <i class="fas fa-paper-plane ms-1"></i>
                            </button>
                        </div>
                        <small class="form-text-improved">*No spam, unsubscribe anytime</small>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
.newsletter-section-improved {
    padding: 80px 0;
    background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
    position: relative;
}

.newsletter-card-improved {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    border-radius: 24px;
    padding: 60px 40px;
    border: 1px solid rgba(255, 255, 255, 0.2);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
}

.newsletter-content-improved {
    color: #1e293b;
}

.newsletter-icon-improved {
    font-size: 3rem;
    margin-bottom: 24px;
    display: block;
}

.newsletter-content-improved h3 {
    font-size: 2.2rem;
    font-weight: 700;
    margin-bottom: 16px;
    color: #1e293b;
}

.newsletter-content-improved p {
    font-size: 1.1rem;
    margin-bottom: 24px;
    color: #4b5563;
    line-height: 1.6;
}

.newsletter-benefits-improved {
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
    margin-bottom: 0;
}

.benefit-improved {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 0.9rem;
    font-weight: 500;
}

.newsletter-form-improved {
    padding-left: 0;
}

.newsletter-input-group {
    background: #f8f9fa;
    border-radius: 50px;
    padding: 4px;
    margin-bottom: 12px;
    border: 2px solid #e9ecef;
}

.newsletter-input-improved {
    border: none;
    background: transparent;
    padding: 12px 20px;
    font-size: 1rem;
    color: #495057;
}

.newsletter-input-improved:focus {
    box-shadow: none;
    outline: none;
    background: transparent;
}

.newsletter-input-improved::placeholder {
    color: #6c757d;
}

.newsletter-btn-improved {
    border-radius: 50px;
    padding: 12px 24px;
    font-weight: 600;
    background: linear-gradient(135deg, #667eea, #764ba2);
    border: none;
    color: white;
    transition: all 0.3s ease;
}

.newsletter-btn-improved:hover {
    background: linear-gradient(135deg, #5a6fd8, #6b4190);
    transform: translateY(-1px);
    color: white;
}

.form-text-improved {
    color: #6c757d;
    text-align: center;
    font-size: 0.85rem;
}

@media (max-width: 991px) {
    .newsletter-form-improved {
        padding-left: 0;
        margin-top: 40px;
        text-align: center;
    }
    
    .newsletter-benefits-improved {
        justify-content: center;
    }
}

@media (max-width: 768px) {
    .newsletter-input-group {
        flex-direction: column;
        padding: 8px;
    }
    
    .newsletter-input-improved {
        border-radius: 12px;
        margin-bottom: 12px;
        background: white;
        border: 1px solid #dee2e6;
    }
    
    .newsletter-btn-improved {
        border-radius: 12px;
        width: 100%;
    }
}
</style>

<?php
// Include footer
include_once 'includes/footer.php';

// Close database connection
$conn->close();
?>