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

// Check if pet_id is provided
if (!isset($_GET['pet_id']) || empty($_GET['pet_id'])) {
    $_SESSION['error_message'] = "Invalid pet ID.";
    header("Location: pets.php");
    exit();
}

// Include database connection
require_once '../config/db_connect.php';

$user_id = $_SESSION['user_id'];
$pet_id = $_GET['pet_id'];

// Verify pet belongs to user
$pet_sql = "SELECT petName, image, type, breed FROM pet_profile WHERE petID = ? AND userID = ?";
$pet_stmt = $conn->prepare($pet_sql);
$pet_stmt->bind_param("ii", $pet_id, $user_id);
$pet_stmt->execute();
$pet_result = $pet_stmt->get_result();

if ($pet_result->num_rows === 0) {
    $_SESSION['error_message'] = "Pet not found or does not belong to you.";
    header("Location: pets.php");
    exit();
}

$pet = $pet_result->fetch_assoc();
$pet_stmt->close();

// Get behavioral rankings with detailed information
$rankings_sql = "SELECT pr.*, ps.fullName as sitterName, ps.image as sitterImage, 
                 b.checkInDate, b.checkOutDate 
                 FROM pet_ranking pr 
                 LEFT JOIN pet_sitter ps ON pr.sitterID = ps.userID 
                 LEFT JOIN booking b ON pr.bookingID = b.bookingID
                 WHERE pr.petID = ? 
                 ORDER BY pr.created_at DESC";
$rankings_stmt = $conn->prepare($rankings_sql);
$rankings_stmt->bind_param("i", $pet_id);
$rankings_stmt->execute();
$rankings_result = $rankings_stmt->get_result();
$behavioral_reviews = [];

while ($row = $rankings_result->fetch_assoc()) {
    // Parse specific behaviors JSON if exists
    if (!empty($row['specific_behaviors'])) {
        $row['specific_behaviors'] = json_decode($row['specific_behaviors'], true);
    }
    $behavioral_reviews[] = $row;
}

$rankings_stmt->close();

// Calculate behavioral statistics
$total_reviews = count($behavioral_reviews);
$average_rating = $total_reviews > 0 ? array_sum(array_column($behavioral_reviews, 'rating')) / $total_reviews : 0;

// Calculate behavior trend (last 3 vs previous reviews)
$recent_reviews = array_slice($behavioral_reviews, 0, 3);
$older_reviews = array_slice($behavioral_reviews, 3, 3);
$recent_avg = count($recent_reviews) > 0 ? array_sum(array_column($recent_reviews, 'rating')) / count($recent_reviews) : 0;
$older_avg = count($older_reviews) > 0 ? array_sum(array_column($older_reviews, 'rating')) / count($older_reviews) : 0;
$trend = $recent_avg - $older_avg;

// Calculate behavior category averages
$behavior_categories = [
    'obedience' => [],
    'friendliness' => [],
    'energy_level' => [],
    'potty_trained' => []
];

foreach ($behavioral_reviews as $review) {
    if (isset($review['specific_behaviors']) && is_array($review['specific_behaviors'])) {
        foreach ($behavior_categories as $category => $values) {
            if (isset($review['specific_behaviors'][$category])) {
                $behavior_categories[$category][] = $review['specific_behaviors'][$category];
            }
        }
    }
}

// Calculate averages for each category
$behavior_averages = [];
foreach ($behavior_categories as $category => $values) {
    $behavior_averages[$category] = count($values) > 0 ? array_sum($values) / count($values) : 0;
}

// Include header
include_once '../includes/header.php';
?>

