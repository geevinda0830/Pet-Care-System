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

<!-- Modern Pet Sitters Header -->
<section class="sitters-header-section">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <div class="sitters-header-content">
                    <span class="section-badge">💖 Trusted Care</span>
                    <h1 class="sitters-title">Find Perfect <span class="text-gradient">Pet Sitters</span></h1>
                    <p class="sitters-subtitle">Connect with verified, loving pet care professionals in your area. Your furry friends deserve the best care while you're away.</p>
                    
                    <div class="trust-indicators">
                        <div class="trust-item">
                            <i class="fas fa-shield-check"></i>
                            <span>100% Verified</span>
                        </div>
                        <div class="trust-item">
                            <i class="fas fa-heart"></i>
                            <span>Pet Lovers</span>
                        </div>
                        <div class="trust-item">
                            <i class="fas fa-star"></i>
                            <span>Top Rated</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="sitters-stats">
                    <div class="stat-card">
                        <div class="stat-icon">👥</div>
                        <div class="stat-number">200+</div>
                        <div class="stat-label">Pet Sitters</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">⭐</div>
                        <div class="stat-number">4.9</div>
                        <div class="stat-label">Avg Rating</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">🎯</div>
                        <div class="stat-number">24/7</div>
                        <div class="stat-label">Available</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Modern Search and Filter Section -->
<section class="sitters-filter-section">
    <div class="container">
        <div class="filter-card">
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="get" class="filter-form">
                <div class="row g-3 align-items-end">
                    <div class="col-lg-3">
                        <label class="filter-label">Search Pet Sitters</label>
                        <div class="search-group">
                            <i class="fas fa-search search-icon"></i>
                            <input type="text" class="form-control search-input" name="search" placeholder="Name, service, specialization..." value="<?php echo htmlspecialchars($search_query); ?>">
                        </div>
                    </div>
                    
                    <div class="col-lg-2">
                        <label class="filter-label">Service Type</label>
                        <select class="form-select modern-select" name="service_type">
                            <option value="">All Services</option>
                            <option value="Pet Sitting" <?php if($service_type === 'Pet Sitting') echo 'selected'; ?>>Pet Sitting</option>
                            <option value="Dog Walking" <?php if($service_type === 'Dog Walking') echo 'selected'; ?>>Dog Walking</option>
                            <option value="Pet Boarding" <?php if($service_type === 'Pet Boarding') echo 'selected'; ?>>Pet Boarding</option>
                            <option value="Pet Grooming" <?php if($service_type === 'Pet Grooming') echo 'selected'; ?>>Pet Grooming</option>
                            <option value="Pet Training" <?php if($service_type === 'Pet Training') echo 'selected'; ?>>Pet Training</option>
                        </select>
                    </div>
                    
                    <div class="col-lg-2">
                        <label class="filter-label">Min Rating</label>
                        <select class="form-select modern-select" name="rating">
                            <option value="0">Any Rating</option>
                            <option value="4" <?php if($rating === 4) echo 'selected'; ?>>4+ Stars</option>
                            <option value="3" <?php if($rating === 3) echo 'selected'; ?>>3+ Stars</option>
                            <option value="2" <?php if($rating === 2) echo 'selected'; ?>>2+ Stars</option>
                        </select>
                    </div>
                    
                    <div class="col-lg-3">
                        <label class="filter-label">Price Range (per hour)</label>
                        <div class="price-range-group">
                            <input type="number" class="form-control price-input" name="min_price" placeholder="Min" value="<?php echo $min_price; ?>">
                            <span class="price-separator">-</span>
                            <input type="number" class="form-control price-input" name="max_price" placeholder="Max" value="<?php echo $max_price; ?>">
                        </div>
                    </div>
                    
                    <div class="col-lg-2">
                        <button type="submit" class="btn btn-primary-gradient filter-btn w-100">
                            <i class="fas fa-search me-2"></i> Search
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</section>

