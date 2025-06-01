<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in as pet owner
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'pet_owner') {
    $_SESSION['error_message'] = "You must be logged in as a pet owner to access this page.";
    header("Location: ../login.php");
    exit();
}

// Include database connection
require_once '../config/db_connect.php';

$user_id = $_SESSION['user_id'];

// Process delete pet request
if (isset($_POST['delete_pet']) && isset($_POST['pet_id'])) {
    $pet_id = $_POST['pet_id'];
    
    // Check if pet belongs to user
    $check_sql = "SELECT * FROM pet_profile WHERE petID = ? AND userID = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ii", $pet_id, $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        // Check if pet has active bookings
        $booking_check_sql = "SELECT * FROM booking WHERE petID = ? AND status IN ('Pending', 'Confirmed')";
        $booking_check_stmt = $conn->prepare($booking_check_sql);
        $booking_check_stmt->bind_param("i", $pet_id);
        $booking_check_stmt->execute();
        $booking_check_result = $booking_check_stmt->get_result();
        
        if ($booking_check_result->num_rows > 0) {
            $_SESSION['error_message'] = "Cannot delete pet with active bookings.";
        } else {
            // Delete pet
            $delete_sql = "DELETE FROM pet_profile WHERE petID = ?";
            $delete_stmt = $conn->prepare($delete_sql);
            $delete_stmt->bind_param("i", $pet_id);
            
            if ($delete_stmt->execute()) {
                $_SESSION['success_message'] = "Pet deleted successfully.";
            } else {
                $_SESSION['error_message'] = "Failed to delete pet: " . $conn->error;
            }
            
            $delete_stmt->close();
        }
        
        $booking_check_stmt->close();
    } else {
        $_SESSION['error_message'] = "Pet not found or does not belong to you.";
    }
    
    $check_stmt->close();
    
    // Redirect to refresh page
    header("Location: pets.php");
    exit();
}

// Get all pets for this owner
$pets_sql = "SELECT p.*, 
             (SELECT AVG(pr.rating) FROM pet_ranking pr WHERE pr.petID = p.petID) as avg_rating,
             (SELECT COUNT(pr.rankingID) FROM pet_ranking pr WHERE pr.petID = p.petID) as review_count
             FROM pet_profile p 
             WHERE p.userID = ? 
             ORDER BY p.petName ASC";
$pets_stmt = $conn->prepare($pets_sql);
$pets_stmt->bind_param("i", $user_id);
$pets_stmt->execute();
$pets_result = $pets_stmt->get_result();
$pets = [];
while ($row = $pets_result->fetch_assoc()) {
    $pets[] = $row;
}
$pets_stmt->close();

// Include header
include_once '../includes/header.php';
?>

<!-- Modern Page Header -->
<section class="page-header-modern">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <div class="page-header-content">
                    <span class="page-badge">🐾 My Pets</span>
                    <h1 class="page-title">Your Beloved <span class="text-gradient">Companions</span></h1>
                    <p class="page-subtitle">Manage your pet profiles and track their care history</p>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="page-actions">
                    <a href="add_pet.php" class="btn btn-primary-gradient">
                        <i class="fas fa-plus me-2"></i> Add New Pet
                    </a>
                    <a href="dashboard.php" class="btn btn-outline-light">
                        <i class="fas fa-arrow-left me-2"></i> Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Pets Content -->