<!-- Modern Page Header -->
<section class="page-header-modern">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <div class="page-header-content">
                    <span class="page-badge">⭐ Behavioral Reviews</span>
                    <h1 class="page-title"><?php echo htmlspecialchars($pet['petName']); ?>'s <span class="text-gradient">Behavior Profile</span></h1>
                    <p class="page-subtitle">Pet sitter feedback and behavioral tracking</p>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="page-actions">
                    <a href="pet_details.php?id=<?php echo $pet_id; ?>" class="btn btn-outline-light">
                        <i class="fas fa-arrow-left me-2"></i> Back to Profile
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Behavioral Statistics -->
<section class="stats-section-modern">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-3 col-md-6">
                <div class="stat-card-modern primary">
                    <div class="stat-icon">
                        <i class="fas fa-star"></i>
                    </div>
                    <div class="stat-content">
                        <h3 class="stat-number"><?php echo number_format($average_rating, 1); ?></h3>
                        <p class="stat-label">Average Rating</p>
                        <div class="rating-stars">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="<?php echo $i <= round($average_rating) ? 'fas' : 'far'; ?> fa-star"></i>
                            <?php endfor; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <div class="stat-card-modern success">
                    <div class="stat-icon">
                        <i class="fas fa-comments"></i>
                    </div>
                    <div class="stat-content">
                        <h3 class="stat-number"><?php echo $total_reviews; ?></h3>
                        <p class="stat-label">Total Reviews</p>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <div class="stat-card-modern <?php echo $trend >= 0 ? 'info' : 'warning'; ?>">
                    <div class="stat-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-content">
                        <h3 class="stat-number">
                            <?php echo $trend > 0 ? '+' : ''; ?><?php echo number_format($trend, 1); ?>
                        </h3>
                        <p class="stat-label">Recent Trend</p>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <div class="stat-card-modern warning">
                    <div class="stat-icon">
                        <i class="fas fa-calendar"></i>
                    </div>
                    <div class="stat-content">
                        <h3 class="stat-number">
                            <?php 
                            if ($total_reviews > 0) {
                                $last_review = $behavioral_reviews[0]['created_at'];
                                $days_ago = floor((time() - strtotime($last_review)) / (60 * 60 * 24));
                                echo $days_ago . 'd';
                            } else {
                                echo 'None';
                            }
                            ?>
                        </h3>
                        <p class="stat-label">Last Review</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Behavior Categories Overview -->
<?php if (!empty($behavior_averages) && max($behavior_averages) > 0): ?>
<section class="behavior-categories-section">
    <div class="container">
        <div class="behavior-overview-card">
            <div class="card-header">
                <h4><i class="fas fa-chart-bar me-2"></i>Behavior Categories</h4>
                <p>Average performance across different behavioral traits</p>
            </div>
            
            <div class="behavior-grid">
                <?php foreach ($behavior_averages as $category => $average): ?>
                    <?php if ($average > 0): ?>
                        <div class="behavior-category">
                            <div class="category-header">
                                <span class="category-name"><?php echo ucfirst(str_replace('_', ' ', $category)); ?></span>
                                <span class="category-score"><?php echo number_format($average, 1); ?>/5</span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo ($average / 5) * 100; ?>%"></div>
                            </div>
                            <div class="category-level">
                                <?php
                                $level = 'Poor';
                                $level_class = 'poor';
                                if ($average >= 4.5) { $level = 'Excellent'; $level_class = 'excellent'; }
                                elseif ($average >= 3.5) { $level = 'Good'; $level_class = 'good'; }
                                elseif ($average >= 2.5) { $level = 'Fair'; $level_class = 'fair'; }
                                ?>
                                <span class="level-badge <?php echo $level_class; ?>"><?php echo $level; ?></span>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Behavioral Reviews List -->
<section class="reviews-section-modern">
    <div class="container">
        <?php if (empty($behavioral_reviews)): ?>
            <div class="empty-state-modern">
                <div class="empty-icon">
                    <i class="fas fa-star"></i>
                </div>
                <h4>No Behavioral Reviews Yet</h4>
                <p>Pet sitters will provide behavioral feedback after completing care sessions with <?php echo htmlspecialchars($pet['petName']); ?>.</p>
                <a href="../pet_sitters.php" class="btn btn-primary-gradient">
                    <i class="fas fa-search me-2"></i> Find Pet Sitters
                </a>
            </div>
        <?php else: ?>
            <div class="content-header">
                <h3>Behavioral Reviews <span class="count-badge"><?php echo count($behavioral_reviews); ?> reviews</span></h3>
            </div>
            
            <div class="reviews-timeline">
                <?php foreach ($behavioral_reviews as $review): ?>
                    <div class="review-card-modern">
                        <div class="review-header">
                            <div class="sitter-info">
                                <div class="sitter-avatar">
                                    <?php if (!empty($review['sitterImage'])): ?>
                                        <img src="../assets/images/pet_sitters/<?php echo htmlspecialchars($review['sitterImage']); ?>" alt="<?php echo htmlspecialchars($review['sitterName']); ?>">
                                    <?php else: ?>
                                        <div class="avatar-placeholder">
                                            <i class="fas fa-user"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="sitter-details">
                                    <h6><?php echo htmlspecialchars($review['sitterName'] ?: 'Anonymous Sitter'); ?></h6>
                                    <div class="review-date">
                                        <?php echo date('M d, Y', strtotime($review['created_at'])); ?>
                                        <?php if (!empty($review['checkInDate'])): ?>
                                            <span class="separator">•</span>
                                            <span class="booking-period">
                                                <?php echo date('M d', strtotime($review['checkInDate'])); ?> - 
                                                <?php echo date('M d', strtotime($review['checkOutDate'])); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="overall-rating">
                                <div class="rating-stars">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="<?php echo $i <= $review['rating'] ? 'fas' : 'far'; ?> fa-star"></i>
                                    <?php endfor; ?>
                                </div>
                                <span class="rating-number"><?php echo $review['rating']; ?>/5</span>
                            </div>
                        </div>
                        
                        <?php if (!empty($review['behavior_category'])): ?>
                            <div class="behavior-category-badge">
                                <span><?php echo htmlspecialchars($review['behavior_category']); ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <div class="review-content">
                            <p><?php echo nl2br(htmlspecialchars($review['feedback'])); ?></p>
                        </div>
                        
                        <?php if (!empty($review['specific_behaviors']) && is_array($review['specific_behaviors'])): ?>
                            <div class="specific-behaviors">
                                <h6>Detailed Behavior Assessment:</h6>
                                <div class="behavior-ratings">
                                    <?php foreach ($review['specific_behaviors'] as $behavior => $rating): ?>
                                        <div class="behavior-item">
                                            <span class="behavior-name"><?php echo ucfirst(str_replace('_', ' ', $behavior)); ?></span>
                                            <div class="behavior-rating">
                                                <div class="mini-stars">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <i class="<?php echo $i <= $rating ? 'fas' : 'far'; ?> fa-star"></i>
                                                    <?php endfor; ?>
                                                </div>
                                                <span class="rating-value"><?php echo $rating; ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($review['recommendations'])): ?>
                            <div class="recommendations">
                                <h6>Sitter Recommendations:</h6>
                                <p><?php echo nl2br(htmlspecialchars($review['recommendations'])); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Behavior Improvement Tips -->
