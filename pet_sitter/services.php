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

// Process service deletion
if (isset($_POST['delete_service']) && isset($_POST['service_id'])) {
    $service_id = $_POST['service_id'];
    
    // Check if service belongs to this sitter
    $check_sql = "SELECT * FROM pet_service WHERE serviceID = ? AND userID = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ii", $service_id, $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        // Delete service
        $delete_sql = "DELETE FROM pet_service WHERE serviceID = ?";
        $delete_stmt = $conn->prepare($delete_sql);
        $delete_stmt->bind_param("i", $service_id);
        
        if ($delete_stmt->execute()) {
            $_SESSION['success_message'] = "Service deleted successfully.";
        } else {
            $_SESSION['error_message'] = "Failed to delete service: " . $conn->error;
        }
        
        $delete_stmt->close();
    } else {
        $_SESSION['error_message'] = "Service not found or does not belong to you.";
    }
    
    $check_stmt->close();
    
    // Redirect to refresh page
    header("Location: services.php");
    exit();
}

// Get all services for this sitter
$services_sql = "SELECT * FROM pet_service WHERE userID = ? ORDER BY name ASC";
$services_stmt = $conn->prepare($services_sql);
$services_stmt->bind_param("i", $user_id);
$services_stmt->execute();
$services_result = $services_stmt->get_result();
$services = [];
while ($row = $services_result->fetch_assoc()) {
    $services[] = $row;
}
$services_stmt->close();

// Include header
include_once '../includes/header.php';
?>

<!-- Modern Services Header -->
<section class="services-header-section">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <div class="services-header-content">
                    <span class="section-badge">🛍️ Service Management</span>
                    <h1 class="services-title">My <span class="text-gradient">Services</span></h1>
                    <p class="services-subtitle">Create and manage your pet sitting services to attract more clients.</p>
                </div>
            </div>
            <div class="col-lg-4 text-lg-end">
                <div class="services-actions">
                    <a href="add_service.php" class="btn btn-primary-gradient btn-lg">
                        <i class="fas fa-plus me-2"></i> Add New Service
                    </a>
                    <a href="dashboard.php" class="btn btn-outline-light ms-2">
                        <i class="fas fa-arrow-left me-1"></i> Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Services Content -->
<section class="services-content-section">
    <div class="container">
        <?php if (empty($services)): ?>
            <div class="empty-services-state">
                <div class="empty-icon">
                    <i class="fas fa-clipboard-list"></i>
                </div>
                <h3>No Services Yet</h3>
                <p>Start creating your services to attract pet owners and grow your business.</p>
                <a href="add_service.php" class="btn btn-primary-gradient btn-lg">
                    <i class="fas fa-plus me-2"></i> Create Your First Service
                </a>
            </div>
        <?php else: ?>
            <div class="services-grid">
                <?php foreach ($services as $service): ?>
                    <div class="service-card-modern">
                        <div class="service-image">
                            <?php if (!empty($service['image'])): ?>
                                <img src="../assets/images/services/<?php echo htmlspecialchars($service['image']); ?>" alt="<?php echo htmlspecialchars($service['name']); ?>">
                            <?php else: ?>
                                <div class="service-placeholder">
                                    <i class="fas fa-paw"></i>
                                </div>
                            <?php endif; ?>
                            <div class="service-type-badge">
                                <?php echo htmlspecialchars($service['type']); ?>
                            </div>
                        </div>
                        
                        <div class="service-content">
                            <h5 class="service-title"><?php echo htmlspecialchars($service['name']); ?></h5>
                            <p class="service-description"><?php echo nl2br(htmlspecialchars(substr($service['description'], 0, 120) . (strlen($service['description']) > 120 ? '...' : ''))); ?></p>
                            
                            <div class="service-price">
                                <span class="price-label">Starting from</span>
                                <span class="price">$<?php echo number_format($service['price'], 2); ?></span>
                                <span class="price-unit">/hour</span>
                            </div>
                            
                            <div class="service-actions">
                                <a href="edit_service.php?id=<?php echo $service['serviceID']; ?>" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-edit me-1"></i> Edit
                                </a>
                                <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $service['serviceID']; ?>">
                                    <i class="fas fa-trash-alt me-1"></i> Delete
                                </button>
                            </div>
                        </div>
                        
                        <!-- Delete Confirmation Modal -->
                        <div class="modal fade" id="deleteModal<?php echo $service['serviceID']; ?>" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content modern-modal">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Confirm Deletion</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="text-center mb-3">
                                            <i class="fas fa-exclamation-triangle text-warning" style="font-size: 3rem;"></i>
                                        </div>
                                        <p class="text-center">Are you sure you want to delete the service "<strong><?php echo htmlspecialchars($service['name']); ?></strong>"?</p>
                                        <p class="text-center text-muted small">This action cannot be undone.</p>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="d-inline">
                                            <input type="hidden" name="service_id" value="<?php echo $service['serviceID']; ?>">
                                            <button type="submit" name="delete_service" class="btn btn-danger">Delete Service</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Service Types Guide -->
