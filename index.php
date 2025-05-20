<?php
// Include header
include_once 'includes/header.php';
?>

<!-- Hero Section -->
<section class="hero-section">
    <div class="container">
        <h1>Welcome to Pet Care & Sitting System</h1>
        <p>Your trusted partner for all pet care needs. We provide reliable pet sitting services and quality pet products.</p>
        <div class="hero-buttons">
            <a href="pet_sitters.php" class="btn btn-primary btn-lg me-2">Find a Pet Sitter</a>
            <a href="shop.php" class="btn btn-success btn-lg">Shop Pet Products</a>
        </div>
    </div>
</section>

<!-- Features Section -->
<section class="features-section py-5">
    <div class="container">
        <div class="section-title">
            <h2>Our Services</h2>
            <p>We offer a wide range of services to care for your beloved pets</p>
        </div>
        
        <div class="row">
            <div class="col-md-4">
                <div class="feature-box fade-in">
                    <div class="feature-icon">
                        <i class="fas fa-home"></i>
                    </div>
                    <h3>Pet Sitting</h3>
                    <p>Our experienced pet sitters will take care of your pets in the comfort of your home while you're away.</p>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="feature-box fade-in">
                    <div class="feature-icon">
                        <i class="fas fa-walking"></i>
                    </div>
                    <h3>Dog Walking</h3>
                    <p>Regular exercise is important for your dog's health. Our pet sitters offer dog walking services tailored to your pet's needs.</p>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="feature-box fade-in">
                    <div class="feature-icon">
                        <i class="fas fa-hospital"></i>
                    </div>
                    <h3>Pet Boarding</h3>
                    <p>When you need to leave town, our pet sitters can provide comfortable boarding for your pets in their homes.</p>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="feature-box fade-in">
                    <div class="feature-icon">
                        <i class="fas fa-shopping-basket"></i>
                    </div>
                    <h3>Pet Shop</h3>
                    <p>Browse our online store for a wide selection of pet food, toys, accessories, and more for your furry friends.</p>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="feature-box fade-in">
                    <div class="feature-icon">
                        <i class="fas fa-syringe"></i>
                    </div>
                    <h3>Health Support</h3>
                    <p>Our sitters can administer medications and provide basic health support for pets with special needs.</p>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="feature-box fade-in">
                    <div class="feature-icon">
                        <i class="fas fa-shower"></i>
                    </div>
                    <h3>Grooming</h3>
                    <p>Some of our sitters offer basic grooming services to keep your pets clean and comfortable.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- How It Works -->
<section class="how-it-works-section py-5 bg-light-gray">
    <div class="container">
        <div class="section-title">
            <h2>How It Works</h2>
            <p>Finding the perfect pet sitter is easy with our simple process</p>
        </div>
        
        <div class="row">
            <div class="col-md-3">
                <div class="text-center mb-4">
                    <div class="mb-3">
                        <i class="fas fa-search fa-3x text-primary"></i>
                    </div>
                    <h4>Find a Sitter</h4>
                    <p>Browse our network of professional pet sitters in your area.</p>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="text-center mb-4">
                    <div class="mb-3">
                        <i class="fas fa-calendar-alt fa-3x text-primary"></i>
                    </div>
                    <h4>Book a Service</h4>
                    <p>Select the dates and services you need for your pet.</p>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="text-center mb-4">
                    <div class="mb-3">
                        <i class="fas fa-credit-card fa-3x text-primary"></i>
                    </div>
                    <h4>Make Payment</h4>
                    <p>Securely pay for your booking through our platform.</p>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="text-center mb-4">
                    <div class="mb-3">
                        <i class="fas fa-heart fa-3x text-primary"></i>
                    </div>
                    <h4>Relax & Enjoy</h4>
                    <p>Rest assured your pet is in good hands while you're away.</p>
                </div>
            </div>
        </div>
        
        <div class="text-center mt-4">
            <a href="pet_sitters.php" class="btn btn-primary btn-lg">Find a Pet Sitter Now</a>
        </div>
    </div>
