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

// Initialize variables
$petName = $age = $type = $sex = $breed = $color = '';
$errors = array();

// Improved image upload function
function uploadImage($file, $target_dir) {
    // Create directory if it doesn't exist
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    // Check if file was uploaded successfully
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        if ($file['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        $upload_errors = array(
            UPLOAD_ERR_INI_SIZE => "The uploaded file exceeds the upload_max_filesize directive in php.ini",
            UPLOAD_ERR_FORM_SIZE => "The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form",
            UPLOAD_ERR_PARTIAL => "The uploaded file was only partially uploaded",
            UPLOAD_ERR_NO_TMP_DIR => "Missing a temporary folder",
            UPLOAD_ERR_CANT_WRITE => "Failed to write file to disk",
            UPLOAD_ERR_EXTENSION => "A PHP extension stopped the file upload"
        );
        $error_message = isset($upload_errors[$file['error']]) ? $upload_errors[$file['error']] : "Unknown upload error";
        return [false, "Error uploading file: " . $error_message];
    }
    
    // Create unique filename
    $file_extension = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
    $new_filename = "pet_" . time() . "_" . uniqid() . "." . $file_extension;
    $target_file = $target_dir . $new_filename;
    
    // Check if image file is a actual image
    $check = getimagesize($file["tmp_name"]);
    if ($check === false) {
        return [false, "File is not an image."];
    }
    
    // Check file size (limit to 5MB)
    if ($file["size"] > 5000000) {
        return [false, "File is too large. Maximum file size is 5MB."];
    }
    
    // Allow certain file formats
    if ($file_extension != "jpg" && $file_extension != "png" && $file_extension != "jpeg") {
        return [false, "Only JPG, JPEG, PNG files are allowed."];
    }
    
    // Try to move the uploaded file
    if (!move_uploaded_file($file["tmp_name"], $target_file)) {
        return [false, "There was an error moving the uploaded file. Directory may not be writable."];
    }
    
    return [true, $new_filename];
}

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $petName = trim($_POST['petName']);
    $age = $_POST['age'];
    $type = trim($_POST['type']);
    $sex = $_POST['sex'];
    $breed = trim($_POST['breed']);
    $color = trim($_POST['color']);
    
    // Validate form data
    if (empty($petName)) {
        $errors[] = "Pet name is required";
    }
    
    if (!is_numeric($age) || $age < 0) {
        $errors[] = "Age must be a valid number";
    }
    
    if (empty($type)) {
        $errors[] = "Pet type is required";
    }
    
    if (empty($breed)) {
        $errors[] = "Breed is required";
    }
    
    if (empty($color)) {
        $errors[] = "Color is required";
    }
    
    // Process image upload if no errors
    $image_filename = null;
    if (empty($errors) && isset($_FILES['pet_image']) && $_FILES['pet_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $target_dir = "../assets/images/pets/";
        
        // Upload the image
        $upload_result = uploadImage($_FILES["pet_image"], $target_dir);
        
        if ($upload_result[0] === false) {
            $errors[] = $upload_result[1];
        } else {
            $image_filename = $upload_result[1];
        }
    }
    
    // If no errors, insert pet into database
    if (empty($errors)) {
        $sql = "INSERT INTO pet_profile (petName, age, type, sex, breed, color, image, userID) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sisssssi", $petName, $age, $type, $sex, $breed, $color, $image_filename, $user_id);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Pet added successfully!";
            header("Location: pets.php");
            exit();
        } else {
            $errors[] = "Error adding pet: " . $conn->error;
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
                    <span class="page-badge">🐾 Add Pet</span>
                    <h1 class="page-title">Add Your <span class="text-gradient">Beloved Pet</span></h1>
                    <p class="page-subtitle">Create a profile for your pet to access our care services</p>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="page-actions">
                    <a href="pets.php" class="btn btn-outline-light">
                        <i class="fas fa-arrow-left me-2"></i> Back to My Pets
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Add Pet Form -->
<section class="form-section-modern">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="form-card-modern">
                    <div class="form-header">
                        <div class="form-icon">
                            <i class="fas fa-paw"></i>
                        </div>
                        <h4>Pet Information</h4>
                        <p>Fill in your pet's details to create their profile</p>
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
                    
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data" class="modern-form">
                        <!-- Pet Photo Upload -->
                        <div class="form-group-modern photo-upload-group">
                            <label class="form-label-modern">Pet Photo</label>
                            <div class="photo-upload-container">
                                <div class="photo-preview" id="photoPreview">
                                    <div class="upload-placeholder">
                                        <i class="fas fa-camera"></i>
                                        <span>Upload Photo</span>
                                        <small>JPG, PNG up to 5MB</small>
                                    </div>
                                </div>
                                <input type="file" class="photo-input" id="pet_image" name="pet_image" accept="image/jpeg, image/png, image/jpg">
                                <label for="pet_image" class="photo-upload-btn">
                                    <i class="fas fa-upload me-2"></i> Choose Photo
                                </label>
                            </div>
                        </div>
                        
                        <!-- Pet Name -->
                        <div class="form-group-modern">
                            <label for="petName" class="form-label-modern">Pet Name <span class="required">*</span></label>
                            <div class="input-group-modern">
                                <div class="input-icon">
                                    <i class="fas fa-heart"></i>
                                </div>
                                <input type="text" class="form-control-modern" id="petName" name="petName" value="<?php echo htmlspecialchars($petName); ?>" placeholder="Enter your pet's name" required>
                            </div>
                        </div>
                        
                        <!-- Pet Type and Sex -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group-modern">
                                    <label for="type" class="form-label-modern">Pet Type <span class="required">*</span></label>
                                    <div class="input-group-modern">
                                        <div class="input-icon">
                                            <i class="fas fa-paw"></i>
                                        </div>
                                        <select class="form-control-modern" id="type" name="type" required>
                                            <option value="">-- Select Type --</option>
                                            <option value="Dog" <?php echo ($type === 'Dog') ? 'selected' : ''; ?>>🐕 Dog</option>
                                            <option value="Cat" <?php echo ($type === 'Cat') ? 'selected' : ''; ?>>🐱 Cat</option>
                                            <option value="Bird" <?php echo ($type === 'Bird') ? 'selected' : ''; ?>>🐦 Bird</option>
                                            <option value="Fish" <?php echo ($type === 'Fish') ? 'selected' : ''; ?>>🐠 Fish</option>
                                            <option value="Rabbit" <?php echo ($type === 'Rabbit') ? 'selected' : ''; ?>>🐰 Rabbit</option>
                                            <option value="Hamster" <?php echo ($type === 'Hamster') ? 'selected' : ''; ?>>🐹 Hamster</option>
                                            <option value="Guinea Pig" <?php echo ($type === 'Guinea Pig') ? 'selected' : ''; ?>>🐹 Guinea Pig</option>
                                            <option value="Reptile" <?php echo ($type === 'Reptile') ? 'selected' : ''; ?>>🦎 Reptile</option>
                                            <option value="Other" <?php echo ($type === 'Other') ? 'selected' : ''; ?>>🐾 Other</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group-modern">
                                    <label for="sex" class="form-label-modern">Gender <span class="required">*</span></label>
                                    <div class="gender-selector">
                                        <input type="radio" id="male" name="sex" value="Male" <?php echo ($sex === 'Male') ? 'checked' : ''; ?>>
                                        <label for="male" class="gender-option">
                                            <i class="fas fa-mars"></i>
                                            <span>Male</span>
                                        </label>
                                        
                                        <input type="radio" id="female" name="sex" value="Female" <?php echo ($sex === 'Female') ? 'checked' : ''; ?>>
                                        <label for="female" class="gender-option">
                                            <i class="fas fa-venus"></i>
                                            <span>Female</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Age and Color -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group-modern">
                                    <label for="age" class="form-label-modern">Age (years) <span class="required">*</span></label>
                                    <div class="input-group-modern">
                                        <div class="input-icon">
                                            <i class="fas fa-birthday-cake"></i>
                                        </div>
                                        <input type="number" class="form-control-modern" id="age" name="age" min="0" step="0.1" value="<?php echo htmlspecialchars($age); ?>" placeholder="0.5" required>
                                    </div>
                                    <div class="form-text">Enter age in years (e.g., 2.5 for 2 years 6 months)</div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group-modern">
                                    <label for="color" class="form-label-modern">Color <span class="required">*</span></label>
                                    <div class="input-group-modern">
                                        <div class="input-icon">
                                            <i class="fas fa-palette"></i>
                                        </div>
                                        <input type="text" class="form-control-modern" id="color" name="color" value="<?php echo htmlspecialchars($color); ?>" placeholder="e.g., Brown, Black, White" required>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Breed -->
                        <div class="form-group-modern">
                            <label for="breed" class="form-label-modern">Breed <span class="required">*</span></label>
                            <div class="input-group-modern">
                                <div class="input-icon">
                                    <i class="fas fa-dna"></i>
                                </div>
                                <input type="text" class="form-control-modern" id="breed" name="breed" value="<?php echo htmlspecialchars($breed); ?>" placeholder="e.g., Golden Retriever, Persian Cat, Budgerigar" required>
                            </div>
                            <div class="form-text">Specify the breed or closest known breed</div>
                        </div>
                        
                        <!-- Submit Button -->
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary-gradient btn-lg">
                                <i class="fas fa-plus me-2"></i> Add Pet to Family
                            </button>
                            <a href="pets.php" class="btn btn-outline-secondary btn-lg">
                                <i class="fas fa-times me-2"></i> Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Pet Care Tips -->
<section class="tips-section-modern">
    <div class="container">
        <div class="section-header text-center">
            <h3>Getting Started with Pet Care</h3>
            <p>Essential tips for new pet owners</p>
        </div>
        
        <div class="tips-grid">
            <div class="tip-card-inline">
                <div class="tip-icon">📋</div>
                <div class="tip-content">
                    <h6>Complete Profile</h6>
                    <p>Provide accurate information to help sitters care for your pet properly.</p>
                </div>
            </div>
            <div class="tip-card-inline">
                <div class="tip-icon">📸</div>
                <div class="tip-content">
                    <h6>Add Photos</h6>
                    <p>Recent photos help sitters identify and bond with your pet.</p>
                </div>
            </div>
            <div class="tip-card-inline">
                <div class="tip-icon">💝</div>
                <div class="tip-content">
                    <h6>Regular Updates</h6>
                    <p>Keep your pet's information current for the best care experience.</p>
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
    background: linear-gradient(135deg, #667eea, #764ba2);
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

.photo-upload-group {
    margin-bottom: 32px;
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

.photo-upload-container {
    text-align: center;
}

.photo-preview {
    width: 200px;
    height: 200px;
    margin: 0 auto 20px;
    border-radius: 20px;
    overflow: hidden;
    border: 3px dashed #d1d5db;
    transition: all 0.3s ease;
    position: relative;
}

.photo-preview:hover {
    border-color: #667eea;
    background: #f8f9ff;
}

.upload-placeholder {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    color: #9ca3af;
}

.upload-placeholder i {
    font-size: 2rem;
    margin-bottom: 8px;
}

.upload-placeholder span {
    font-weight: 600;
    margin-bottom: 4px;
}

.upload-placeholder small {
    font-size: 0.8rem;
}

.photo-input {
    display: none;
}

.photo-upload-btn {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    padding: 12px 24px;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-block;
    font-weight: 600;
}

.photo-upload-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
    color: white;
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
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    background: white;
    outline: none;
}

.gender-selector {
    display: flex;
    gap: 12px;
    margin-top: 8px;
}

.gender-selector input[type="radio"] {
    display: none;
}

.gender-option {
    flex: 1;
    padding: 16px 12px;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
    background: white;
}

.gender-option i {
    display: block;
    font-size: 1.5rem;
    margin-bottom: 8px;
    color: #9ca3af;
}

.gender-option span {
    font-weight: 500;
    color: #6b7280;
}

.gender-selector input[type="radio"]:checked + .gender-option {
    border-color: #667eea;
    background: linear-gradient(135deg, #667eea, #764ba2);
}

.gender-selector input[type="radio"]:checked + .gender-option i,
.gender-selector input[type="radio"]:checked + .gender-option span {
    color: white;
}

.form-text {
    color: #6b7280;
    font-size: 0.85rem;
    margin-top: 4px;
}

.form-actions {
    display: flex;
    gap: 16px;
    justify-content: center;
    margin-top: 40px;
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
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 24px;
}

.tip-card-inline {
    background: white;
    padding: 24px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    gap: 16px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(0, 0, 0, 0.05);
    transition: all 0.3s ease;
}

.tip-card-inline:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
}

.tip-card-inline .tip-icon {
    font-size: 2rem;
    flex-shrink: 0;
}

.tip-content h6 {
    font-size: 1.1rem;
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 6px;
}

.tip-content p {
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
    
    .form-card-modern {
        padding: 24px;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .gender-selector {
        flex-direction: column;
    }
    
    .tip-card-inline {
        flex-direction: column;
        text-align: center;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Photo upload preview
    const photoInput = document.getElementById('pet_image');
    const photoPreview = document.getElementById('photoPreview');
    
    photoInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                photoPreview.innerHTML = `<img src="${e.target.result}" style="width: 100%; height: 100%; object-fit: cover;">`;
                photoPreview.style.border = '3px solid #667eea';
            };
            reader.readAsDataURL(file);
        }
    });
    
    // Form validation
    const form = document.querySelector('.modern-form');
    form.addEventListener('submit', function(e) {
        const requiredFields = form.querySelectorAll('[required]');
        let isValid = true;
        
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                isValid = false;
                field.style.borderColor = '#ef4444';
            } else {
                field.style.borderColor = '#e5e7eb';
            }
        });
        
        if (!isValid) {
            e.preventDefault();
            alert('Please fill in all required fields.');
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