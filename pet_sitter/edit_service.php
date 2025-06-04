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

// Check if service ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "Invalid service ID.";
    header("Location: services.php");
    exit();
}

// Include database connection
require_once '../config/db_connect.php';

$user_id = $_SESSION['user_id'];
$service_id = $_GET['id'];

// Get service details
$service_sql = "SELECT * FROM pet_service WHERE serviceID = ? AND userID = ?";
$service_stmt = $conn->prepare($service_sql);
$service_stmt->bind_param("ii", $service_id, $user_id);
$service_stmt->execute();
$service_result = $service_stmt->get_result();

if ($service_result->num_rows === 0) {
    $_SESSION['error_message'] = "Service not found or does not belong to you.";
    header("Location: services.php");
    exit();
}

$service = $service_result->fetch_assoc();
$service_stmt->close();

// Initialize variables with current data
$name = $service['name'];
$type = $service['type'];
$price = $service['price'];
$description = $service['description'];
$errors = array();

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $name = trim($_POST['name']);
    $type = trim($_POST['type']);
    $price = $_POST['price'];
    $description = trim($_POST['description']);
    
    // Validate form data
    if (empty($name)) {
        $errors[] = "Service name is required";
    }
    
    if (empty($type)) {
        $errors[] = "Service type is required";
    }
    
    if (empty($price) || !is_numeric($price) || $price <= 0) {
        $errors[] = "Price must be a valid positive number";
    }
    
    if (empty($description)) {
        $errors[] = "Description is required";
    }
    
    // Process image upload if no errors
    $image_filename = $service['image']; // Keep existing image by default
    if (empty($errors) && isset($_FILES['service_image']) && $_FILES['service_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $target_dir = "../assets/images/services/";
        
        // Create directory if it doesn't exist
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $file_extension = strtolower(pathinfo($_FILES["service_image"]["name"], PATHINFO_EXTENSION));
        $new_image_filename = "service_" . time() . "_" . uniqid() . "." . $file_extension;
        $target_file = $target_dir . $new_image_filename;
        $upload_ok = true;
        
        // Check if image file is actual image
        $check = getimagesize($_FILES["service_image"]["tmp_name"]);
        if ($check === false) {
            $errors[] = "File is not an image.";
            $upload_ok = false;
        }
        
        // Check file size (limit to 5MB)
        if ($_FILES["service_image"]["size"] > 5000000) {
            $errors[] = "File is too large. Maximum file size is 5MB.";
            $upload_ok = false;
        }
        
        // Allow certain file formats
        if ($file_extension != "jpg" && $file_extension != "png" && $file_extension != "jpeg") {
            $errors[] = "Only JPG, JPEG, PNG files are allowed.";
            $upload_ok = false;
        }
        
        // If everything is ok, try to upload file
        if ($upload_ok) {
            if (move_uploaded_file($_FILES["service_image"]["tmp_name"], $target_file)) {
                // Delete old image if it exists
                if (!empty($service['image']) && file_exists($target_dir . $service['image'])) {
                    unlink($target_dir . $service['image']);
                }
                $image_filename = $new_image_filename;
            } else {
                $errors[] = "There was an error uploading your file.";
            }
        }
    }
    
    // If no errors, update service in database
    if (empty($errors)) {
        $sql = "UPDATE pet_service SET name = ?, type = ?, price = ?, image = ?, description = ? WHERE serviceID = ? AND userID = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssdssii", $name, $type, $price, $image_filename, $description, $service_id, $user_id);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Service updated successfully!";
            header("Location: services.php");
            exit();
        } else {
            $errors[] = "Error updating service: " . $conn->error;
        }
        
        $stmt->close();
    }
}

// Include header
include_once '../includes/header.php';
?>

<!-- Modern Edit Service Header -->
<section class="edit-service-header-section">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <div class="edit-service-header-content">
                    <span class="section-badge">✏️ Edit Service</span>
                    <h1 class="edit-service-title">Update <span class="text-gradient">Service</span></h1>
                    <p class="edit-service-subtitle">Modify your service details to attract more clients.</p>
                </div>
            </div>
            <div class="col-lg-4 text-lg-end">
                <a href="services.php" class="btn btn-outline-light btn-lg">
                    <i class="fas fa-arrow-left me-2"></i> Back to Services
                </a>
            </div>
        </div>
    </div>
