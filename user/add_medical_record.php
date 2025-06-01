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
$pet_sql = "SELECT petName FROM pet_profile WHERE petID = ? AND userID = ?";
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

// Initialize variables
$record_type = $record_date = $description = $veterinarian = $clinic_name = '';
$medication = $dosage = $follow_up_date = $cost = $notes = '';
$errors = array();

// File upload function
function uploadDocument($file, $target_dir) {
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        if ($file['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        return [false, "Error uploading file"];
    }
    
    $file_extension = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
    $allowed_extensions = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
    
    if (!in_array($file_extension, $allowed_extensions)) {
        return [false, "Only PDF, JPG, PNG, DOC, DOCX files are allowed."];
    }
    
    if ($file["size"] > 10000000) { // 10MB limit
        return [false, "File is too large. Maximum file size is 10MB."];
    }
    
    $new_filename = "medical_" . time() . "_" . uniqid() . "." . $file_extension;
    $target_file = $target_dir . $new_filename;
    
    if (!move_uploaded_file($file["tmp_name"], $target_file)) {
        return [false, "There was an error uploading the file."];
    }
    
    return [true, $new_filename];
}

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $record_type = trim($_POST['record_type']);
    $record_date = $_POST['record_date'];
    $description = trim($_POST['description']);
    $veterinarian = trim($_POST['veterinarian']);
    $clinic_name = trim($_POST['clinic_name']);
    $medication = trim($_POST['medication']);
    $dosage = trim($_POST['dosage']);
    $follow_up_date = !empty($_POST['follow_up_date']) ? $_POST['follow_up_date'] : null;
    $cost = !empty($_POST['cost']) ? $_POST['cost'] : null;
    $notes = trim($_POST['notes']);
    
    // Validate required fields
    if (empty($record_type)) {
        $errors[] = "Record type is required";
    }
    
    if (empty($record_date)) {
        $errors[] = "Record date is required";
    }
    
    if (empty($description)) {
        $errors[] = "Description is required";
    }
    
    // Validate date format
    if (!empty($record_date)) {
        $date = DateTime::createFromFormat('Y-m-d', $record_date);
        if (!$date || $date->format('Y-m-d') !== $record_date) {
            $errors[] = "Invalid record date format";
        }
    }
    
    // Validate cost if provided
    if (!empty($cost) && (!is_numeric($cost) || $cost < 0)) {
        $errors[] = "Cost must be a valid positive number";
    }
    
    // Process file upload if provided
    $attachment_filename = null;
    if (empty($errors) && isset($_FILES['attachment']) && $_FILES['attachment']['error'] !== UPLOAD_ERR_NO_FILE) {
        $target_dir = "../assets/uploads/medical_records/";
        $upload_result = uploadDocument($_FILES["attachment"], $target_dir);
        
        if ($upload_result[0] === false) {
            $errors[] = $upload_result[1];
        } else {
            $attachment_filename = $upload_result[1];
        }
    }
    
    // If no errors, insert record
    if (empty($errors)) {
        $sql = "INSERT INTO pet_medical_records (petID, record_type, record_date, description, veterinarian, clinic_name, medication, dosage, follow_up_date, cost, notes, attachment) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("issssssssdss", $pet_id, $record_type, $record_date, $description, $veterinarian, $clinic_name, $medication, $dosage, $follow_up_date, $cost, $notes, $attachment_filename);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Medical record added successfully!";
            header("Location: pet_details.php?id=" . $pet_id);
            exit();
        } else {
            $errors[] = "Error adding medical record: " . $conn->error;
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
                    <span class="page-badge">🏥 Medical Record</span>
                    <h1 class="page-title">Add Medical Record</h1>
                    <p class="page-subtitle">Record health information for <?php echo htmlspecialchars($pet['petName']); ?></p>
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

<!-- Add Medical Record Form -->
<section class="form-section-modern">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="form-card-modern">
                    <div class="form-header">
                        <div class="form-icon">
                            <i class="fas fa-notes-medical"></i>
                        </div>
                        <h4>Medical Record Details</h4>
                        <p>Add comprehensive health information for <?php echo htmlspecialchars($pet['petName']); ?></p>
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
                    
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . "?pet_id=" . $pet_id); ?>" method="post" enctype="multipart/form-data" class="modern-form">
                        <!-- Record Type and Date -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group-modern">
                                    <label for="record_type" class="form-label-modern">Record Type <span class="required">*</span></label>
                                    <div class="input-group-modern">
                                        <div class="input-icon">
                                            <i class="fas fa-clipboard"></i>
                                        </div>
                                        <select class="form-control-modern" id="record_type" name="record_type" required>
                                            <option value="">-- Select Type --</option>
                                            <option value="Vaccination" <?php echo ($record_type === 'Vaccination') ? 'selected' : ''; ?>>💉 Vaccination</option>
                                            <option value="Health Checkup" <?php echo ($record_type === 'Health Checkup') ? 'selected' : ''; ?>>🔍 Health Checkup</option>
                                            <option value="Emergency Visit" <?php echo ($record_type === 'Emergency Visit') ? 'selected' : ''; ?>>🚨 Emergency Visit</option>
                                            <option value="Surgery" <?php echo ($record_type === 'Surgery') ? 'selected' : ''; ?>>🏥 Surgery</option>
                                            <option value="Dental Care" <?php echo ($record_type === 'Dental Care') ? 'selected' : ''; ?>>🦷 Dental Care</option>
                                            <option value="Lab Results" <?php echo ($record_type === 'Lab Results') ? 'selected' : ''; ?>>🧪 Lab Results</option>
                                            <option value="Medication" <?php echo ($record_type === 'Medication') ? 'selected' : ''; ?>>💊 Medication</option>
                                            <option value="Grooming" <?php echo ($record_type === 'Grooming') ? 'selected' : ''; ?>>✂️ Grooming</option>
                                            <option value="Injury" <?php echo ($record_type === 'Injury') ? 'selected' : ''; ?>>🩹 Injury</option>
                                            <option value="Other" <?php echo ($record_type === 'Other') ? 'selected' : ''; ?>>📋 Other</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group-modern">
                                    <label for="record_date" class="form-label-modern">Date <span class="required">*</span></label>
                                    <div class="input-group-modern">
                                        <div class="input-icon">
                                            <i class="fas fa-calendar"></i>
                                        </div>
                                        <input type="date" class="form-control-modern" id="record_date" name="record_date" value="<?php echo htmlspecialchars($record_date); ?>" max="<?php echo date('Y-m-d'); ?>" required>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Description -->
                        <div class="form-group-modern">
                            <label for="description" class="form-label-modern">Description <span class="required">*</span></label>
                            <div class="textarea-group-modern">
                                <textarea class="form-control-modern" id="description" name="description" rows="4" placeholder="Describe the medical procedure, findings, or treatment..." required><?php echo htmlspecialchars($description); ?></textarea>
                            </div>
                        </div>
                        
                        <!-- Veterinarian and Clinic -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group-modern">
                                    <label for="veterinarian" class="form-label-modern">Veterinarian</label>
                                    <div class="input-group-modern">
                                        <div class="input-icon">
                                            <i class="fas fa-user-md"></i>
                                        </div>
                                        <input type="text" class="form-control-modern" id="veterinarian" name="veterinarian" value="<?php echo htmlspecialchars($veterinarian); ?>" placeholder="Dr. Smith">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group-modern">
                                    <label for="clinic_name" class="form-label-modern">Clinic/Hospital</label>
                                    <div class="input-group-modern">
                                        <div class="input-icon">
                                            <i class="fas fa-hospital"></i>
                                        </div>
                                        <input type="text" class="form-control-modern" id="clinic_name" name="clinic_name" value="<?php echo htmlspecialchars($clinic_name); ?>" placeholder="Pet Care Clinic">
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Medication and Dosage -->
                        <div class="row">
                            <div class="col-md-8">
                                <div class="form-group-modern">
                                    <label for="medication" class="form-label-modern">Medication Prescribed</label>
                                    <div class="input-group-modern">
                                        <div class="input-icon">
                                            <i class="fas fa-pills"></i>
                                        </div>
                                        <input type="text" class="form-control-modern" id="medication" name="medication" value="<?php echo htmlspecialchars($medication); ?>" placeholder="e.g., Amoxicillin, Pain reliever">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="form-group-modern">
                                    <label for="dosage" class="form-label-modern">Dosage</label>
                                    <div class="input-group-modern">
                                        <div class="input-icon">
                                            <i class="fas fa-prescription-bottle"></i>
                                        </div>
                                        <input type="text" class="form-control-modern" id="dosage" name="dosage" value="<?php echo htmlspecialchars($dosage); ?>" placeholder="e.g., 250mg 2x daily">
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Follow-up Date and Cost -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group-modern">
                                    <label for="follow_up_date" class="form-label-modern">Follow-up Date</label>
                                    <div class="input-group-modern">
                                        <div class="input-icon">
                                            <i class="fas fa-calendar-plus"></i>
                                        </div>
                                        <input type="date" class="form-control-modern" id="follow_up_date" name="follow_up_date" value="<?php echo htmlspecialchars($follow_up_date); ?>" min="<?php echo date('Y-m-d'); ?>">
                                    </div>
                                    <div class="form-text">Optional: When to schedule next visit</div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group-modern">
                                    <label for="cost" class="form-label-modern">Cost</label>
                                    <div class="input-group-modern">
                                        <div class="input-icon">
                                            <i class="fas fa-dollar-sign"></i>
                                        </div>
                                        <input type="number" class="form-control-modern" id="cost" name="cost" step="0.01" min="0" value="<?php echo htmlspecialchars($cost); ?>" placeholder="0.00">
                                    </div>
                                    <div class="form-text">Optional: Treatment cost in USD</div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Notes -->
                        <div class="form-group-modern">
                            <label for="notes" class="form-label-modern">Additional Notes</label>
                            <div class="textarea-group-modern">
                                <textarea class="form-control-modern" id="notes" name="notes" rows="3" placeholder="Any additional information, special instructions, or observations..."><?php echo htmlspecialchars($notes); ?></textarea>
                            </div>
                        </div>
                        
                        <!-- File Attachment -->
                        <div class="form-group-modern">
                            <label class="form-label-modern">Attach Document</label>
                            <div class="file-upload-area">
                                <div class="file-upload-box" id="fileUploadBox">
                                    <div class="upload-icon">
                                        <i class="fas fa-cloud-upload-alt"></i>
                                    </div>
                                    <div class="upload-text">
                                        <strong>Click to upload</strong> or drag and drop
                                    </div>
                                    <div class="upload-hint">
                                        PDF, DOC, DOCX, JPG, PNG (Max 10MB)
                                    </div>
                                </div>
                                <input type="file" class="file-input" id="attachment" name="attachment" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <div class="file-preview" id="filePreview" style="display: none;">
                                    <div class="file-info">
                                        <i class="fas fa-file"></i>
                                        <span class="file-name"></span>
                                        <button type="button" class="remove-file" onclick="removeFile()">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="form-text">Upload medical reports, prescriptions, or related documents</div>
                        </div>
                        
                        <!-- Submit Button -->
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary-gradient btn-lg">
                                <i class="fas fa-save me-2"></i> Save Medical Record
                            </button>
                            <a href="pet_details.php?id=<?php echo $pet_id; ?>" class="btn btn-outline-secondary btn-lg">
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

.form-section-modern {
    padding: 80px 0;
    background: white;
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
    background: linear-gradient(135deg, #10b981, #059669);
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
    border-color: #10b981;
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
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

.file-upload-area {
    position: relative;
}

.file-upload-box {
    border: 2px dashed #d1d5db;
    border-radius: 12px;
    padding: 40px 20px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
    background: #f9fafb;
}

.file-upload-box:hover {
    border-color: #10b981;
    background: #f0fdf4;
}

.upload-icon {
    font-size: 2.5rem;
    color: #9ca3af;
    margin-bottom: 16px;
}

.upload-text {
    font-size: 1rem;
    color: #374151;
    margin-bottom: 8px;
}

.upload-hint {
    font-size: 0.875rem;
    color: #6b7280;
}

.file-input {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    opacity: 0;
    cursor: pointer;
}

.file-preview {
    background: #f0fdf4;
    border: 2px solid #10b981;
    border-radius: 12px;
    padding: 16px;
}

.file-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.file-info i {
    color: #10b981;
    font-size: 1.2rem;
}

.file-name {
    flex: 1;
    color: #374151;
    font-weight: 500;
}

.remove-file {
    background: #ef4444;
    color: white;
    border: none;
    border-radius: 50%;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 0.8rem;
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
    
    .form-actions {
        flex-direction: column;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const fileInput = document.getElementById('attachment');
    const fileUploadBox = document.getElementById('fileUploadBox');
    const filePreview = document.getElementById('filePreview');
    
    // File upload handling
    fileInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            showFilePreview(file);
        }
    });
    
    // Drag and drop handling
    fileUploadBox.addEventListener('dragover', function(e) {
        e.preventDefault();
        this.style.borderColor = '#10b981';
        this.style.background = '#f0fdf4';
    });
    
    fileUploadBox.addEventListener('dragleave', function(e) {
        e.preventDefault();
        this.style.borderColor = '#d1d5db';
        this.style.background = '#f9fafb';
    });
    
    fileUploadBox.addEventListener('drop', function(e) {
        e.preventDefault();
        this.style.borderColor = '#d1d5db';
        this.style.background = '#f9fafb';
        
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            fileInput.files = files;
            showFilePreview(files[0]);
        }
    });
    
    function showFilePreview(file) {
        fileUploadBox.style.display = 'none';
        filePreview.style.display = 'block';
        filePreview.querySelector('.file-name').textContent = file.name;
    }
});

function removeFile() {
    const fileInput = document.getElementById('attachment');
    const fileUploadBox = document.getElementById('fileUploadBox');
    const filePreview = document.getElementById('filePreview');
    
    fileInput.value = '';
    fileUploadBox.style.display = 'block';
    filePreview.style.display = 'none';
}
</script>

<?php
// Include footer
include_once '../includes/footer.php';

// Close database connection
$conn->close();
?>