</section>

<!-- Featured Pet Sitters -->
<section class="featured-sitters-section py-5">
    <div class="container">
        <div class="section-title">
            <h2>Featured Pet Sitters</h2>
            <p>Meet some of our top-rated pet sitters</p>
        </div>
        
        <div class="row">
            <!-- This would normally be populated from the database -->
            <!-- Pet Sitter 1 -->
            <div class="col-md-4">
                <div class="card sitter-card">
                    <img src="assets/images/sitter-placeholder.jpg" class="card-img-top" alt="Pet Sitter">
                    <div class="card-body">
                        <h5 class="card-title">Sarah Johnson</h5>
                        <div class="rating mb-2">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star-half-alt"></i>
                            <span class="ms-1">4.5 (28 reviews)</span>
                        </div>
                        <p class="card-text">Professional dog walker and pet sitter with 5 years of experience. Specializes in dog walking and overnight stays.</p>
                        <p class="price">$25/hour</p>
                        <a href="#" class="btn btn-primary">View Profile</a>
                    </div>
                </div>
            </div>
            
            <!-- Pet Sitter 2 -->
            <div class="col-md-4">
                <div class="card sitter-card">
                    <img src="assets/images/sitter-placeholder.jpg" class="card-img-top" alt="Pet Sitter">
                    <div class="card-body">
                        <h5 class="card-title">Michael Davis</h5>
                        <div class="rating mb-2">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <span class="ms-1">5.0 (42 reviews)</span>
                        </div>
                        <p class="card-text">Certified pet caregiver with 8 years of experience. Experienced with cats, dogs, and small pets. Offers boarding services.</p>
                        <p class="price">$30/hour</p>
                        <a href="#" class="btn btn-primary">View Profile</a>
                    </div>
                </div>
            </div>
            
            <!-- Pet Sitter 3 -->
            <div class="col-md-4">
                <div class="card sitter-card">
                    <img src="assets/images/sitter-placeholder.jpg" class="card-img-top" alt="Pet Sitter">
                    <div class="card-body">
                        <h5 class="card-title">Emily Wilson</h5>
                        <div class="rating mb-2">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="far fa-star"></i>
                            <span class="ms-1">4.0 (15 reviews)</span>
                        </div>
                        <p class="card-text">Veterinary assistant offering pet sitting services. Specializes in medication administration and special needs pets.</p>
                        <p class="price">$28/hour</p>
                        <a href="#" class="btn btn-primary">View Profile</a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="text-center mt-4">
            <a href="pet_sitters.php" class="btn btn-outline-primary">View All Pet Sitters</a>
        </div>
    </div>
</section>