<section class="service-guide-section">
    <div class="container">
        <div class="guide-card">
            <div class="guide-header">
                <h3><i class="fas fa-lightbulb me-2"></i> Service Types Guide</h3>
                <p>Choose the right service types to attract your ideal clients</p>
            </div>
            
            <div class="row g-4">
                <div class="col-lg-4 col-md-6">
                    <div class="guide-item">
                        <div class="guide-icon pet-sitting">
                            <i class="fas fa-home"></i>
                        </div>
                        <h5>Pet Sitting</h5>
                        <p>Caring for pets in the owner's home. Perfect for busy pet owners who want their pets to stay comfortable at home.</p>
                        <div class="guide-tips">
                            <strong>Tips:</strong> Highlight your reliability and experience with different pet types.
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6">
                    <div class="guide-item">
                        <div class="guide-icon dog-walking">
                            <i class="fas fa-walking"></i>
                        </div>
                        <h5>Dog Walking</h5>
                        <p>Regular exercise and outdoor activities for dogs. Great for pet owners with busy schedules.</p>
                        <div class="guide-tips">
                            <strong>Tips:</strong> Mention your fitness level and experience with different dog sizes.
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6">
                    <div class="guide-item">
                        <div class="guide-icon pet-boarding">
                            <i class="fas fa-house-user"></i>
                        </div>
                        <h5>Pet Boarding</h5>
                        <p>Caring for pets in your own home. Ideal for social pets who enjoy being around other animals.</p>
                        <div class="guide-tips">
                            <strong>Tips:</strong> Show photos of your pet-friendly space and mention any other pets you have.
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6">
                    <div class="guide-item">
                        <div class="guide-icon pet-grooming">
                            <i class="fas fa-cut"></i>
                        </div>
                        <h5>Pet Grooming</h5>
                        <p>Professional grooming services including bathing, brushing, and nail trimming.</p>
                        <div class="guide-tips">
                            <strong>Tips:</strong> Include before/after photos and list your grooming certifications.
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6">
                    <div class="guide-item">
                        <div class="guide-icon pet-training">
                            <i class="fas fa-graduation-cap"></i>
                        </div>
                        <h5>Pet Training</h5>
                        <p>Basic obedience training and behavioral correction for pets of all ages.</p>
                        <div class="guide-tips">
                            <strong>Tips:</strong> Highlight your training methodology and success stories.
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6">
                    <div class="guide-item">
                        <div class="guide-icon pet-healthcare">
                            <i class="fas fa-pills"></i>
                        </div>
                        <h5>Pet Healthcare</h5>
                        <p>Medication administration and basic health monitoring for pets with special needs.</p>
                        <div class="guide-tips">
                            <strong>Tips:</strong> Mention any medical training or experience with senior pets.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Pricing Strategy Section -->
