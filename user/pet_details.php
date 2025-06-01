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
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "Invalid pet ID.";
    header("Location: pets.php");
    exit();
}

// Include database connection
require_once '../config/db_connect.php';

$user_id = $_SESSION['user_id'];
$pet_id = $_GET['id'];

// Get pet details
$pet_sql = "SELECT * FROM pet_profile WHERE petID = ? AND userID = ?";
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

// Get recent medical records (last 3)
$medical_sql = "SELECT * FROM pet_medical_records WHERE petID = ? ORDER BY record_date DESC, created_at DESC LIMIT 3";
$medical_stmt = $conn->prepare($medical_sql);
$medical_stmt->bind_param("i", $pet_id);
$medical_stmt->execute();
$medical_result = $medical_stmt->get_result();
$recent_medical_records = [];
while ($row = $medical_result->fetch_assoc()) {
    $recent_medical_records[] = $row;
}
$medical_stmt->close();

// Get total medical records count
$count_sql = "SELECT COUNT(*) as record_count FROM pet_medical_records WHERE petID = ?";
$count_stmt = $conn->prepare($count_sql);
$count_stmt->bind_param("i", $pet_id);
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$record_count = $count_result->fetch_assoc()['record_count'];
$count_stmt->close();

// Include header
include_once '../includes/header.php';
?>