<!-- Modern Pet Sitters Section -->
<section class="sitters-listing-section">
    <div class="container">
        <?php if (empty($pet_sitters)): ?>
            <div class="no-sitters-card">
                <div class="no-sitters-icon">
                    <i class="fas fa-user-friends"></i>
                </div>
                <h4>No Pet Sitters Found</h4>
                <p>Try adjusting your search criteria or check back later for new pet sitters joining our community.</p>
                <a href="pet_sitters.php" class="btn btn-outline-primary">Reset Filters</a>
            </div>
        <?php else: ?>
            <div class="sitters-header">
                <h3>Available Pet Sitters <span class="sitters-count">(<?php echo count($pet_sitters); ?> found)</span></h3>
                <a href="pet_sitters.php" class="reset-link">Reset All Filters</a>
            </div>
            
            <div class="row g-4">
                <?php foreach ($pet_sitters as $sitter): ?>
                    <div class="col-xl-4 col-lg-6">
                        <div class="sitter-card-modern">
                            <div class="sitter-image">
                                <?php if (!empty($sitter['image'])): ?>
                                    <img src="assets/images/pet_sitters/<?php echo htmlspecialchars($sitter['image']); ?>" alt="<?php echo htmlspecialchars($sitter['fullName']); ?>">
                                <?php else: ?>
                                    <img src="https://images.unsplash.com/photo-1494790108755-2616c96e5e21?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80" alt="Pet Sitter">
                                <?php endif; ?>
                                
                                <div class="sitter-badge verified">
                                    <i class="fas fa-check-circle"></i>
                                    Verified
                                </div>
                                
                                <div class="sitter-availability">
                                    <div class="availability-dot"></div>
                                    Available
                                </div>
                            </div>
                            
                            <div class="sitter-info">
                                <div class="sitter-header">
                                    <h5 class="sitter-name"><?php echo htmlspecialchars($sitter['fullName']); ?></h5>
                                    <div class="sitter-rating">
                                        <div class="stars">
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
                                        </div>
                                        <span class="rating-text"><?php echo $avg_rating; ?> (<?php echo $sitter['review_count']; ?>)</span>
                                    </div>
                                </div>
                                
                                <div class="sitter-service">
                                    <i class="fas fa-paw service-icon"></i>
                                    <span><?php echo htmlspecialchars($sitter['service']); ?></span>
                                </div>
                                
                                <?php if (!empty($sitter['specialization'])): ?>
                                    <div class="sitter-specialization">
                                        <strong>Specializes in:</strong> <?php echo htmlspecialchars($sitter['specialization']); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($sitter['experience'])): ?>
                                    <div class="sitter-experience">
                                        <i class="fas fa-award me-1"></i>
                                        <span><?php echo htmlspecialchars($sitter['experience']); ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="sitter-location">
                                    <i class="fas fa-map-marker-alt me-1"></i>
                                    <span><?php echo htmlspecialchars($sitter['address']); ?></span>
                                </div>
                                
                                <div class="sitter-price">
                                    <span class="price-label">Starting from</span>
                                    <span class="price">Rs. <?php echo number_format($sitter['price'], 0); ?></span>
                                    <span class="price-unit">/hour</span>
                                </div>
                                
                                <div class="sitter-actions">
                                    <a href="sitter_profile.php?id=<?php echo $sitter['userID']; ?>" class="btn btn-outline-primary">
                                        <i class="fas fa-user me-1"></i> View Profile
                                    </a>
                                    <a href="booking.php?sitter_id=<?php echo $sitter['userID']; ?>" class="btn btn-primary-gradient">
                                        <i class="fas fa-calendar-check me-1"></i> Book Now
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Become a Pet Sitter Section -->
<section class="become-sitter-section">
    <div class="container">
        <div class="become-sitter-card">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <div class="become-sitter-content">
                        <div class="become-sitter-icon">
                            <i class="fas fa-hands-helping"></i>
                        </div>
                        <h3>Love Pets? Join Our Community!</h3>
                        <p>Turn your passion for animals into income. Become a trusted pet sitter and help pet parents in your community.</p>
                        
                        <div class="sitter-benefits">
                            <div class="benefit">
                                <i class="fas fa-money-bill-wave"></i>
                                <span>Earn Rs. 2,000-5,000/month</span>
                            </div>
                            <div class="benefit">
                                <i class="fas fa-clock"></i>
                                <span>Flexible schedule</span>
                            </div>
                            <div class="benefit">
                                <i class="fas fa-heart"></i>
                                <span>Work with adorable pets</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="become-sitter-image">
                        <img src="https://images.unsplash.com/photo-1601758228041-f3b2795255f1?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80" alt="Pet Sitter" class="img-fluid rounded-3">
                    </div>
                </div>
            </div>
            <div class="text-center mt-4">
                <a href="register.php?type=pet_sitter" class="btn btn-primary-gradient btn-lg">
                    <i class="fas fa-user-plus me-2"></i> Become a Pet Sitter
                </a>
                <p class="mt-3 mb-0"><small>All applications are reviewed by our admin team for quality assurance</small></p>
            </div>
        </div>
    </div>
</section>

<style>
.sitters-header-section {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 80px 0;
}

.sitters-header-content {
    padding-right: 40px;
}

.sitters-title {
    font-size: 3.5rem;
    font-weight: 800;
    line-height: 1.2;
    margin-bottom: 24px;
}

.sitters-subtitle {
    font-size: 1.2rem;
    opacity: 0.9;
    margin-bottom: 32px;
}

.trust-indicators {
    display: flex;
    gap: 24px;
}

.trust-item {
    display: flex;
    align-items: center;
    gap: 8px;
    background: rgba(255, 255, 255, 0.1);
    padding: 8px 16px;
    border-radius: 50px;
    backdrop-filter: blur(10px);
}

.trust-item i {
    color: #4ade80;
}

.sitters-stats {
    display: flex;
    gap: 24px;
    justify-content: center;
}

.sitters-filter-section {
    padding: 40px 0;
    background: #f8f9ff;
}

.sitters-listing-section {
    padding: 80px 0;
    background: white;
}

.no-sitters-card {
    text-align: center;
    padding: 80px 40px;
    background: #f8f9ff;
    border-radius: 20px;
    border: 2px dashed #d1d5db;
}