<section class="tips-section-modern">
    <div class="container">
        <div class="tips-card-large">
            <div class="tips-header">
                <h4><i class="fas fa-lightbulb me-2"></i>Behavior Improvement Tips</h4>
                <p>Help <?php echo htmlspecialchars($pet['petName']); ?> become an even better companion</p>
            </div>
            
            <div class="tips-grid">
                <div class="tip-item">
                    <div class="tip-icon">🎯</div>
                    <h6>Consistent Training</h6>
                    <p>Regular, positive reinforcement training sessions help improve obedience and behavior.</p>
                </div>
                <div class="tip-item">
                    <div class="tip-icon">🏃</div>
                    <h6>Daily Exercise</h6>
                    <p>Adequate physical activity reduces behavioral issues and improves overall well-being.</p>
                </div>
                <div class="tip-item">
                    <div class="tip-icon">🧠</div>
                    <h6>Mental Stimulation</h6>
                    <p>Puzzle toys and training games keep your pet mentally engaged and well-behaved.</p>
                </div>
                <div class="tip-item">
                    <div class="tip-icon">👥</div>
                    <h6>Socialization</h6>
                    <p>Regular interaction with other pets and people improves social behaviors.</p>
                </div>
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

.stats-section-modern {
    padding: 40px 0;
    background: #f8f9ff;
    margin-top: -30px;
    position: relative;
    z-index: 2;
}

.stat-card-modern {
    background: white;
    border-radius: 20px;
    padding: 24px;
    display: flex;
    align-items: center;
    gap: 20px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(0, 0, 0, 0.05);
    transition: all 0.3s ease;
    height: 100%;
}

.stat-card-modern:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
    flex-shrink: 0;
}