<!-- Modern Page Header -->
<section class="page-header-modern">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <div class="page-header-content">
                    <span class="page-badge">🐾 Pet Profile</span>
                    <h1 class="page-title"><?php echo htmlspecialchars($pet['petName']); ?></h1>
                    <p class="page-subtitle">Complete profile and health information</p>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="page-actions">
                    <a href="edit_pet.php?id=<?php echo $pet_id; ?>" class="btn btn-outline-light">
                        <i class="fas fa-edit me-2"></i> Edit Profile
                    </a>
                    <a href="pets.php" class="btn btn-outline-light">
                        <i class="fas fa-arrow-left me-2"></i> Back to Pets
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Pet Information -->
<section class="pet-info-section">
    <div class="container">
        <div class="row">
            <div class="col-lg-4">
                <!-- Pet Photo Card -->
                <div class="pet-photo-card">
                    <div class="pet-image">
                        <?php if (!empty($pet['image'])): ?>
                            <img src="../assets/images/pets/<?php echo htmlspecialchars($pet['image']); ?>" alt="<?php echo htmlspecialchars($pet['petName']); ?>">
                        <?php else: ?>
                            <div class="image-placeholder">
                                <i class="fas fa-paw"></i>
                                <span>No Photo</span>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="pet-basic-info">
                        <h3><?php echo htmlspecialchars($pet['petName']); ?></h3>
                        <div class="pet-type-badge">
                            <?php 
                            $type_icons = [
                                'Dog' => '🐕', 'Cat' => '🐱', 'Bird' => '🐦',
                                'Fish' => '🐠', 'Rabbit' => '🐰', 'Hamster' => '🐹'
                            ];
                            echo isset($type_icons[$pet['type']]) ? $type_icons[$pet['type']] : '🐾';
                            ?>
                            <?php echo htmlspecialchars($pet['type']); ?>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions Card -->
                <div class="quick-actions-card">
                    <h5><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
                    <div class="actions-list">
                        <!-- Add Medical Record Button - Primary Action -->
                        <a href="add_medical_record.php?pet_id=<?php echo $pet_id; ?>" class="action-btn primary">
                            <div class="action-icon">
                                <i class="fas fa-notes-medical"></i>
                            </div>
                            <div class="action-content">
                                <h6>Add Medical Record</h6>
                                <p>Record health visit or treatment</p>
                            </div>
                            <i class="fas fa-arrow-right action-arrow"></i>
                        </a>
                        
                        <a href="add_reminder.php?pet_id=<?php echo $pet_id; ?>" class="action-btn">
                            <div class="action-icon">
                                <i class="fas fa-bell"></i>
                            </div>
                            <div class="action-content">
                                <h6>Add Reminder</h6>
                                <p>Set medical reminder</p>
                            </div>
                        </a>
                        
                        <a href="../pet_sitters.php" class="action-btn">
                            <div class="action-icon">
                                <i class="fas fa-search"></i>
                            </div>
                            <div class="action-content">
                                <h6>Find Pet Sitter</h6>
                                <p>Book care services</p>
                            </div>
                        </a>
                        
                        <a href="behavioral_reviews.php?pet_id=<?php echo $pet_id; ?>" class="action-btn">
                            <div class="action-icon">
                                <i class="fas fa-star"></i>
                            </div>
                            <div class="action-content">
                                <h6>Behavior Reviews</h6>
                                <p>View sitter feedback</p>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-8">
                <!-- Pet Details Card -->
                <div class="pet-details-card">
                    <div class="card-header">
                        <h5><i class="fas fa-info-circle me-2"></i>Pet Information</h5>
                    </div>
                    <div class="details-grid">
                        <div class="detail-item">
                            <span class="label">Breed</span>
                            <span class="value"><?php echo htmlspecialchars($pet['breed']); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="label">Age</span>
                            <span class="value"><?php echo $pet['age']; ?> years old</span>
                        </div>
                        <div class="detail-item">
                            <span class="label">Gender</span>
                            <span class="value"><?php echo htmlspecialchars($pet['sex']); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="label">Color</span>
                            <span class="value"><?php echo htmlspecialchars($pet['color']); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Medical Records Card -->
                <div class="medical-records-card">
                    <div class="card-header">
                        <div class="header-content">
                            <h5><i class="fas fa-file-medical me-2"></i>Medical Records</h5>
                            <span class="record-count"><?php echo $record_count; ?> records</span>
                        </div>
                        <div class="header-actions">
                            <!-- Primary Add Medical Record Button in Header -->
                            <a href="add_medical_record.php?pet_id=<?php echo $pet_id; ?>" class="btn btn-success btn-sm">
                                <i class="fas fa-plus me-1"></i> Add Record
                            </a>
                            <?php if ($record_count > 0): ?>
                                <a href="medical_records.php?pet_id=<?php echo $pet_id; ?>" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-list me-1"></i> View All
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="medical-content">
                        <?php if (empty($recent_medical_records)): ?>
                            <div class="empty-medical-state">
                                <div class="empty-icon">
                                    <i class="fas fa-notes-medical"></i>
                                </div>
                                <h6>No Medical Records</h6>
                                <p>Start tracking <?php echo htmlspecialchars($pet['petName']); ?>'s health by adding medical records.</p>
                                <!-- Prominent Add Record Button for Empty State -->
                                <a href="add_medical_record.php?pet_id=<?php echo $pet_id; ?>" class="btn btn-primary-gradient">
                                    <i class="fas fa-plus me-2"></i> Add First Medical Record
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="recent-records">
                                <h6>Recent Records</h6>
                                <?php foreach ($recent_medical_records as $record): ?>
                                    <div class="record-item">
                                        <div class="record-type">
                                            <?php
                                            $type_icons = [
                                                'Vaccination' => '💉', 'Health Checkup' => '🔍',
                                                'Emergency Visit' => '🚨', 'Surgery' => '🏥',
                                                'Dental Care' => '🦷', 'Lab Results' => '🧪',
                                                'Medication' => '💊', 'Other' => '📋'
                                            ];
                                            echo isset($type_icons[$record['record_type']]) ? $type_icons[$record['record_type']] : '📋';
                                            ?>
                                            <?php echo htmlspecialchars($record['record_type']); ?>
                                        </div>
                                        <div class="record-details">
                                            <div class="record-date"><?php echo date('M d, Y', strtotime($record['record_date'])); ?></div>
                                            <div class="record-description"><?php echo htmlspecialchars(substr($record['description'], 0, 100)) . (strlen($record['description']) > 100 ? '...' : ''); ?></div>
                                            <?php if (!empty($record['veterinarian'])): ?>
                                                <div class="record-vet">Dr. <?php echo htmlspecialchars($record['veterinarian']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!empty($record['cost'])): ?>
                                            <div class="record-cost">$<?php echo number_format($record['cost'], 2); ?></div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                                
                                <?php if ($record_count > 3): ?>
                                    <div class="view-all-records">
                                        <a href="medical_records.php?pet_id=<?php echo $pet_id; ?>" class="btn btn-outline-primary w-100">
                                            View All <?php echo $record_count; ?> Medical Records
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
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

.page-actions {
    text-align: right;
    position: relative;
    z-index: 2;
}

.pet-info-section {
    padding: 80px 0;
    background: white;
}

.pet-photo-card {
    background: white;
    border-radius: 20px;
    padding: 24px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(0, 0, 0, 0.05);
    margin-bottom: 24px;
    text-align: center;
}

.pet-image {
    width: 200px;
    height: 200px;
    border-radius: 50%;
    overflow: hidden;
    margin: 0 auto 20px;
    border: 4px solid #f1f5f9;
}

.pet-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.image-placeholder {
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, #f1f5f9, #e2e8f0);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: #9ca3af;
    font-size: 2rem;
}

