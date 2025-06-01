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

// Get all pets for this user
$pets_sql = "SELECT petID, petName, image FROM pet_profile WHERE userID = ? ORDER BY petName ASC";
$pets_stmt = $conn->prepare($pets_sql);
$pets_stmt->bind_param("i", $user_id);
$pets_stmt->execute();
$pets_result = $pets_stmt->get_result();
$pets = [];
while ($row = $pets_result->fetch_assoc()) {
    $pets[] = $row;
}
$pets_stmt->close();

// Check if pets exist
if (empty($pets)) {
    $_SESSION['error_message'] = "You need to add at least one pet before creating reminders.";
    header("Location: add_pet.php");
    exit();
}

// Initialize variables
$pet_id = $reminder_type = $title = $description = $due_date = $reminder_date = '';
$recurring = $recurring_interval = '';
$errors = array();

// Pre-fill based on URL parameters
$preset_type = isset($_GET['type']) ? $_GET['type'] : '';
$preset_pet_id = isset($_GET['pet_id']) ? $_GET['pet_id'] : '';

if (!empty($preset_type)) {
    switch ($preset_type) {
        case 'vaccination':
            $reminder_type = 'Vaccination';
            $title = 'Annual Vaccination Due';
            $description = 'Schedule annual vaccination including rabies, distemper, and other core vaccines.';
            break;
        case 'checkup':
            $reminder_type = 'Health Checkup';
            $title = 'Routine Health Checkup';
            $description = 'Schedule routine health examination and wellness check.';
            break;
    }
}