<section class="pricing-strategy-section">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-6">
                <div class="pricing-card">
                    <h4><i class="fas fa-chart-line me-2"></i> Pricing Strategy</h4>
                    <div class="pricing-factors">
                        <h6>Consider These Factors:</h6>
                        <ul>
                            <li><i class="fas fa-check me-2"></i> Your experience and qualifications</li>
                            <li><i class="fas fa-check me-2"></i> Local market rates</li>
                            <li><i class="fas fa-check me-2"></i> Service duration and complexity</li>
                            <li><i class="fas fa-check me-2"></i> Number of pets</li>
                            <li><i class="fas fa-check me-2"></i> Special requirements</li>
                            <li><i class="fas fa-check me-2"></i> Your availability and demand</li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6">
                <div class="pricing-card">
                    <h4><i class="fas fa-dollar-sign me-2"></i> Market Rates in Colombo</h4>
                    <div class="pricing-table">
                        <div class="pricing-row">
                            <span class="service-name">Pet Sitting</span>
                            <span class="price-range">$15 - $25/hour</span>
                        </div>
                        <div class="pricing-row">
                            <span class="service-name">Dog Walking</span>
                            <span class="price-range">$10 - $20/hour</span>
                        </div>
                        <div class="pricing-row">
                            <span class="service-name">Pet Boarding</span>
                            <span class="price-range">$25 - $40/day</span>
                        </div>
                        <div class="pricing-row">
                            <span class="service-name">Pet Grooming</span>
                            <span class="price-range">$30 - $50/session</span>
                        </div>
                        <div class="pricing-row">
                            <span class="service-name">Pet Training</span>
                            <span class="price-range">$35 - $60/hour</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
.services-header-section {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 80px 0;
    position: relative;
    overflow: hidden;
}

.services-header-section::before {
    content: '';
    position: absolute;
    width: 100%;
    height: 100%;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="20" cy="20" r="2" fill="rgba(255,255,255,0.1)"/><circle cx="80" cy="40" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="40" cy="80" r="1.5" fill="rgba(255,255,255,0.1)"/></svg>');
    animation: float 20s infinite linear;
}

.services-title {
    font-size: 3.5rem;
    font-weight: 800;
    line-height: 1.2;
    margin-bottom: 24px;
}

.services-subtitle {
    font-size: 1.2rem;
    opacity: 0.9;
    margin-bottom: 0;
}

.services-actions {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}

.services-content-section {
    padding: 80px 0;
}

.empty-services-state {
    text-align: center;
    padding: 100px 40px;
    background: linear-gradient(135deg, #f8f9ff, #f1f5f9);
    border-radius: 24px;
    border: 2px dashed #d1d5db;
}

.empty-icon {
    font-size: 5rem;
    color: #cbd5e1;
    margin-bottom: 32px;
}

.empty-services-state h3 {
    font-size: 2rem;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 16px;
}

.empty-services-state p {
    font-size: 1.1rem;
    color: #64748b;
    margin-bottom: 32px;
    max-width: 500px;
    margin-left: auto;
    margin-right: auto;
}

.services-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 32px;
}

.service-card-modern {
    background: white;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(0, 0, 0, 0.05);
    transition: all 0.3s ease;
    height: 100%;
    display: flex;
    flex-direction: column;
}

.service-card-modern:hover {
    transform: translateY(-8px);
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
}

.service-image {
    position: relative;
    height: 200px;
    overflow: hidden;
}

.service-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.service-card-modern:hover .service-image img {
    transform: scale(1.05);
}

.service-placeholder {
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, #f1f5f9, #e2e8f0);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 3rem;
    color: #cbd5e1;
}

.service-type-badge {
    position: absolute;
    top: 16px;
    right: 16px;
    background: rgba(102, 126, 234, 0.9);
    color: white;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    backdrop-filter: blur(10px);
}

.service-content {
    padding: 24px;
    flex: 1;
    display: flex;
    flex-direction: column;
}

.service-title {
    font-size: 1.3rem;
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 12px;
}

.service-description {
    color: #64748b;
    line-height: 1.6;
    margin-bottom: 20px;
    flex: 1;
}