.no-sitters-icon {
    font-size: 4rem;
    color: #9ca3af;
    margin-bottom: 24px;
}

.sitters-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 40px;
}

.sitters-header h3 {
    font-size: 2rem;
    font-weight: 700;
    color: #1e293b;
    margin: 0;
}

.sitters-count {
    color: #64748b;
    font-weight: 500;
    font-size: 1rem;
}

.sitter-card-modern {
    background: white;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    transition: all 0.3s ease;
    height: 100%;
    border: 1px solid rgba(0, 0, 0, 0.05);
}

.sitter-card-modern:hover {
    transform: translateY(-8px);
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
}

.sitter-image {
    position: relative;
    height: 280px;
    overflow: hidden;
}

.sitter-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.sitter-card-modern:hover .sitter-image img {
    transform: scale(1.05);
}

.sitter-badge {
    position: absolute;
    top: 16px;
    right: 16px;
    background: #10b981;
    color: white;
    padding: 6px 12px;
    border-radius: 50px;
    font-size: 0.8rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 4px;
}

.sitter-availability {
    position: absolute;
    bottom: 16px;
    left: 16px;
    background: rgba(16, 185, 129, 0.9);
    color: white;
    padding: 6px 12px;
    border-radius: 50px;
    font-size: 0.8rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 6px;
    backdrop-filter: blur(10px);
}

.availability-dot {
    width: 8px;
    height: 8px;
    background: #4ade80;
    border-radius: 50%;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.2); opacity: 0.7; }
    100% { transform: scale(1); opacity: 1; }
}

.sitter-info {
    padding: 24px;
}

.sitter-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 16px;
}

.sitter-name {
    font-size: 1.3rem;
    font-weight: 600;
    color: #1e293b;
    margin: 0;
}

.sitter-rating {
    text-align: right;
}

.sitter-rating .stars {
    color: #fbbf24;
    margin-bottom: 4px;
}

.rating-text {
    font-size: 0.8rem;
    color: #64748b;
}

.sitter-service {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 12px;
    color: #667eea;
    font-weight: 500;
}

.service-icon {
    width: 20px;
    text-align: center;
}

.sitter-specialization {
    font-size: 0.9rem;
    color: #64748b;
    margin-bottom: 12px;
    line-height: 1.4;
}

.sitter-experience {
    font-size: 0.9rem;
    color: #6b7280;
    margin-bottom: 12px;
}

.sitter-location {
    font-size: 0.9rem;
    color: #9ca3af;
    margin-bottom: 16px;
}

.sitter-price {
    background: linear-gradient(135deg, #f1f5f9, #e2e8f0);
    padding: 16px;
    border-radius: 12px;
    text-align: center;
    margin-bottom: 20px;
}

.price-label {
    display: block;
    font-size: 0.8rem;
    color: #64748b;
    margin-bottom: 4px;
}

.price {
    font-size: 1.8rem;
    font-weight: 700;
    color: #667eea;
}

.price-unit {
    font-size: 0.9rem;
    color: #64748b;
}

.sitter-actions {
    display: flex;
    gap: 12px;
}

.sitter-actions .btn {
    flex: 1;
    font-size: 0.9rem;
    padding: 10px 16px;
}

.become-sitter-section {
    padding: 80px 0;
    background: linear-gradient(135deg, #f1f5f9, #e2e8f0);
}

.become-sitter-card {
    background: white;
    border-radius: 24px;
    padding: 60px 40px;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
    border: 1px solid rgba(0, 0, 0, 0.05);
}

.become-sitter-content {
    padding-right: 40px;
}

.become-sitter-icon {
    width: 80px;
    height: 80px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    color: white;
    margin-bottom: 24px;
}

.become-sitter-content h3 {
    font-size: 2.2rem;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 16px;
}

.become-sitter-content p {
    font-size: 1.1rem;
    color: #64748b;
    margin-bottom: 32px;
    line-height: 1.6;
}

.sitter-benefits {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.benefit {
    display: flex;
    align-items: center;
    gap: 12px;
    font-weight: 500;
    color: #374151;
}

.benefit i {
    width: 20px;
    color: #667eea;
}

.become-sitter-image img {
    width: 100%;
    height: 400px;
    object-fit: cover;
}

@media (max-width: 991px) {
    .sitters-title {
        font-size: 2.5rem;
    }
    
    .sitters-header-content {
        padding-right: 0;
        margin-bottom: 40px;
    }
    
    .trust-indicators {
        justify-content: center;
        flex-wrap: wrap;
    }
    
    .sitters-stats {
        justify-content: space-around;
    }
    
    .sitter-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 16px;
    }
    
    .become-sitter-content {
        padding-right: 0;
        margin-bottom: 40px;
    }
    
    .sitter-benefits {
        flex-direction: row;
        flex-wrap: wrap;
        justify-content: center;
    }
}

@media (max-width: 768px) {
    .sitter-actions {
        flex-direction: column;
    }
    
    .become-sitter-card {
        padding: 40px 24px;
    }
}
</style>

<?php
// Include footer
include_once 'includes/footer.php';

// Close database connection
$conn->close();
?>