if (!empty($preset_pet_id) && in_array($preset_pet_id, array_column($pets, 'petID'))) {
    $pet_id = $preset_pet_id;
}

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $pet_id = $_POST['pet_id'];
    $reminder_type = trim($_POST['reminder_type']);
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $due_date = $_POST['due_date'];
    $reminder_date = $_POST['reminder_date'];
    $recurring = isset($_POST['recurring']) ? 1 : 0;
    $recurring_interval = !empty($_POST['recurring_interval']) ? $_POST['recurring_interval'] : null;
    
    // Validate required fields
    if (empty($pet_id)) {
        $errors[] = "Please select a pet.";
    }
    
    if (empty($reminder_type)) {
        $errors[] = "Reminder type is required.";
    }
    
    if (empty($title)) {
        $errors[] = "Title is required.";
    }
    
    if (empty($due_date)) {
        $errors[] = "Due date is required.";
    }
    
    if (empty($reminder_date)) {
        $errors[] = "Reminder date is required.";
    }
    
    // Validate dates
    if (!empty($due_date) && !empty($reminder_date)) {
        if (strtotime($reminder_date) > strtotime($due_date)) {
            $errors[] = "Reminder date cannot be after due date.";
        }
        
        if (strtotime($reminder_date) < strtotime(date('Y-m-d'))) {
            $errors[] = "Reminder date cannot be in the past.";
        }
    }
    
    // Validate recurring interval if recurring is enabled
    if ($recurring && (empty($recurring_interval) || $recurring_interval < 1)) {
        $errors[] = "Recurring interval must be at least 1 day.";
    }
    
    // Validate pet ownership
    if (!empty($pet_id)) {
        $pet_check_sql = "SELECT petID FROM pet_profile WHERE petID = ? AND userID = ?";
        $pet_check_stmt = $conn->prepare($pet_check_sql);
        $pet_check_stmt->bind_param("ii", $pet_id, $user_id);
        $pet_check_stmt->execute();
        $pet_check_result = $pet_check_stmt->get_result();
        
        if ($pet_check_result->num_rows === 0) {
            $errors[] = "Invalid pet selection.";
        }
        $pet_check_stmt->close();
    }
    
    // If no errors, insert reminder
    if (empty($errors)) {
        $sql = "INSERT INTO pet_medical_reminders (petID, reminder_type, title, description, due_date, reminder_date, recurring, recurring_interval) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isssssii", $pet_id, $reminder_type, $title, $description, $due_date, $reminder_date, $recurring, $recurring_interval);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Medical reminder created successfully!";
            header("Location: medical_reminders.php");
            exit();
        } else {
            $errors[] = "Error creating reminder: " . $conn->error;
        }
        
        $stmt->close();
    }
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
                    <span class="page-badge">⏰ Add Reminder</span>
                    <h1 class="page-title">Create Medical <span class="text-gradient">Reminder</span></h1>
                    <p class="page-subtitle">Set up health care reminders for your pets</p>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="page-actions">
                    <a href="medical_reminders.php" class="btn btn-outline-light">
                        <i class="fas fa-arrow-left me-2"></i> Back to Reminders
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Add Reminder Form -->
<section class="form-section-modern">
    <div class="container">
        <div class="row">
            <div class="col-lg-4">
                <!-- Quick Reminder Templates -->
                <div class="templates-card">
                    <h5><i class="fas fa-magic me-2"></i>Quick Templates</h5>
                    <p>Select a template to quickly set up common reminders</p>
                    
                    <div class="template-list">
                        <div class="template-item" onclick="useTemplate('vaccination')">
                            <div class="template-icon vaccination">
                                <i class="fas fa-syringe"></i>
                            </div>
                            <div class="template-content">
                                <h6>Vaccination</h6>
                                <p>Annual vaccination reminder</p>
                            </div>
                        </div>
                        
                        <div class="template-item" onclick="useTemplate('checkup')">
                            <div class="template-icon checkup">
                                <i class="fas fa-stethoscope"></i>
                            </div>
                            <div class="template-content">
                                <h6>Health Checkup</h6>
                                <p>Routine health examination</p>
                            </div>
                        </div>
                        
                        <div class="template-item" onclick="useTemplate('dental')">
                            <div class="template-icon dental">
                                <i class="fas fa-tooth"></i>
                            </div>
                            <div class="template-content">
                                <h6>Dental Care</h6>
                                <p>Dental cleaning and care</p>
                            </div>
                        </div>
                        
                        <div class="template-item" onclick="useTemplate('medication')">
                            <div class="template-icon medication">
                                <i class="fas fa-pills"></i>
                            </div>
                            <div class="template-content">
                                <h6>Medication</h6>
                                <p>Medicine refill reminder</p>
                            </div>
                        </div>
                        
                        <div class="template-item" onclick="useTemplate('grooming')">
                            <div class="template-icon grooming">
                                <i class="fas fa-cut"></i>
                            </div>
                            <div class="template-content">
                                <h6>Grooming</h6>
                                <p>Regular grooming session</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Tips Card -->
                <div class="tips-card">
                    <h6><i class="fas fa-lightbulb me-2"></i>Reminder Tips</h6>
                    <ul>
                        <li>Set reminders 1-2 weeks before due dates</li>
                        <li>Use recurring reminders for regular care</li>
                        <li>Add detailed descriptions for clarity</li>
                        <li>Consider your vet's schedule when setting dates</li>
                    </ul>
                </div>
            </div>
            
            <div class="col-lg-8">
                <div class="form-card-modern">
                    <div class="form-header">
                        <div class="form-icon">
                            <i class="fas fa-bell"></i>
                        </div>
                        <h4>Reminder Details</h4>
                        <p>Set up a new medical reminder for your pet</p>
                    </div>
                    
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger alert-modern">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <ul class="mb-0">
                                <?php foreach($errors as $error): ?>
                                    <li><?php echo $error; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="modern-form">
                        <!-- Pet Selection -->
                        <div class="form-group-modern">
                            <label for="pet_id" class="form-label-modern">Select Pet <span class="required">*</span></label>
                            <div class="pet-selector">
                                <?php foreach ($pets as $pet): ?>
                                    <div class="pet-option">
                                        <input type="radio" id="pet_<?php echo $pet['petID']; ?>" name="pet_id" value="<?php echo $pet['petID']; ?>" <?php echo ($pet_id == $pet['petID']) ? 'checked' : ''; ?> required>
                                        <label for="pet_<?php echo $pet['petID']; ?>" class="pet-option-label">
                                            <div class="pet-avatar">
                                                <?php if (!empty($pet['image'])): ?>
                                                    <img src="../assets/images/pets/<?php echo htmlspecialchars($pet['image']); ?>" alt="<?php echo htmlspecialchars($pet['petName']); ?>">
                                                <?php else: ?>
                                                    <div class="avatar-placeholder">
                                                        <i class="fas fa-paw"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <span class="pet-name"><?php echo htmlspecialchars($pet['petName']); ?></span>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <!-- Reminder Type and Title -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group-modern">
                                    <label for="reminder_type" class="form-label-modern">Reminder Type <span class="required">*</span></label>
                                    <div class="input-group-modern">
                                        <div class="input-icon">
                                            <i class="fas fa-tag"></i>
                                        </div>
                                        <select class="form-control-modern" id="reminder_type" name="reminder_type" required>
                                            <option value="">-- Select Type --</option>
                                            <option value="Vaccination" <?php echo ($reminder_type === 'Vaccination') ? 'selected' : ''; ?>>💉 Vaccination</option>
                                            <option value="Health Checkup" <?php echo ($reminder_type === 'Health Checkup') ? 'selected' : ''; ?>>🔍 Health Checkup</option>
                                            <option value="Dental Care" <?php echo ($reminder_type === 'Dental Care') ? 'selected' : ''; ?>>🦷 Dental Care</option>
                                            <option value="Medication" <?php echo ($reminder_type === 'Medication') ? 'selected' : ''; ?>>💊 Medication</option>
                                            <option value="Grooming" <?php echo ($reminder_type === 'Grooming') ? 'selected' : ''; ?>>✂️ Grooming</option>
                                            <option value="Deworming" <?php echo ($reminder_type === 'Deworming') ? 'selected' : ''; ?>>🪱 Deworming</option>
                                            <option value="Flea Treatment" <?php echo ($reminder_type === 'Flea Treatment') ? 'selected' : ''; ?>>🐛 Flea Treatment</option>
                                            <option value="Eye Exam" <?php echo ($reminder_type === 'Eye Exam') ? 'selected' : ''; ?>>👁️ Eye Exam</option>
                                            <option value="Weight Check" <?php echo ($reminder_type === 'Weight Check') ? 'selected' : ''; ?>>⚖️ Weight Check</option>
                                            <option value="Other" <?php echo ($reminder_type === 'Other') ? 'selected' : ''; ?>>📋 Other</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group-modern">
                                    <label for="title" class="form-label-modern">Title <span class="required">*</span></label>
                                    <div class="input-group-modern">
                                        <div class="input-icon">
                                            <i class="fas fa-heading"></i>
                                        </div>
                                        <input type="text" class="form-control-modern" id="title" name="title" value="<?php echo htmlspecialchars($title); ?>" placeholder="e.g., Annual Vaccination Due" required>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Description -->
                        <div class="form-group-modern">
                            <label for="description" class="form-label-modern">Description</label>
                            <div class="textarea-group-modern">
                                <textarea class="form-control-modern" id="description" name="description" rows="3" placeholder="Additional details about this reminder..."><?php echo htmlspecialchars($description); ?></textarea>
                            </div>
                        </div>
                        
                        <!-- Due Date and Reminder Date -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group-modern">
                                    <label for="due_date" class="form-label-modern">Due Date <span class="required">*</span></label>
                                    <div class="input-group-modern">
                                        <div class="input-icon">
                                            <i class="fas fa-calendar"></i>
                                        </div>
                                        <input type="date" class="form-control-modern" id="due_date" name="due_date" value="<?php echo htmlspecialchars($due_date); ?>" min="<?php echo date('Y-m-d'); ?>" required>
                                    </div>
                                    <div class="form-text">When this care is actually due</div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group-modern">
                                    <label for="reminder_date" class="form-label-modern">Remind Me On <span class="required">*</span></label>
                                    <div class="input-group-modern">
                                        <div class="input-icon">
                                            <i class="fas fa-bell"></i>
                                        </div>
                                        <input type="date" class="form-control-modern" id="reminder_date" name="reminder_date" value="<?php echo htmlspecialchars($reminder_date); ?>" min="<?php echo date('Y-m-d'); ?>" required>
                                    </div>
                                    <div class="form-text">When to send you the reminder</div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Quick Date Buttons -->
                        <div class="form-group-modern">
                            <label class="form-label-modern">Quick Date Selection</label>
                            <div class="quick-dates">
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setDueDateFromToday(7)">1 Week</button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setDueDateFromToday(30)">1 Month</button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setDueDateFromToday(90)">3 Months</button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setDueDateFromToday(180)">6 Months</button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setDueDateFromToday(365)">1 Year</button>
                            </div>
                        </div>
                        
                        <!-- Recurring Options -->
                        <div class="form-group-modern">
                            <div class="recurring-section">
                                <div class="recurring-header">
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" id="recurring" name="recurring" value="1" <?php echo $recurring ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="recurring">
                                            <strong>Make this a recurring reminder</strong>
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="recurring-options" id="recurringOptions" style="<?php echo $recurring ? 'display: block;' : 'display: none;'; ?>">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <label for="recurring_interval" class="form-label-modern">Repeat Every</label>
                                            <div class="input-group">
                                                <input type="number" class="form-control" id="recurring_interval" name="recurring_interval" value="<?php echo htmlspecialchars($recurring_interval); ?>" min="1" placeholder="30">
                                                <span class="input-group-text">days</span>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label-modern">Common Intervals</label>
                                            <div class="interval-buttons">
                                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="setInterval(30)">Monthly</button>
                                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="setInterval(90)">Quarterly</button>
                                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="setInterval(365)">Yearly</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Submit Button -->
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary-gradient btn-lg">
                                <i class="fas fa-bell me-2"></i> Create Reminder
                            </button>
                            <a href="medical_reminders.php" class="btn btn-outline-secondary btn-lg">
                                <i class="fas fa-times me-2"></i> Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