</section>

<!-- Modern Edit Service Form -->
<section class="edit-service-form-section">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="service-form-card">
                    <div class="form-header">
                        <div class="form-icon">
                            <i class="fas fa-edit"></i>
                        </div>
                        <h3>Edit Service Information</h3>
                        <p>Update the details below to modify your service listing</p>
                    </div>
                    
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
                    
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . "?id=" . $service_id); ?>" method="post" enctype="multipart/form-data" class="service-form">
                        <div class="form-group-modern">
                            <label for="name" class="form-label-modern">Service Name <span class="required">*</span></label>
                            <div class="input-group-modern">
                                <div class="input-icon">
                                    <i class="fas fa-tag"></i>
                                </div>
                                <input type="text" class="form-control-modern" id="name" name="name" value="<?php echo htmlspecialchars($name); ?>" placeholder="e.g., Professional Dog Walking" required>
                            </div>
                            <div class="form-hint">Choose a descriptive and catchy name for your service</div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group-modern">
                                    <label for="type" class="form-label-modern">Service Type <span class="required">*</span></label>
                                    <div class="input-group-modern">
                                        <div class="input-icon">
                                            <i class="fas fa-list"></i>
                                        </div>
                                        <select class="form-control-modern" id="type" name="type" required>
                                            <option value="">Select Service Type</option>
                                            <option value="Pet Sitting" <?php echo ($type === 'Pet Sitting') ? 'selected' : ''; ?>>Pet Sitting</option>
                                            <option value="Dog Walking" <?php echo ($type === 'Dog Walking') ? 'selected' : ''; ?>>Dog Walking</option>
                                            <option value="Pet Boarding" <?php echo ($type === 'Pet Boarding') ? 'selected' : ''; ?>>Pet Boarding</option>
                                            <option value="Pet Grooming" <?php echo ($type === 'Pet Grooming') ? 'selected' : ''; ?>>Pet Grooming</option>
                                            <option value="Pet Training" <?php echo ($type === 'Pet Training') ? 'selected' : ''; ?>>Pet Training</option>
                                            <option value="Medication Administration" <?php echo ($type === 'Medication Administration') ? 'selected' : ''; ?>>Medication Administration</option>
                                            <option value="Other" <?php echo ($type === 'Other') ? 'selected' : ''; ?>>Other</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group-modern">
                                    <label for="price" class="form-label-modern">Price Per Hour ($) <span class="required">*</span></label>
                                    <div class="input-group-modern">
                                        <div class="input-icon">
                                            <i class="fas fa-dollar-sign"></i>
                                        </div>
                                        <input type="number" class="form-control-modern" id="price" name="price" min="0.01" step="0.01" value="<?php echo htmlspecialchars($price); ?>" placeholder="25.00" required>
                                    </div>
                                    <div class="form-hint">Set a competitive hourly rate</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group-modern">
                            <label for="description" class="form-label-modern">Service Description <span class="required">*</span></label>
                            <div class="textarea-group-modern">
                                <div class="textarea-icon">
                                    <i class="fas fa-align-left"></i>
                                </div>
                                <textarea class="form-control-modern textarea-modern" id="description" name="description" rows="6" placeholder="Describe your service in detail. Include your experience, what's included, and any special skills..." required><?php echo htmlspecialchars($description); ?></textarea>
                            </div>
                            <div class="form-hint">Provide a detailed description to attract pet owners (minimum 100 characters)</div>
                            <div class="char-counter">
                                <span id="char-count">0</span> characters
                            </div>
                        </div>
                        
                        <div class="form-group-modern">
                            <label for="service_image" class="form-label-modern">Service Image</label>
                            
                            <!-- Current Image Display -->
                            <?php if (!empty($service['image'])): ?>
                                <div class="current-image-display">
                                    <h6>Current Image:</h6>
                                    <img src="../assets/images/services/<?php echo htmlspecialchars($service['image']); ?>" alt="Current Service Image" class="current-service-image">
                                </div>
                            <?php endif; ?>
                            
                            <div class="file-upload-area" id="file-upload-area">
                                <input type="file" class="file-input" id="service_image" name="service_image" accept="image/jpeg, image/png, image/jpg">
                                <div class="file-upload-content">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <h6><?php echo !empty($service['image']) ? 'Upload New Image' : 'Upload Service Image'; ?></h6>
                                    <p>Drag & drop an image here, or click to select</p>
                                    <div class="file-requirements">
                                        JPG, JPEG, or PNG • Max 5MB
                                    </div>
                                </div>
                                <div class="file-preview" id="file-preview" style="display: none;">
                                    <img id="preview-image" alt="Preview">
                                    <div class="file-info">
                                        <span id="file-name"></span>
                                        <button type="button" class="remove-file" id="remove-file">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="form-hint"><?php echo !empty($service['image']) ? 'Leave empty to keep current image' : 'Add an attractive image to showcase your service'; ?></div>
                        </div>
                        
                        <div class="form-actions">
                            <a href="services.php" class="btn btn-outline-secondary btn-lg">
                                <i class="fas fa-times me-2"></i> Cancel
                            </a>
                            <button type="submit" class="btn btn-primary-gradient btn-lg">
                                <i class="fas fa-save me-2"></i> Update Service
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
.edit-service-header-section {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 80px 0;
    position: relative;
    overflow: hidden;
}