.service-price {
    background: linear-gradient(135deg, #f8f9ff, #f1f5f9);
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
    text-transform: uppercase;
    letter-spacing: 0.5px;
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

.service-actions {
    display: flex;
    gap: 12px;
}

.service-actions .btn {
    flex: 1;
    font-size: 0.9rem;
}

.modern-modal .modal-content {
    border-radius: 20px;
    border: none;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
}

.modern-modal .modal-header {
    border-bottom: 1px solid #f1f5f9;
    padding: 24px 32px 20px;
}

.modern-modal .modal-body {
    padding: 20px 32px;
}

.modern-modal .modal-footer {
    border-top: 1px solid #f1f5f9;
    padding: 20px 32px 24px;
}

.service-guide-section {
    padding: 80px 0;
    background: #f8f9ff;
}

.guide-card {
    background: white;
    border-radius: 24px;
    padding: 48px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(0, 0, 0, 0.05);
}

.guide-header {
    text-align: center;
    margin-bottom: 48px;
}

.guide-header h3 {
    font-size: 2.2rem;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 16px;
}

.guide-header p {
    font-size: 1.1rem;
    color: #64748b;
    margin-bottom: 0;
}

.guide-item {
    text-align: center;
    padding: 32px 24px;
    background: #f8f9ff;
    border-radius: 16px;
    height: 100%;
    border: 2px solid transparent;
    transition: all 0.3s ease;
}

.guide-item:hover {
    border-color: #667eea;
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(102, 126, 234, 0.15);
}

.guide-icon {
    width: 64px;
    height: 64px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
    color: white;
    margin: 0 auto 20px;
}

.guide-icon.pet-sitting { background: linear-gradient(135deg, #667eea, #764ba2); }
.guide-icon.dog-walking { background: linear-gradient(135deg, #10b981, #059669); }
.guide-icon.pet-boarding { background: linear-gradient(135deg, #f59e0b, #d97706); }
.guide-icon.pet-grooming { background: linear-gradient(135deg, #ef4444, #dc2626); }
.guide-icon.pet-training { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }
.guide-icon.pet-healthcare { background: linear-gradient(135deg, #06b6d4, #0891b2); }

.guide-item h5 {
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 12px;
}

.guide-item p {
    color: #64748b;
    line-height: 1.6;
    margin-bottom: 16px;
}

.guide-tips {
    background: rgba(102, 126, 234, 0.1);
    padding: 12px;
    border-radius: 8px;
    font-size: 0.85rem;
    color: #667eea;
    text-align: left;
}

.pricing-strategy-section {
    padding: 80px 0;
}

.pricing-card {
    background: white;
    border-radius: 20px;
    padding: 32px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(0, 0, 0, 0.05);
    height: 100%;
}

.pricing-card h4 {
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 24px;
}

.pricing-factors h6 {
    color: #667eea;
    font-weight: 600;
    margin-bottom: 16px;
}

.pricing-factors ul {
    list-style: none;
    padding: 0;
}

.pricing-factors li {
    padding: 8px 0;
    color: #64748b;
    display: flex;
    align-items: center;
}

.pricing-factors li i {
    color: #10b981;
    margin-right: 8px;
}

.pricing-table {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.pricing-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 16px;
    background: #f8f9ff;
    border-radius: 10px;
    border-left: 4px solid #667eea;
}

.service-name {
    font-weight: 500;
    color: #1e293b;
}

.price-range {
    font-weight: 600;
    color: #667eea;
}

@media (max-width: 991px) {
    .services-title {
        font-size: 2.5rem;
    }
    
    .services-actions {
        justify-content: center;
        margin-top: 24px;
    }
    
    .services-grid {
        grid-template-columns: 1fr;
        gap: 24px;
    }
    
    .guide-card {
        padding: 32px 24px;
    }
}

@media (max-width: 768px) {
    .services-header-section {
        text-align: center;
    }
    
    .services-actions {
        flex-direction: column;
        align-items: center;
    }
    
    .service-actions {
        flex-direction: column;
    }
    
    .empty-services-state {
        padding: 60px 24px;
    }
}
</style>

<?php
// Include footer
include_once '../includes/footer.php';

// Close database connection
$conn->close();
?>