.page-header-modern {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
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

.form-section-modern {
    padding: 80px 0;
    background: white;
}

.templates-card {
    background: white;
    border-radius: 20px;
    padding: 24px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(0, 0, 0, 0.05);
    margin-bottom: 24px;
    position: sticky;
    top: 100px;
}

.templates-card h5 {
    color: #1e293b;
    font-weight: 600;
    margin-bottom: 8px;
}

.templates-card p {
    color: #64748b;
    margin-bottom: 24px;
    font-size: 0.9rem;
}

.template-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.template-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px;
    background: #f8fafc;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.3s ease;
    border: 1px solid #e2e8f0;
}

.template-item:hover {
    background: #f59e0b;
    color: white;
    transform: translateX(4px);
}

.template-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.2rem;
    flex-shrink: 0;
}

.template-icon.vaccination { background: #ef4444; }
.template-icon.checkup { background: #3b82f6; }
.template-icon.dental { background: #10b981; }
.template-icon.medication { background: #8b5cf6; }
.template-icon.grooming { background: #06b6d4; }

.template-item:hover .template-icon {
    background: white;
    color: #f59e0b;
}

.template-content h6 {
    color: #1e293b;
    font-weight: 600;
    margin-bottom: 2px;
}

.template-content p {
    color: #64748b;
    font-size: 0.8rem;
    margin: 0;
}

.template-item:hover .template-content h6,
.template-item:hover .template-content p {
    color: white;
}

.tips-card {
    background: #fffbeb;
    border: 1px solid #fed7aa;
    border-radius: 16px;
    padding: 20px;
}

.tips-card h6 {
    color: #d97706;
    margin-bottom: 12px;
    font-weight: 600;
}

.tips-card ul {
    margin: 0;
    padding-left: 20px;
    color: #a16207;
}

.tips-card li {
    margin-bottom: 8px;
    font-size: 0.9rem;
}

.form-card-modern {
    background: white;
    border-radius: 24px;
    padding: 40px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(0, 0, 0, 0.05);
}

.form-header {
    text-align: center;
    margin-bottom: 40px;
}

.form-icon {
    width: 80px;
    height: 80px;
    background: linear-gradient(135deg, #f59e0b, #d97706);
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    color: white;
    margin: 0 auto 20px;
}

.form-header h4 {
    font-size: 1.8rem;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 8px;
}

.form-header p {
    color: #64748b;
    font-size: 1rem;
}

.alert-modern {
    border: none;
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 32px;
    border-left: 4px solid #ef4444;
}

.form-group-modern {
    margin-bottom: 24px;
}

.form-label-modern {
    font-weight: 600;
    color: #374151;
    margin-bottom: 8px;
    display: block;
    font-size: 1rem;
}

.required {
    color: #ef4444;
}

.pet-selector {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 12px;
}

.pet-option input[type="radio"] {
    display: none;
}

.pet-option-label {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    padding: 16px 12px;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.3s ease;
    background: white;
}

.pet-option input[type="radio"]:checked + .pet-option-label {
    border-color: #f59e0b;
    background: #fffbeb;
}

.pet-avatar {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    overflow: hidden;
}

.pet-avatar img {
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

.pet-name {
    font-weight: 500;
    color: #374151;
    font-size: 0.9rem;
    text-align: center;
}

.input-group-modern {
    position: relative;
}

.input-icon {
    position: absolute;
    left: 16px;
    top: 50%;
    transform: translateY(-50%);
    color: #9ca3af;
    z-index: 2;
}

.form-control-modern {
    width: 100%;
    padding: 16px 16px 16px 48px;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    font-size: 1rem;
    transition: all 0.3s ease;
    background: rgba(255, 255, 255, 0.8);
}

.form-control-modern:focus {
    border-color: #f59e0b;
    box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.1);
    background: white;
    outline: none;
}

.textarea-group-modern .form-control-modern {
    padding: 16px;
    resize: vertical;
}

.form-text {
    color: #6b7280;
    font-size: 0.85rem;
    margin-top: 4px;
}

.quick-dates {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.recurring-section {
    background: #f8fafc;
    padding: 20px;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
}

.recurring-header {
    margin-bottom: 16px;
}

.recurring-options {
    padding-top: 16px;
    border-top: 1px solid #e2e8f0;
}

.interval-buttons {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.form-actions {
    display: flex;
    gap: 16px;
    justify-content: center;
    margin-top: 40px;
}

@media (max-width: 768px) {
    .page-title {
        font-size: 2.5rem;
    }
    
    .page-actions {
        text-align: left;
        margin-top: 24px;
    }
    
    .form-card-modern {
        padding: 24px;
    }
    
    .templates-card {
        position: static;
        margin-bottom: 24px;
    }
    
    .pet-selector {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .form-actions {
        flex-direction: column;
    }
}
</style>

<script>
// Template data
const templates = {
    vaccination: {
        type: 'Vaccination',
        title: 'Annual Vaccination Due',
        description: 'Schedule annual vaccination including rabies, distemper, and other core vaccines.',
        interval: 365
    },
    checkup: {
        type: 'Health Checkup',
        title: 'Routine Health Checkup',
        description: 'Schedule routine health examination and wellness check.',
        interval: 180
    },
    dental: {
        type: 'Dental Care',
        title: 'Dental Cleaning Due',
        description: 'Professional dental cleaning and oral health examination.',
        interval: 365
    },
    medication: {
        type: 'Medication',
        title: 'Medication Refill',
        description: 'Time to refill or review current medications.',
        interval: 30
    },
    grooming: {
        type: 'Grooming',
        title: 'Grooming Appointment',
        description: 'Regular grooming session for hygiene and health.',
        interval: 60
    }
};

function useTemplate(templateKey) {
    const template = templates[templateKey];
    if (!template) return;
    
    // Fill form fields
    document.getElementById('reminder_type').value = template.type;
    document.getElementById('title').value = template.title;
    document.getElementById('description').value = template.description;
    
    // Set default dates (due in template interval, remind 1 week before)
    const today = new Date();
    const dueDate = new Date(today.getTime() + (template.interval * 24 * 60 * 60 * 1000));
    const reminderDate = new Date(dueDate.getTime() - (7 * 24 * 60 * 60 * 1000));
    
    document.getElementById('due_date').value = dueDate.toISOString().split('T')[0];
    document.getElementById('reminder_date').value = reminderDate.toISOString().split('T')[0];
    
    // Set recurring if it's a recurring type
    if (template.interval >= 30) {
        document.getElementById('recurring').checked = true;
        document.getElementById('recurring_interval').value = template.interval;
        document.getElementById('recurringOptions').style.display = 'block';
    }
}

function setDueDateFromToday(days) {
    const today = new Date();
    const dueDate = new Date(today.getTime() + (days * 24 * 60 * 60 * 1000));
    const reminderDate = new Date(dueDate.getTime() - (7 * 24 * 60 * 60 * 1000));
    
    // Don't set reminder date in the past
    if (reminderDate < today) {
        reminderDate.setTime(today.getTime());
    }
    
    document.getElementById('due_date').value = dueDate.toISOString().split('T')[0];
    document.getElementById('reminder_date').value = reminderDate.toISOString().split('T')[0];
}

function setInterval(days) {
    document.getElementById('recurring_interval').value = days;
}

// Toggle recurring options
document.getElementById('recurring').addEventListener('change', function() {
    const options = document.getElementById('recurringOptions');
    options.style.display = this.checked ? 'block' : 'none';
});

// Auto-set reminder date when due date changes
document.getElementById('due_date').addEventListener('change', function() {
    const dueDate = new Date(this.value);
    const reminderDate = new Date(dueDate.getTime() - (7 * 24 * 60 * 60 * 1000));
    const today = new Date();
    
    // Don't set reminder date in the past
    if (reminderDate < today) {
        reminderDate.setTime(today.getTime());
    }
    
    document.getElementById('reminder_date').value = reminderDate.toISOString().split('T')[0];
});
</script>

<?php
// Include footer
include_once '../includes/footer.php';

// Close database connection
$conn->close();
?>