<section class="content-section-modern">
    <div class="container">
        <?php if (empty($pets)): ?>
            <div class="empty-state-modern">
                <div class="empty-icon">
                    <i class="fas fa-paw"></i>
                </div>
                <h4>No Pets Added Yet</h4>
                <p>Start by adding your first pet to create their profile and track their care.</p>
                <a href="add_pet.php" class="btn btn-primary-gradient">
                    <i class="fas fa-plus me-2"></i> Add Your First Pet
                </a>
            </div>
        <?php else: ?>
            <div class="content-header">
                <h3>Your Pets <span class="count-badge"><?php echo count($pets); ?> registered</span></h3>
                <div class="header-actions">
                    <a href="add_pet.php" class="btn btn-primary-gradient">
                        <i class="fas fa-plus me-2"></i> Add Pet
                    </a>
                </div>
            </div>
            
            <div class="pets-grid">
                <?php foreach ($pets as $pet): ?>
                    <div class="pet-card-modern">
                        <div class="pet-image-container">
                            <?php if (!empty($pet['image'])): ?>
                                <img src="../assets/images/pets/<?php echo htmlspecialchars($pet['image']); ?>" class="pet-image" alt="<?php echo htmlspecialchars($pet['petName']); ?>">
                            <?php else: ?>
                                <div class="pet-placeholder">
                                    <i class="fas fa-paw"></i>
                                    <span>No Photo</span>
                                </div>
                            <?php endif; ?>
                            <div class="pet-type-badge">
                                <?php 
                                $type_icons = [
                                    'Dog' => '🐕',
                                    'Cat' => '🐱', 
                                    'Bird' => '🐦',
                                    'Fish' => '🐠',
                                    'Rabbit' => '🐰',
                                    'Hamster' => '🐹'
                                ];
                                echo isset($type_icons[$pet['type']]) ? $type_icons[$pet['type']] : '🐾';
                                ?>
                                <?php echo htmlspecialchars($pet['type']); ?>
                            </div>
                        </div>
                        
                        <div class="pet-content">
                            <div class="pet-header">
                                <h5 class="pet-name"><?php echo htmlspecialchars($pet['petName']); ?></h5>
                                <?php if ($pet['avg_rating']): ?>
                                    <div class="pet-rating">
                                        <div class="stars">
                                            <?php
                                            $avg_rating = round($pet['avg_rating'], 1);
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
                                        <span class="rating-text"><?php echo $avg_rating; ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="pet-details">
                                <div class="detail-row">
                                    <div class="detail-item">
                                        <span class="detail-label">Breed</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($pet['breed']); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Age</span>
                                        <span class="detail-value"><?php echo $pet['age']; ?> years</span>
                                    </div>
                                </div>
                                
                                <div class="detail-row">
                                    <div class="detail-item">
                                        <span class="detail-label">Gender</span>
                                        <span class="detail-value"><?php echo $pet['sex']; ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Color</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($pet['color']); ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="pet-stats">
                                <?php if ($pet['review_count'] > 0): ?>
                                    <div class="stat-item">
                                        <div class="stat-icon">
                                            <i class="fas fa-star"></i>
                                        </div>
                                        <div class="stat-content">
                                            <span class="stat-number"><?php echo $pet['review_count']; ?></span>
                                            <span class="stat-label">Reviews</span>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="stat-item">
                                    <div class="stat-icon">
                                        <i class="fas fa-heart"></i>
                                    </div>
                                    <div class="stat-content">
                                        <span class="stat-number">❤️</span>
                                        <span class="stat-label">Loved</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="pet-actions">
                                <a href="pet_details.php?id=<?php echo $pet['petID']; ?>" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-eye me-1"></i> View Details
                                </a>
                                <a href="edit_pet.php?id=<?php echo $pet['petID']; ?>" class="btn btn-primary-gradient btn-sm">
                                    <i class="fas fa-edit me-1"></i> Edit
                                </a>
                                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to delete <?php echo htmlspecialchars($pet['petName']); ?>? This action cannot be undone.');">
                                    <input type="hidden" name="pet_id" value="<?php echo $pet['petID']; ?>">
                                    <button type="submit" name="delete_pet" class="btn btn-outline-danger btn-sm">
                                        <i class="fas fa-trash-alt me-1"></i> Delete
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Pet Care Tips Section -->
<section class="tips-section-modern">
    <div class="container">
        <div class="section-header text-center">
            <h3>Pet Care Tips</h3>
            <p>Keep your pets healthy and happy with these expert tips</p>
        </div>
        
        <div class="tips-grid">
            <div class="tip-card">
                <div class="tip-icon">🥘</div>
                <h6>Proper Nutrition</h6>
                <p>Feed high-quality, age-appropriate food and maintain regular feeding schedules.</p>
            </div>
            <div class="tip-card">
                <div class="tip-icon">🏃</div>
                <h6>Regular Exercise</h6>
                <p>Ensure daily physical activity appropriate for your pet's breed and age.</p>
            </div>
            <div class="tip-card">
                <div class="tip-icon">🏥</div>
                <h6>Health Checkups</h6>
                <p>Schedule regular veterinary visits and keep vaccinations up to date.</p>
            </div>
            <div class="tip-card">
                <div class="tip-icon">❤️</div>
                <h6>Love & Attention</h6>
                <p>Spend quality time bonding and provide mental stimulation through play.</p>
            </div>
        </div>
    </div>
</section>

<style>
.page-header-modern {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 80px 0;
    position: relative;
    overflow: hidden;
}

.page-header-modern::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="20" cy="20" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="80" cy="40" r="0.5" fill="rgba(255,255,255,0.1)"/><circle cx="40" cy="80" r="1.5" fill="rgba(255,255,255,0.1)"/></svg>');
    animation: float 20s infinite linear;
}

.page-header-content {
    position: relative;
    z-index: 2;
}

.page-badge {
    background: rgba(255, 255, 255, 0.2);
    padding: 8px 16px;
    border-radius: 50px;
    font-size: 0.9rem;
    font-weight: 600;
    margin-bottom: 16px;
    display: inline-block;
    backdrop-filter: blur(10px);
}

.page-title {
    font-size: 3.5rem;
    font-weight: 800;
    line-height: 1.2;
    margin-bottom: 16px;
}

.page-subtitle {
    font-size: 1.2rem;
    opacity: 0.9;
    margin-bottom: 0;
}

.page-actions {
    text-align: right;
    position: relative;
    z-index: 2;
}

.page-actions .btn {
    margin-left: 12px;
}