.edit-service-header-section::before {
    content: '';
    position: absolute;
    width: 100%;
    height: 100%;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="20" cy="20" r="2" fill="rgba(255,255,255,0.1)"/><circle cx="80" cy="40" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="40" cy="80" r="1.5" fill="rgba(255,255,255,0.1)"/></svg>');
    animation: float 20s infinite linear;
}

.edit-service-title {
    font-size: 3.5rem;
    font-weight: 800;
    line-height: 1.2;
    margin-bottom: 24px;
}

.edit-service-subtitle {
    font-size: 1.2rem;
    opacity: 0.9;
}

.edit-service-form-section {
    padding: 80px 0;
    background: #f8f9ff;
}

.service-form-card {
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

.current-image-display {
    margin-bottom: 20px;
    padding: 20px;
    background: #f8f9ff;
    border-radius: 12px;
    border: 2px solid #e5e7eb;
}

.current-image-display h6 {
    color: #374151;
    margin-bottom: 12px;
    font-weight: 600;
}

.current-service-image {
    max-width: 200px;
    max-height: 150px;
    border-radius: 8px;
    border: 2px solid #e5e7eb;
    object-fit: cover;
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

.error-item {
    display: flex;
    align-items: center;
}

.error-item::before {
    content: '•';
    margin-right: 8px;
    color: #ef4444;
}

.form-group-modern {
    margin-bottom: 32px;
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
    outline: none;
    border-color: #667eea;
    background: white;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.textarea-group-modern {
    position: relative;
}

.textarea-icon {
    position: absolute;
    left: 16px;
    top: 16px;
    color: #9ca3af;
    z-index: 2;
}

.textarea-modern {
    padding: 16px 16px 16px 48px;
    resize: vertical;
    min-height: 120px;
}

.form-hint {
    font-size: 0.85rem;
    color: #6b7280;
    margin-top: 6px;
}

.char-counter {
    text-align: right;
    font-size: 0.8rem;
    color: #9ca3af;
    margin-top: 4px;
}

.file-upload-area {
    border: 2px dashed #d1d5db;
    border-radius: 12px;
    padding: 32px;
    text-align: center;
    background: #f9fafb;
    transition: all 0.3s ease;
    cursor: pointer;
    position: relative;
}

.file-upload-area:hover {
    border-color: #667eea;
    background: #f8f9ff;
}

.file-upload-area.dragover {
    border-color: #667eea;
    background: #f1f5f9;
    transform: scale(1.02);
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

.file-upload-content i {
    font-size: 3rem;
    color: #9ca3af;
    margin-bottom: 16px;
}

.file-upload-content h6 {
    font-weight: 600;
    color: #374151;
    margin-bottom: 8px;
}

.file-upload-content p {
    color: #6b7280;
    margin-bottom: 12px;
}

.file-requirements {
    font-size: 0.8rem;
    color: #9ca3af;
}

.file-preview {
    display: flex;
    align-items: center;
    gap: 16px;
    background: white;
    padding: 16px;
    border-radius: 12px;
    border: 2px solid #10b981;
}

.file-preview img {
    width: 60px;
    height: 60px;
    object-fit: cover;
    border-radius: 8px;
}

.file-info {
    flex: 1;
    text-align: left;
}

.file-info span {
    font-weight: 500;
    color: #374151;
}

.remove-file {
    background: #ef4444;
    color: white;
    border: none;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
}

.remove-file:hover {
    background: #dc2626;
    transform: scale(1.1);
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

@media (max-width: 991px) {
    .edit-service-title {
        font-size: 2.5rem;
    }
    
    .service-form-card {
        padding: 32px 24px;
    }
}

@media (max-width: 768px) {
    .edit-service-header-section {
        text-align: center;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .file-upload-area {
        padding: 24px 16px;
    }
    
    .file-preview {
        flex-direction: column;
        text-align: center;
    }
    
    .current-image-display {
        text-align: center;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Character counter for description
    const descriptionTextarea = document.getElementById('description');
    const charCount = document.getElementById('char-count');
    
    function updateCharCount() {
        const count = descriptionTextarea.value.length;
        charCount.textContent = count;
        
        if (count < 100) {
            charCount.style.color = '#ef4444';
        } else if (count < 200) {
            charCount.style.color = '#f59e0b';
        } else {
            charCount.style.color = '#10b981';
        }
    }
    
    descriptionTextarea.addEventListener('input', updateCharCount);
    updateCharCount(); // Initial count
    
    // File upload handling
    const fileInput = document.getElementById('service_image');
    const fileUploadArea = document.getElementById('file-upload-area');
    const fileUploadContent = fileUploadArea.querySelector('.file-upload-content');
    const filePreview = document.getElementById('file-preview');
    const previewImage = document.getElementById('preview-image');
    const fileName = document.getElementById('file-name');
    const removeFileBtn = document.getElementById('remove-file');
    
    function showFilePreview(file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            previewImage.src = e.target.result;
            fileName.textContent = file.name;
            fileUploadContent.style.display = 'none';
            filePreview.style.display = 'flex';
            fileUploadArea.style.border = '2px solid #10b981';
            fileUploadArea.style.background = '#f0fdf4';
        };
        reader.readAsDataURL(file);
    }
    
    function hideFilePreview() {
        fileUploadContent.style.display = 'block';
        filePreview.style.display = 'none';
        fileUploadArea.style.border = '2px dashed #d1d5db';
        fileUploadArea.style.background = '#f9fafb';
        fileInput.value = '';
    }
    
    fileInput.addEventListener('change', function() {
        if (this.files && this.files[0]) {
            showFilePreview(this.files[0]);
        }
    });
    
    removeFileBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        hideFilePreview();
    });
    
    // Drag and drop functionality
    fileUploadArea.addEventListener('dragover', function(e) {
        e.preventDefault();
        this.classList.add('dragover');
    });
    
    fileUploadArea.addEventListener('dragleave', function() {
        this.classList.remove('dragover');
    });
    
    fileUploadArea.addEventListener('drop', function(e) {
        e.preventDefault();
        this.classList.remove('dragover');
        
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            fileInput.files = files;
            showFilePreview(files[0]);
        }
    });
    
    // Form validation
    const form = document.querySelector('.service-form');
    form.addEventListener('submit', function(e) {
        const description = descriptionTextarea.value;
        if (description.length < 50) {
            e.preventDefault();
            alert('Please provide a more detailed description (at least 50 characters).');
            descriptionTextarea.focus();
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