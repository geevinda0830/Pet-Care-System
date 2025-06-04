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

// Check if booking ID is provided
if (!isset($_GET['booking_id']) || empty($_GET['booking_id'])) {
    $_SESSION['error_message'] = "Invalid booking ID.";
    header("Location: bookings.php");
    exit();
}

// Include database connection
require_once '../config/db_connect.php';

$user_id = $_SESSION['user_id'];
$booking_id = $_GET['booking_id'];

// Get booking and pet details
$booking_sql = "SELECT b.*, p.petID, p.petName, p.type, p.breed, p.age, p.sex, p.color, p.image, 
                po.fullName as ownerName, po.contact as ownerContact
                FROM booking b 
                JOIN pet_profile p ON b.petID = p.petID 
                JOIN pet_owner po ON b.userID = po.userID
                WHERE b.bookingID = ? AND b.sitterID = ? AND b.status = 'Completed'";
$booking_stmt = $conn->prepare($booking_sql);
$booking_stmt->bind_param("ii", $booking_id, $user_id);
$booking_stmt->execute();
$booking_result = $booking_stmt->get_result();

if ($booking_result->num_rows === 0) {
    $_SESSION['error_message'] = "Booking not found, not completed, or does not belong to you.";
    header("Location: bookings.php");
    exit();
}

$booking = $booking_result->fetch_assoc();
$booking_stmt->close();

// Check if pet has already been ranked for this booking
$ranking_check_sql = "SELECT rankingID FROM pet_ranking WHERE petID = ? AND userID = ? AND bookingID = ?";
$ranking_check_stmt = $conn->prepare($ranking_check_sql);
$ranking_check_stmt->bind_param("iii", $booking['petID'], $user_id, $booking_id);
$ranking_check_stmt->execute();
$ranking_check_result = $ranking_check_stmt->get_result();

if ($ranking_check_result->num_rows > 0) {
    $_SESSION['error_message'] = "You have already ranked this pet for this booking.";
    header("Location: bookings.php");
    exit();
}

$ranking_check_stmt->close();

// Initialize variables
$rating = '';
$behavior_notes = '';
$obedience_level = '';
$energy_level = '';
$friendliness = '';
$special_needs = '';
$feeding_notes = '';
$exercise_notes = '';
$overall_feedback = '';
$errors = array();

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $rating = $_POST['rating'];
    $behavior_notes = trim($_POST['behavior_notes']);
    $obedience_level = $_POST['obedience_level'];
    $energy_level = $_POST['energy_level'];
    $friendliness = $_POST['friendliness'];
    $special_needs = trim($_POST['special_needs']);
    $feeding_notes = trim($_POST['feeding_notes']);
    $exercise_notes = trim($_POST['exercise_notes']);
    $overall_feedback = trim($_POST['overall_feedback']);
    
    // Validate form data
    if (empty($rating) || !is_numeric($rating) || $rating < 1 || $rating > 5) {
        $errors[] = "Please provide a valid rating between 1 and 5.";
    }
    
    if (empty($behavior_notes)) {
        $errors[] = "Behavior notes are required.";
    }
    
    if (empty($obedience_level)) {
        $errors[] = "Please select the obedience level.";
    }
    
    if (empty($energy_level)) {
        $errors[] = "Please select the energy level.";
    }
    
    if (empty($friendliness)) {
        $errors[] = "Please select the friendliness level.";
    }
    
    if (empty($overall_feedback)) {
        $errors[] = "Overall feedback is required.";
    }
    
    // If no errors, insert ranking
    if (empty($errors)) {
        $insert_sql = "INSERT INTO pet_ranking (petID, userID, bookingID, rating, behavior_notes, obedience_level, energy_level, friendliness, special_needs, feeding_notes, exercise_notes, overall_feedback, created_at) 
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("iiiissssssss", $booking['petID'], $user_id, $booking_id, $rating, $behavior_notes, $obedience_level, $energy_level, $friendliness, $special_needs, $feeding_notes, $exercise_notes, $overall_feedback);
        
        if ($insert_stmt->execute()) {
            $_SESSION['success_message'] = "Pet ranking submitted successfully!";
            header("Location: bookings.php");
            exit();
        } else {
            $errors[] = "Error submitting ranking: " . $conn->error;
        }
        
        $insert_stmt->close();
    }
}