.pet-basic-info h3 {
    color: #1e293b;
    font-weight: 700;
    margin-bottom: 12px;
}

.pet-type-badge {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    padding: 8px 16px;
    border-radius: 50px;
    font-weight: 600;
    display: inline-block;
}

.quick-actions-card {
    background: white;
    border-radius: 20px;
    padding: 24px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(0, 0, 0, 0.05);
}

.quick-actions-card h5 {
    color: #1e293b;
    font-weight: 600;
    margin-bottom: 20px;
}

.actions-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.action-btn {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 16px;
    background: #f8fafc;
    border-radius: 12px;
    text-decoration: none;
    color: #374151;
    transition: all 0.3s ease;
    border: 1px solid #e2e8f0;
    position: relative;
}

.action-btn.primary {
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
    border-color: #10b981;
    box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
}

.action-btn:hover {
    transform: translateX(4px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
    color: #374151;
}

.action-btn.primary:hover {
    color: white;
    box-shadow: 0 8px 25px rgba(16, 185, 129, 0.4);
}

.action-icon {
    width: 40px;
    height: 40px;
    background: #667eea;
    color: white;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.action-btn.primary .action-icon {
    background: rgba(255, 255, 255, 0.2);
}

.action-content h6 {
    margin: 0 0 4px 0;
    font-weight: 600;
}

.action-content p {
    margin: 0;
    font-size: 0.85rem;
    opacity: 0.8;
}

.action-arrow {
    margin-left: auto;
    opacity: 0.5;
    transition: all 0.3s ease;
}

.action-btn:hover .action-arrow {
    opacity: 1;
    transform: translateX(4px);
}

.pet-details-card, .medical-records-card {
    background: white;
    border-radius: 20px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(0, 0, 0, 0.05);
    margin-bottom: 24px;
}

.card-header {
    padding: 24px 24px 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.header-content {
    display: flex;
    align-items: center;
    gap: 12px;
}

.header-content h5 {
    color: #1e293b;
    font-weight: 600;
    margin: 0;
}

.record-count {
    background: #f1f5f9;
    color: #64748b;
    padding: 4px 12px;
    border-radius: 50px;
    font-size: 0.8rem;
    font-weight: 500;
}

.header-actions {
    display: flex;
    gap: 8px;
}

.details-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    padding: 0 24px 24px;
}

.detail-item {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.label {
    color: #9ca3af;
    font-size: 0.875rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.value {
    color: #1e293b;
    font-weight: 600;
    font-size: 1.1rem;
}

.medical-content {
    padding: 0 24px 24px;
}

.empty-medical-state {
    text-align: center;
    padding: 40px 20px;
    background: #f8f9ff;
    border-radius: 12px;
    border: 2px dashed #d1d5db;
}

.empty-icon {
    font-size: 3rem;
    color: #9ca3af;
    margin-bottom: 16px;
}

.empty-medical-state h6 {
    color: #374151;
    margin-bottom: 8px;
    font-weight: 600;
}

.empty-medical-state p {
    color: #6b7280;
    margin-bottom: 24px;
}

.recent-records h6 {
    color: #374151;
    font-weight: 600;
    margin-bottom: 16px;
}

.record-item {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 16px;
    background: #f8fafc;
    border-radius: 12px;
    margin-bottom: 12px;
    border: 1px solid #e2e8f0;
}

.record-type {
    background: white;
    padding: 8px 12px;
    border-radius: 50px;
    font-size: 0.85rem;
    font-weight: 600;
    color: #667eea;
    white-space: nowrap;
}

.record-details {
    flex: 1;
}

.record-date {
    color: #667eea;
    font-weight: 600;
    font-size: 0.9rem;
    margin-bottom: 4px;
}

.record-description {
    color: #374151;
    font-size: 0.9rem;
    margin-bottom: 4px;
}

.record-vet {
    color: #64748b;
    font-size: 0.8rem;
}

.record-cost {
    color: #10b981;
    font-weight: 700;
    font-size: 1.1rem;
}

.view-all-records {
    margin-top: 16px;
    padding-top: 16px;
    border-top: 1px solid #e2e8f0;
}

@media (max-width: 768px) {
    .page-title {
        font-size: 2.5rem;
    }
    
    .page-actions {
        text-align: left;
        margin-top: 24px;
    }
    
    .card-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 16px;
    }
    
    .header-actions {
        width: 100%;
        justify-content: flex-start;
    }
    
    .record-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
    }
}
</style>

<?php
// Include footer
include_once '../includes/footer.php';

// Close database connection
$conn->close();
?>