.stat-card-modern.primary .stat-icon { background: linear-gradient(135deg, #fbbf24, #f59e0b); }
.stat-card-modern.success .stat-icon { background: linear-gradient(135deg, #10b981, #059669); }
.stat-card-modern.info .stat-icon { background: linear-gradient(135deg, #3b82f6, #1d4ed8); }
.stat-card-modern.warning .stat-icon { background: linear-gradient(135deg, #f59e0b, #d97706); }

.stat-number {
    font-size: 2rem;
    font-weight: 800;
    color: #1e293b;
    margin-bottom: 4px;
}

.stat-label {
    color: #64748b;
    font-weight: 500;
    margin: 0;
}

.rating-stars {
    color: #fbbf24;
    margin-top: 8px;
}

.behavior-categories-section {
    padding: 60px 0;
    background: white;
}

.behavior-overview-card {
    background: white;
    border-radius: 24px;
    padding: 40px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(0, 0, 0, 0.05);
}

.behavior-overview-card .card-header {
    text-align: center;
    margin-bottom: 40px;
}

.behavior-overview-card .card-header h4 {
    color: #1e293b;
    font-weight: 700;
    margin-bottom: 8px;
}

.behavior-overview-card .card-header p {
    color: #64748b;
    margin: 0;
}

.behavior-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 24px;
}

.behavior-category {
    background: #f8fafc;
    padding: 24px;
    border-radius: 16px;
    border: 1px solid #e2e8f0;
}

.category-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}

.category-name {
    font-weight: 600;
    color: #374151;
}

.category-score {
    font-weight: 700;
    color: #667eea;
}

.progress-bar {
    height: 8px;
    background: #e2e8f0;
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 12px;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #667eea, #764ba2);
    border-radius: 4px;
    transition: width 0.3s ease;
}

.level-badge {
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 600;
}

.level-badge.excellent { background: #dcfce7; color: #166534; }
.level-badge.good { background: #dbeafe; color: #1e40af; }
.level-badge.fair { background: #fef3c7; color: #92400e; }
.level-badge.poor { background: #fee2e2; color: #991b1b; }

.reviews-section-modern {
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

.reviews-timeline {
    display: flex;
    flex-direction: column;
    gap: 24px;
}

.review-card-modern {
    background: white;
    border-radius: 20px;
    padding: 32px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(0, 0, 0, 0.05);
    transition: all 0.3s ease;
}

.review-card-modern:hover {
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
}

.review-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.sitter-info {
    display: flex;
    align-items: center;
    gap: 16px;
}

.sitter-avatar {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    overflow: hidden;
}

.sitter-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.avatar-placeholder {
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, #f1f5f9, #e2e8f0);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #9ca3af;
    font-size: 1.5rem;
}

.sitter-details h6 {
    color: #1e293b;
    font-weight: 600;
    margin-bottom: 4px;
}

.review-date {
    color: #64748b;
    font-size: 0.9rem;
}

.separator {
    margin: 0 8px;
}

.booking-period {
    color: #9ca3af;
}

.overall-rating {
    text-align: right;
}

.overall-rating .rating-stars {
    margin-bottom: 4px;
}

.rating-number {
    color: #667eea;
    font-weight: 600;
}

.behavior-category-badge {
    margin-bottom: 16px;
}

.behavior-category-badge span {
    background: #f1f5f9;
    color: #64748b;
    padding: 6px 12px;
    border-radius: 12px;
    font-size: 0.85rem;
    font-weight: 500;
}

.review-content {
    margin-bottom: 20px;
}

.review-content p {
    color: #374151;
    line-height: 1.6;
    margin: 0;
}

.specific-behaviors {
    background: #f8fafc;
    padding: 20px;
    border-radius: 12px;
    margin-bottom: 20px;
}

.specific-behaviors h6 {
    color: #374151;
    font-weight: 600;
    margin-bottom: 16px;
    font-size: 0.9rem;
}

.behavior-ratings {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 12px;
}

.behavior-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.behavior-name {
    color: #64748b;
    font-size: 0.9rem;
}

.behavior-rating {
    display: flex;
    align-items: center;
    gap: 8px;
}

.mini-stars {
    color: #fbbf24;
    font-size: 0.8rem;
}

.rating-value {
    color: #667eea;
    font-weight: 600;
    font-size: 0.85rem;
}

.recommendations {
    background: #f0f9ff;
    padding: 16px;
    border-radius: 12px;
    border-left: 4px solid #3b82f6;
}

.recommendations h6 {
    color: #1e40af;
    font-weight: 600;
    margin-bottom: 8px;
    font-size: 0.9rem;
}

.recommendations p {
    color: #1e40af;
    margin: 0;
    line-height: 1.5;
}

.tips-section-modern {
    padding: 80px 0;
    background: #f8f9ff;
}

.tips-card-large {
    background: white;
    border-radius: 24px;
    padding: 40px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(0, 0, 0, 0.05);
}

.tips-header {
    text-align: center;
    margin-bottom: 40px;
}

.tips-header h4 {
    color: #1e293b;
    font-weight: 700;
    margin-bottom: 8px;
}

.tips-header p {
    color: #64748b;
    margin: 0;
}

.tips-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 24px;
}

.tip-item {
    text-align: center;
    padding: 24px;
    background: #f8fafc;
    border-radius: 16px;
    border: 1px solid #e2e8f0;
}

.tip-icon {
    font-size: 2.5rem;
    margin-bottom: 16px;
}

.tip-item h6 {
    color: #1e293b;
    font-weight: 600;
    margin-bottom: 8px;
}

.tip-item p {
    color: #64748b;
    margin: 0;
    line-height: 1.5;
}

@media (max-width: 768px) {
    .page-title {
        font-size: 2.5rem;
    }
    
    .page-actions {
        text-align: left;
        margin-top: 24px;
    }
    
    .review-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 16px;
    }
    
    .overall-rating {
        text-align: left;
    }
    
    .behavior-ratings {
        grid-template-columns: 1fr;
    }
}
</style>

<?php
// Include footer
include_once '../includes/footer.php';

// Close database connection
$conn->close();
?>