// Include header
include_once '../includes/header.php';
?>

<!-- Modern Pet Ranking Header -->
<section class="pet-ranking-header-section">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <div class="pet-ranking-header-content">
                    <span class="section-badge">⭐ Pet Assessment</span>
                    <h1 class="pet-ranking-title">Rate <span class="text-gradient"><?php echo htmlspecialchars($booking['petName']); ?></span></h1>
                    <p class="pet-ranking-subtitle">Share your experience and help future pet sitters understand this pet's behavior.</p>
                </div>
            </div>
            <div class="col-lg-4 text-lg-end">
                <a href="bookings.php" class="btn btn-outline-light btn-lg">
                    <i class="fas fa-arrow-left me-2"></i> Back to Bookings
                </a>
            </div>
        </div>
    </div>
</section>

<!-- Pet Information Card -->
<section class="pet-info-section">
    <div class="container">
        <div class="pet-info-card">
            <div class="pet-avatar-section">
                <div class="pet-avatar-large">
                    <?php if (!empty($booking['image'])): ?>
                        <img src="../assets/images/pets/<?php echo htmlspecialchars($booking['image']); ?>" alt="<?php echo htmlspecialchars($booking['petName']); ?>">
                    <?php else: ?>
                        <div class="pet-placeholder">
                            <i class="fas fa-paw"></i>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="pet-basic-info">
                    <h3><?php echo htmlspecialchars($booking['petName']); ?></h3>
                    <div class="pet-details-grid">
                        <div class="detail-item">
                            <span class="detail-label">Type:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($booking['type']); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Breed:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($booking['breed']); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Age:</span>
                            <span class="detail-value"><?php echo $booking['age']; ?> years</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Sex:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($booking['sex']); ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="booking-summary">
                <h6>Booking Details</h6>
                <div class="summary-grid">
                    <div class="summary-item">
                        <i class="fas fa-user"></i>
                        <span>Owner: <?php echo htmlspecialchars($booking['ownerName']); ?></span>
                    </div>
                    <div class="summary-item">
                        <i class="fas fa-calendar"></i>
                        <span><?php echo date('M d, Y', strtotime($booking['checkInDate'])); ?> - <?php echo date('M d, Y', strtotime($booking['checkOutDate'])); ?></span>
                    </div>
                    <div class="summary-item">
                        <i class="fas fa-clock"></i>
                        <span><?php echo date('h:i A', strtotime($booking['checkInTime'])); ?> - <?php echo date('h:i A', strtotime($booking['checkOutTime'])); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Pet Ranking Form -->