.content-section-modern {
    padding: 80px 0;
    background: white;
}

.empty-state-modern {
    text-align: center;
    padding: 80px 40px;
    background: #f8f9ff;
    border-radius: 20px;
    border: 2px dashed #d1d5db;
    max-width: 500px;
    margin: 0 auto;
}

.empty-icon {
    font-size: 4rem;
    color: #9ca3af;
    margin-bottom: 24px;
}

.empty-state-modern h4 {
    color: #374151;
    margin-bottom: 16px;
    font-weight: 600;
}

.empty-state-modern p {
    color: #6b7280;
    margin-bottom: 32px;
}

.content-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 40px;
}

.content-header h3 {
    font-size: 2rem;
    font-weight: 700;
    color: #1e293b;
    margin: 0;
}

.count-badge {
    background: linear-gradient(135deg, #f1f5f9, #e2e8f0);
    color: #64748b;
    padding: 4px 12px;
    border-radius: 50px;
    font-size: 0.8rem;
    font-weight: 500;
    margin-left: 12px;
}

.pets-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 24px;
}

.pet-card-modern {
    background: white;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(0, 0, 0, 0.05);
    transition: all 0.3s ease;
    position: relative;
}

.pet-card-modern:hover {
    transform: translateY(-8px);
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
}

.pet-image-container {
    position: relative;
    height: 250px;
    overflow: hidden;
}

.pet-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.pet-card-modern:hover .pet-image {
    transform: scale(1.05);
}

.pet-placeholder {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    background: linear-gradient(135deg, #f1f5f9, #e2e8f0);
    color: #9ca3af;
}

.pet-placeholder i {
    font-size: 3rem;
    margin-bottom: 8px;
}

.pet-type-badge {
    position: absolute;
    top: 16px;
    right: 16px;
    background: rgba(255, 255, 255, 0.9);
    padding: 6px 12px;
    border-radius: 50px;
    font-size: 0.8rem;
    font-weight: 600;
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.pet-content {
    padding: 24px;
}

.pet-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.pet-name {
    font-size: 1.4rem;
    font-weight: 600;
    color: #1e293b;
    margin: 0;
}

.pet-rating {
    display: flex;
    align-items: center;
    gap: 8px;
}

.pet-rating .stars {
    color: #fbbf24;
    font-size: 0.9rem;
}

.rating-text {
    color: #64748b;
    font-weight: 500;
    font-size: 0.9rem;
}

.pet-details {
    margin-bottom: 20px;
}

.detail-row {
    display: flex;
    gap: 20px;
    margin-bottom: 12px;
}

.detail-item {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.detail-label {
    font-size: 0.8rem;
    color: #9ca3af;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.detail-value {
    color: #374151;
    font-weight: 500;
}

.pet-stats {
    display: flex;
    gap: 16px;
    margin-bottom: 20px;
    padding: 16px;
    background: #f8fafc;
    border-radius: 12px;
}

.stat-item {
    display: flex;
    align-items: center;
    gap: 8px;
}

.stat-icon {
    width: 32px;
    height: 32px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 0.8rem;
}

.stat-content {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.stat-number {
    font-weight: 600;
    color: #1e293b;
    font-size: 0.9rem;
}

.stat-label {
    font-size: 0.7rem;
    color: #9ca3af;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.pet-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.pet-actions .btn {
    flex: 1;
    min-width: 80px;
}

.tips-section-modern {
    padding: 80px 0;
    background: #f8f9ff;
}

.section-header {
    margin-bottom: 50px;
}

.section-header h3 {
    font-size: 2.2rem;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 12px;
}

.section-header p {
    color: #64748b;
    font-size: 1.1rem;
}

.tips-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 24px;
}

.tip-card {
    background: white;
    padding: 32px 24px;
    border-radius: 20px;
    text-align: center;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(0, 0, 0, 0.05);
    transition: all 0.3s ease;
}

.tip-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
}

.tip-icon {
    font-size: 2.5rem;
    margin-bottom: 16px;
}

.tip-card h6 {
    font-size: 1.1rem;
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 12px;
}

.tip-card p {
    color: #64748b;
    line-height: 1.6;
    margin: 0;
}

@media (max-width: 768px) {
    .page-title {
        font-size: 2.5rem;
    }
    
    .page-actions {
        text-align: left;
        margin-top: 24px;
    }
    
    .page-actions .btn {
        margin-left: 0;
        margin-right: 12px;
        margin-bottom: 8px;
    }
    
    .pets-grid {
        grid-template-columns: 1fr;
    }
    
    .content-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 16px;
    }
    
    .detail-row {
        flex-direction: column;
        gap: 12px;
    }
    
    .pet-actions {
        flex-direction: column;
    }
    
    .pet-actions .btn {
        min-width: auto;
    }
    
    .pet-stats {
        flex-direction: column;
        gap: 12px;
    }
}
</style>

<?php
// Include footer
include_once '../includes/footer.php';

// Close database connection
$conn->close();
?>