<!-- Featured Products -->
<section class="featured-products-section py-5 bg-light-gray">
    <div class="container">
        <div class="section-title">
            <h2>Featured Products</h2>
            <p>Check out these popular items from our pet store</p>
        </div>
        
        <div class="row">
            <!-- This would normally be populated from the database -->
            <!-- Product 1 -->
            <div class="col-md-3">
                <div class="card product-card">
                    <img src="assets/images/product-placeholder.jpg" class="card-img-top" alt="Pet Product">
                    <div class="card-body">
                        <h5 class="card-title">Premium Dog Food</h5>
                        <div class="rating mb-2">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star-half-alt"></i>
                        </div>
                        <p class="price">$25.99</p>
                        <a href="#" class="btn btn-sm btn-primary">View Details</a>
                        <a href="#" class="btn btn-sm btn-success">Add to Cart</a>
                    </div>
                </div>
            </div>
            
            <!-- Product 2 -->
            <div class="col-md-3">
                <div class="card product-card">
                    <img src="assets/images/product-placeholder.jpg" class="card-img-top" alt="Pet Product">
                    <div class="card-body">
                        <h5 class="card-title">Cat Scratching Post</h5>
                        <div class="rating mb-2">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="far fa-star"></i>
                        </div>
                        <p class="price">$34.99</p>
                        <a href="#" class="btn btn-sm btn-primary">View Details</a>
                        <a href="#" class="btn btn-sm btn-success">Add to Cart</a>
                    </div>
                </div>
            </div>
            
            <!-- Product 3 -->
            <div class="col-md-3">
                <div class="card product-card">
                    <img src="assets/images/product-placeholder.jpg" class="card-img-top" alt="Pet Product">
                    <div class="card-body">
                        <h5 class="card-title">Dog Chew Toy</h5>
                        <div class="rating mb-2">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                        </div>
                        <p class="price">$12.99</p>
                        <a href="#" class="btn btn-sm btn-primary">View Details</a>
                        <a href="#" class="btn btn-sm btn-success">Add to Cart</a>
                    </div>
                </div>
            </div>
            
            <!-- Product 4 -->
            <div class="col-md-3">
                <div class="card product-card">
                    <img src="assets/images/product-placeholder.jpg" class="card-img-top" alt="Pet Product">
                    <div class="card-body">
                        <h5 class="card-title">Pet Carrier</h5>
                        <div class="rating mb-2">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star-half-alt"></i>
                            <i class="far fa-star"></i>
                        </div>
                        <p class="price">$45.99</p>
                        <a href="#" class="btn btn-sm btn-primary">View Details</a>
                        <a href="#" class="btn btn-sm btn-success">Add to Cart</a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="text-center mt-4">
            <a href="shop.php" class="btn btn-outline-success">Browse All Products</a>
        </div>
    </div>
</section>

<!-- Testimonials -->
<section class="testimonials-section py-5">
    <div class="container">
        <div class="section-title">
            <h2>What Our Customers Say</h2>
            <p>Read testimonials from satisfied pet owners</p>
        </div>
        
        <div class="row">
            <!-- Testimonial 1 -->
            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="rating mb-3">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                        </div>
                        <p class="card-text">"I was so worried about leaving my cat alone while on vacation, but Sarah took amazing care of him. She sent daily updates with photos, and I came home to a happy kitty!"</p>
                        <div class="d-flex align-items-center mt-3">
                            <img src="assets/images/user-placeholder.jpg" alt="Customer" class="rounded-circle me-3" width="50">
                            <div>
                                <h6 class="mb-0">Amanda Thompson</h6>
                                <small class="text-muted">Cat Owner</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Testimonial 2 -->
            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="rating mb-3">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star-half-alt"></i>
                        </div>
                        <p class="card-text">"Michael is an exceptional dog walker! My energetic Lab needs lots of exercise, and Michael always makes sure he gets a good workout. My dog loves him!"</p>
                        <div class="d-flex align-items-center mt-3">
                            <img src="assets/images/user-placeholder.jpg" alt="Customer" class="rounded-circle me-3" width="50">
                            <div>
                                <h6 class="mb-0">Robert Johnson</h6>
                                <small class="text-muted">Dog Owner</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Testimonial 3 -->
            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="rating mb-3">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                        </div>
                        <p class="card-text">"The premium dog food I ordered arrived quickly and my picky eater actually loves it! Will definitely be ordering again. Great service and products!"</p>
                        <div class="d-flex align-items-center mt-3">
                            <img src="assets/images/user-placeholder.jpg" alt="Customer" class="rounded-circle me-3" width="50">
                            <div>
                                <h6 class="mb-0">Jennifer Lee</h6>
                                <small class="text-muted">Dog & Cat Owner</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Newsletter -->
<section class="newsletter-section py-5 bg-primary text-white">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 text-center">
                <h3>Subscribe to Our Newsletter</h3>
                <p class="mb-4">Stay updated with our latest services, products, and pet care tips.</p>
                <form class="row g-3 justify-content-center">
                    <div class="col-md-8">
                        <input type="email" class="form-control form-control-lg" placeholder="Enter your email address">
                    </div>
                    <div class="col-md-auto">
                        <button type="submit" class="btn btn-light btn-lg">Subscribe</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>

<?php
// Include footer
include_once 'includes/footer.php';
?>