<section class="pet-ranking-form-section">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger alert-modern">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <div class="error-list">
                            <?php foreach($errors as $error): ?>
                                <div class="error-item"><?php echo htmlspecialchars($error); ?></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="ranking-form-card">
                    <div class="form-header">
                        <div class="form-icon">
                            <i class="fas fa-star"></i>
                        </div>
                        <h3>Pet Behavior Assessment</h3>
                        <p>Please provide detailed feedback about <?php echo htmlspecialchars($booking['petName']); ?>'s behavior during the service</p>
                    </div>
                    
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . "?booking_id=" . $booking_id); ?>" method="post" class="ranking-form">
                        <!-- Overall Rating -->
                        <div class="form-section">
                            <h6 class="section-title">Overall Rating</h6>
                            <div class="rating-input-section">
                                <div class="star-rating-input" id="star-rating">
                                    <input type="radio" name="rating" value="1" id="star1" required>
                                    <label for="star1" class="star-label">
                                        <i class="far fa-star"></i>
                                        <span class="rating-text">Poor</span>
                                    </label>
                                    
                                    <input type="radio" name="rating" value="2" id="star2">
                                    <label for="star2" class="star-label">
                                        <i class="far fa-star"></i>
                                        <span class="rating-text">Fair</span>
                                    </label>
                                    
                                    <input type="radio" name="rating" value="3" id="star3">
                                    <label for="star3" class="star-label">
                                        <i class="far fa-star"></i>
                                        <span class="rating-text">Good</span>
                                    </label>
                                    
                                    <input type="radio" name="rating" value="4" id="star4">
                                    <label for="star4" class="star-label">
                                        <i class="far fa-star"></i>
                                        <span class="rating-text">Very Good</span>
                                    </label>
                                    
                                    <input type="radio" name="rating" value="5" id="star5">
                                    <label for="star5" class="star-label">
                                        <i class="far fa-star"></i>
                                        <span class="rating-text">Excellent</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Behavior Assessment -->
                        <div class="form-section">
                            <h6 class="section-title">Behavior Assessment</h6>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group-modern">
                                        <label for="obedience_level" class="form-label-modern">Obedience Level <span class="required">*</span></label>
                                        <select class="form-control-modern" id="obedience_level" name="obedience_level" required>
                                            <option value="">Select Level</option>
                                            <option value="Excellent" <?php echo ($obedience_level === 'Excellent') ? 'selected' : ''; ?>>Excellent</option>
                                            <option value="Good" <?php echo ($obedience_level === 'Good') ? 'selected' : ''; ?>>Good</option>
                                            <option value="Average" <?php echo ($obedience_level === 'Average') ? 'selected' : ''; ?>>Average</option>
                                            <option value="Needs Work" <?php echo ($obedience_level === 'Needs Work') ? 'selected' : ''; ?>>Needs Work</option>
                                            <option value="Poor" <?php echo ($obedience_level === 'Poor') ? 'selected' : ''; ?>>Poor</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="form-group-modern">
                                        <label for="energy_level" class="form-label-modern">Energy Level <span class="required">*</span></label>
                                        <select class="form-control-modern" id="energy_level" name="energy_level" required>
                                            <option value="">Select Level</option>
                                            <option value="Very High" <?php echo ($energy_level === 'Very High') ? 'selected' : ''; ?>>Very High</option>
                                            <option value="High" <?php echo ($energy_level === 'High') ? 'selected' : ''; ?>>High</option>
                                            <option value="Moderate" <?php echo ($energy_level === 'Moderate') ? 'selected' : ''; ?>>Moderate</option>
                                            <option value="Low" <?php echo ($energy_level === 'Low') ? 'selected' : ''; ?>>Low</option>
                                            <option value="Very Low" <?php echo ($energy_level === 'Very Low') ? 'selected' : ''; ?>>Very Low</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="form-group-modern">
                                        <label for="friendliness" class="form-label-modern">Friendliness <span class="required">*</span></label>
                                        <select class="form-control-modern" id="friendliness" name="friendliness" required>
                                            <option value="">Select Level</option>
                                            <option value="Very Friendly" <?php echo ($friendliness === 'Very Friendly') ? 'selected' : ''; ?>>Very Friendly</option>
                                            <option value="Friendly" <?php echo ($friendliness === 'Friendly') ? 'selected' : ''; ?>>Friendly</option>
                                            <option value="Neutral" <?php echo ($friendliness === 'Neutral') ? 'selected' : ''; ?>>Neutral</option>
                                            <option value="Shy" <?php echo ($friendliness === 'Shy') ? 'selected' : ''; ?>>Shy</option>
                                            <option value="Aggressive" <?php echo ($friendliness === 'Aggressive') ? 'selected' : ''; ?>>Aggressive</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Detailed Notes -->
                        <div class="form-section">
                            <h6 class="section-title">Detailed Notes</h6>
                            <div class="form-group-modern">
                                <label for="behavior_notes" class="form-label-modern">Behavior Notes <span class="required">*</span></label>
                                <textarea class="form-control-modern" id="behavior_notes" name="behavior_notes" rows="4" placeholder="Describe the pet's behavior, temperament, and any notable incidents..." required><?php echo htmlspecialchars($behavior_notes); ?></textarea>
                            </div>
                            
                            <div class="form-group-modern">
                                <label for="special_needs" class="form-label-modern">Special Needs or Considerations</label>
                                <textarea class="form-control-modern" id="special_needs" name="special_needs" rows="3" placeholder="Any special needs, medical considerations, or behavioral quirks..."><?php echo htmlspecialchars($special_needs); ?></textarea>
                            </div>
                        </div>
                        
                        <!-- Care Notes -->
                        <div class="form-section">
                            <h6 class="section-title">Care & Activity Notes</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group-modern">
                                        <label for="feeding_notes" class="form-label-modern">Feeding Notes</label>
                                        <textarea class="form-control-modern" id="feeding_notes" name="feeding_notes" rows="3" placeholder="Eating habits, food preferences, feeding schedule..."><?php echo htmlspecialchars($feeding_notes); ?></textarea>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-group-modern">
                                        <label for="exercise_notes" class="form-label-modern">Exercise & Activity Notes</label>
                                        <textarea class="form-control-modern" id="exercise_notes" name="exercise_notes" rows="3" placeholder="Activity level, favorite exercises, play preferences..."><?php echo htmlspecialchars($exercise_notes); ?></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Overall Feedback -->
                        <div class="form-section">
                            <h6 class="section-title">Overall Feedback</h6>
                            <div class="form-group-modern">
                                <label for="overall_feedback" class="form-label-modern">Summary & Recommendations <span class="required">*</span></label>
                                <textarea class="form-control-modern" id="overall_feedback" name="overall_feedback" rows="4" placeholder="Provide an overall summary and any recommendations for future pet sitters..." required><?php echo htmlspecialchars($overall_feedback); ?></textarea>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <a href="bookings.php" class="btn btn-outline-secondary btn-lg">
                                <i class="fas fa-times me-2"></i> Cancel
                            </a>
                            <button type="submit" class="btn btn-primary-gradient btn-lg">
                                <i class="fas fa-star me-2"></i> Submit Ranking
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
.pet-ranking-header-section {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 80px 0;
    position: relative;
    overflow: hidden;
}

.pet-ranking-header-section::before {
    content: '';
    position: absolute;
    width: 100%;
    height: 100%;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="20" cy="20" r="2" fill="rgba(255,255,255,0.1)"/><circle cx="80" cy="40" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="40" cy="80" r="1.5" fill="rgba(255,255,255,0.1)"/></svg>');
    animation: float 20s infinite linear;
}

.pet-ranking-title {
    font-size: 3.5rem;
    font-weight: 800;
    line-height: 1.2;
    margin-bottom: 24px;
}

.pet-ranking-subtitle {
    font-size: 1.2rem;
    opacity: 0.9;
}

.pet-info-section {
    padding: 0;
    margin-top: -40px;
    position: relative;
    z-index: 2;
}

.pet-info-card {
    background: white;
    border-radius: 20px;
    padding: 32px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    border: 1px solid rgba(0, 0, 0, 0.05);
    margin: 0 15px;
    display: flex;
    gap: 32px;
    align-items: center;
}

.pet-avatar-section {
    display: flex;
    align-items: center;
    gap: 24px;
    flex: 1;
}

.pet-avatar-large {
    width: 120px;
    height: 120px;
    border-radius: 20px;
    overflow: hidden;
    background: linear-gradient(135f, #f1f5f9, #e2e8f0);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.pet-avatar-large img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.pet-placeholder {
    font-size: 3rem;
    color: #cbd5e1;
}

.pet-basic-info h3 {
    font-size: 2rem;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 16px;
}

.pet-details-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}

.detail-item {
    display: flex;
    justify-content: space-between;
}

.detail-label {
    color: #64748b;
    font-weight: 500;
}

.detail-value {
    color: #1e293b;
    font-weight: 600;
}

.booking-summary {
    background: #f8f9ff;
    padding: 24px;
    border-radius: 16px;
    border-left: 4px solid #667eea;
}

.booking-summary h6 {
    color: #374151;
    font-weight: 600;
    margin-bottom: 16px;
}

.summary-grid {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.summary-item {
    display: flex;
    align-items: center;
    gap: 12px;
    color: #64748b;
}

.summary-item i {
    color: #667eea;
    width: 16px;
}

.pet-ranking-form-section {
    padding: 60px 0;
}

.ranking-form-card {
    background: white;
    border-radius: 24px;
    padding: 48px;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
    border: 1px solid rgba(0, 0, 0, 0.05);
}

.form-header {
    text-align: center;
    margin-bottom: 40px;
}

.form-icon {
    width: 80px;
    height: 80px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    color: white;
    margin: 0 auto 24px;
}

.form-header h3 {
    font-size: 2rem;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 12px;
}

.form-header p {
    color: #64748b;
    font-size: 1.1rem;
}

.form-section {
    margin-bottom: 40px;
    padding-bottom: 32px;
    border-bottom: 1px solid #f1f5f9;
}

.form-section:last-child {
    border-bottom: none;
    margin-bottom: 0;
}

.section-title {
    font-weight: 600;
    color: #374151;
    margin-bottom: 20px;
    font-size: 1.2rem;
}

.rating-input-section {
    background: #f8f9ff;
    padding: 32px;
    border-radius: 16px;
    border: 2px solid #e5e7eb;
}

.star-rating-input {
    display: flex;
    justify-content: center;
    gap: 16px;
}

.star-rating-input input[type="radio"] {
    display: none;
}

.star-label {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    padding: 16px;
    border-radius: 12px;
    transition: all 0.3s ease;
}

.star-label:hover {
    background: rgba(102, 126, 234, 0.1);
}

.star-label i {
    font-size: 2rem;
    color: #d1d5db;
    transition: all 0.3s ease;
}

.star-label:hover i,
.star-rating-input input[type="radio"]:checked + .star-label i {
    color: #fbbf24;
}

.rating-text {
    font-size: 0.9rem;
    color: #64748b;
    font-weight: 500;
}

.star-rating-input input[type="radio"]:checked + .star-label .rating-text {
    color: #667eea;
    font-weight: 600;
}

.form-group-modern {
    margin-bottom: 24px;
}

.form-label-modern {
    display: block;
    font-weight: 600;
    color: #374151;
    margin-bottom: 8px;
    font-size: 1rem;
}

.required {
    color: #ef4444;
}

.form-control-modern {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    font-size: 1rem;
    transition: all 0.3s ease;
    background: rgba(255, 255, 255, 0.8);
}

.form-control-modern:focus {
    outline: none;
    border-color: #667eea;
    background: white;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.form-actions {
    display: flex;
    gap: 16px;
    justify-content: center;
    margin-top: 40px;
}

.form-actions .btn {
    min-width: 150px;
}

.alert-modern {
    border: none;
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 32px;
    border-left: 4px solid #ef4444;
}

.error-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.error-item::before {
    content: '•';
    margin-right: 8px;
    color: #ef4444;
}

@media (max-width: 991px) {
    .pet-ranking-title {
        font-size: 2.5rem;
    }
    
    .pet-info-card {
        flex-direction: column;
        gap: 24px;
        margin: 0;
    }
    
    .pet-avatar-section {
        flex-direction: column;
        text-align: center;
    }
    
    .star-rating-input {
        flex-wrap: wrap;
        gap: 12px;
    }
    
    .ranking-form-card {
        padding: 32px 24px;
    }
}

@media (max-width: 768px) {
    .pet-ranking-header-section {
        text-align: center;
    }
    
    .pet-details-grid {
        grid-template-columns: 1fr;
    }
    
    .summary-grid {
        align-items: flex-start;
    }
    
    .star-rating-input {
        justify-content: space-between;
    }
    
    .star-label {
        padding: 8px;
    }
    
    .star-label i {
        font-size: 1.5rem;
    }
    
    .form-actions {
        flex-direction: column;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Star rating interaction
    const starInputs = document.querySelectorAll('input[name="rating"]');
    const starLabels = document.querySelectorAll('.star-label i');
    
    starInputs.forEach((input, index) => {
        input.addEventListener('change', function() {
            updateStarDisplay(index);
        });
    });
    
    function updateStarDisplay(selectedIndex) {
        starLabels.forEach((star, index) => {
            if (index <= selectedIndex) {
                star.className = 'fas fa-star';
            } else {
                star.className = 'far fa-star';
            }
        });
    }
    
    // Form validation
    const form = document.querySelector('.ranking-form');
    form.addEventListener('submit', function(e) {
        const rating = document.querySelector('input[name="rating"]:checked');
        if (!rating) {
            e.preventDefault();
            alert('Please select a rating.');
            return false;
        }
        
        const behaviorNotes = document.getElementById('behavior_notes').value;
        if (behaviorNotes.length < 20) {
            e.preventDefault();
            alert('Please provide more detailed behavior notes (at least 20 characters).');
            document.getElementById('behavior_notes').focus();
            return false;
        }
        
        const overallFeedback = document.getElementById('overall_feedback').value;
        if (overallFeedback.length < 20) {
            e.preventDefault();
            alert('Please provide more detailed overall feedback (at least 20 characters).');
            document.getElementById('overall_feedback').focus();
            return false;
        }
    });
});
</script>

<?php
// Include footer
include_once '../includes/footer.php';

// Close database connection
$conn->close();
?>