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

// Initialize variables with current pet data
$petName = $pet['petName'];
$age = $pet['age'];
$type = $pet['type'];
$sex = $pet['sex'];
$breed = $pet['breed'];
$color = $pet['color'];
$current_image = $pet['image'];
$errors = array();

// Enhanced image upload function
function uploadImage($file, $target_dir, $pet_id) {
    // Create directory if it doesn't exist
    if (!file_exists($target_dir)) {
        if (!mkdir($target_dir, 0755, true)) {
            return [false, "Failed to create upload directory"];
        }
    }
    
    // Check if file was uploaded
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        if ($file['error'] === UPLOAD_ERR_NO_FILE) {
            return [null, null]; // No file uploaded
        }
        return [false, "No file was uploaded or upload failed"];
    }
    
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $upload_errors = array(
            UPLOAD_ERR_INI_SIZE => "File exceeds upload_max_filesize",
            UPLOAD_ERR_FORM_SIZE => "File exceeds MAX_FILE_SIZE",
            UPLOAD_ERR_PARTIAL => "File was only partially uploaded",
            UPLOAD_ERR_NO_TMP_DIR => "Missing temporary folder",
            UPLOAD_ERR_CANT_WRITE => "Failed to write file to disk",
            UPLOAD_ERR_EXTENSION => "PHP extension stopped upload"
        );
        $error_message = isset($upload_errors[$file['error']]) ? $upload_errors[$file['error']] : "Unknown upload error";
        return [false, $error_message];
    }
    
    // Validate file type using getimagesize (more reliable than checking extension only)
    $imageinfo = getimagesize($file["tmp_name"]);
    if ($imageinfo === false) {
        return [false, "File is not a valid image"];
    }
    
    // Check file size (5MB limit)
    if ($file["size"] > 5000000) {
        return [false, "File is too large. Maximum size is 5MB"];
    }
    
    // Get file extension
    $file_extension = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
    
    // Allow only specific image formats
    $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
    if (!in_array($file_extension, $allowed_types)) {
        return [false, "Only JPG, JPEG, PNG, and GIF files are allowed"];
    }
    
    // Generate unique filename
    $new_filename = "pet_" . $pet_id . "_" . time() . "_" . uniqid() . "." . $file_extension;
    $target_file = $target_dir . $new_filename;
    
    // Move uploaded file
    if (!move_uploaded_file($file["tmp_name"], $target_file)) {
        return [false, "Failed to save uploaded file"];
    }
    
    // Set proper file permissions
    chmod($target_file, 0644);
    
    return [true, $new_filename];
}

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
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
    
    // Process image upload if new image is provided
    $image_filename = $current_image; // Keep current image by default
    $delete_old_image = false;
    
    if (empty($errors) && isset($_FILES['pet_image'])) {
        $target_dir = "../assets/images/pets/";
        
        $upload_result = uploadImage($_FILES["pet_image"], $target_dir, $pet_id);
        
        if ($upload_result[0] === false) {
            $errors[] = $upload_result[1];
        } elseif ($upload_result[0] === true) {
            // New image uploaded successfully
            $image_filename = $upload_result[1];
            $delete_old_image = true;
        }
        // If $upload_result[0] is null, no new image was uploaded, keep current
    }
    
    // If no errors, update pet in database
    if (empty($errors)) {
        $sql = "UPDATE pet_profile SET petName = ?, age = ?, type = ?, sex = ?, breed = ?, color = ?, image = ? WHERE petID = ? AND userID = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sdsssssii", $petName, $age, $type, $sex, $breed, $color, $image_filename, $pet_id, $user_id);
        
        if ($stmt->execute()) {
            // Delete old image if new one was uploaded and update was successful
            if ($delete_old_image && !empty($current_image) && file_exists($target_dir . $current_image)) {
                unlink($target_dir . $current_image);
            }
            
            $_SESSION['success_message'] = "Pet profile updated successfully!";
            header("Location: pet_details.php?id=" . $pet_id);
            exit();
        } else {
            $errors[] = "Error updating pet: " . $conn->error;
            
            // If database update failed but new image was uploaded, delete the new image
            if ($delete_old_image && file_exists($target_dir . $image_filename)) {
                unlink($target_dir . $image_filename);
            }
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
                    <span class="page-badge">✏️ Edit Pet</span>
                    <h1 class="page-title">Edit <span class="text-gradient"><?php echo htmlspecialchars($petName); ?></span></h1>
                    <p class="page-subtitle">Update your pet's profile information</p>
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

<!-- Edit Pet Form -->
<section class="form-section-modern">
    <div class="container">
        <div class="row">
            <div class="col-lg-4">
                <!-- Current Pet Info -->
                <div class="current-pet-card">
                    <div class="current-pet-image">
                        <?php if (!empty($current_image) && file_exists("../assets/images/pets/" . $current_image)): ?>
                            <img src="../assets/images/pets/<?php echo htmlspecialchars($current_image); ?>" alt="<?php echo htmlspecialchars($petName); ?>" id="currentImage">
                        <?php else: ?>
                            <div class="image-placeholder" id="currentImage">
                                <i class="fas fa-paw"></i>
                                <span>No Photo</span>
                            </div>
                        <?php endif; ?>
                        
                        <div class="pet-type-indicator">
                            <?php 
                            $type_icons = [
                                'Dog' => '🐕', 'Cat' => '🐱', 'Bird' => '🐦',
                                'Fish' => '🐠', 'Rabbit' => '🐰', 'Hamster' => '🐹'
                            ];
                            echo isset($type_icons[$type]) ? $type_icons[$type] : '🐾';
                            ?>
                        </div>
                    </div>
                    
                    <div class="current-pet-info">
                        <h4><?php echo htmlspecialchars($petName); ?></h4>
                        <div class="pet-summary">
                            <span class="summary-item"><?php echo htmlspecialchars($breed); ?></span>
                            <span class="summary-item"><?php echo $age; ?> years old</span>
                            <span class="summary-item"><?php echo $sex; ?></span>
                            <span class="summary-item"><?php echo htmlspecialchars($color); ?></span>
                        </div>
                    </div>
                </div>
                
                <!-- Tips Card -->
                <div class="tips-card">
                    <h6><i class="fas fa-lightbulb me-2"></i>Update Tips</h6>
                    <ul>
                        <li>Keep information current for best care</li>
                        <li>High-quality photos help sitters identify your pet</li>
                        <li>Accurate age helps with care planning</li>
                        <li>Breed info assists with specialized care</li>
                    </ul>
                </div>
            </div>
            
            <div class="col-lg-8">
                <div class="form-card-modern">
                    <div class="form-header">
                        <div class="form-icon">
                            <i class="fas fa-edit"></i>
                        </div>
                        <h4>Update Pet Information</h4>
                        <p>Modify your pet's details below</p>
                    </div>
                    
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger alert-modern">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <ul class="mb-0">
                                <?php foreach($errors as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . "?id=" . $pet_id); ?>" method="post" enctype="multipart/form-data" class="modern-form" id="editPetForm">
                        <!-- Pet Photo Upload -->
                        <div class="form-group-modern photo-upload-group">
                            <label class="form-label-modern">Update Pet Photo</label>
                            <div class="photo-update-container">
                                <div class="current-photo-section">
                                    <label>Current Photo:</label>
                                    <div class="current-photo">
                                        <?php if (!empty($current_image) && file_exists("../assets/images/pets/" . $current_image)): ?>
                                            <img src="../assets/images/pets/<?php echo htmlspecialchars($current_image); ?>" alt="Current photo">
                                        <?php else: ?>
                                            <div class="no-photo">
                                                <i class="fas fa-image"></i>
                                                <span>No current photo</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="photo-upload-area">
                                    <label>New Photo (Optional):</label>
                                    <div class="photo-preview" id="photoPreview">
                                        <div class="upload-placeholder">
                                            <i class="fas fa-camera"></i>
                                            <span>Choose New Photo</span>
                                            <small>JPG, PNG, GIF up to 5MB</small>
                                        </div>
                                    </div>
                                    <input type="file" class="photo-input" id="pet_image" name="pet_image" accept="image/jpeg,image/png,image/jpg,image/gif">
                                    <label for="pet_image" class="photo-upload-btn">
                                        <i class="fas fa-upload me-2"></i> Choose New Photo
                                    </label>
                                    <small class="form-text">Leave empty to keep current photo</small>
                                    <button type="button" class="btn btn-sm btn-outline-secondary mt-2" id="clearImageBtn" style="display:none;">
                                        <i class="fas fa-times me-1"></i> Clear Selection
                                    </button>
                                </div>
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
                                        <input type="number" class="form-control-modern" id="age" name="age" min="0" step="0.1" value="<?php echo htmlspecialchars($age); ?>" required>
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
                                <i class="fas fa-save me-2"></i> Update Pet Profile
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

.current-pet-card {
    background: white;
    border-radius: 20px;
    padding: 24px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(0, 0, 0, 0.05);
    margin-bottom: 24px;
    position: sticky;
    top: 100px;
}

.current-pet-image {
    position: relative;
    width: 100%;
    height: 250px;
    border-radius: 16px;
    overflow: hidden;
    margin-bottom: 20px;
}

.current-pet-image img {
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
}

.image-placeholder i {
    font-size: 2.5rem;
    margin-bottom: 8px;
}

.pet-type-indicator {
    position: absolute;
    top: 16px;
    right: 16px;
    font-size: 2rem;
    background: rgba(255, 255, 255, 0.9);
    border-radius: 50%;
    width: 50px;
    height: 50px;
    display: flex;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(10px);
}

.current-pet-info h4 {
    color: #1e293b;
    font-weight: 700;
    margin-bottom: 16px;
    text-align: center;
}

.pet-summary {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.summary-item {
    background: #f8fafc;
    color: #64748b;
    padding: 8px 12px;
    border-radius: 8px;
    font-size: 0.9rem;
    font-weight: 500;
    text-align: center;
}

.tips-card {
    background: #f0f9ff;
    border: 1px solid #bae6fd;
    border-radius: 16px;
    padding: 20px;
}

.tips-card h6 {
    color: #0369a1;
    margin-bottom: 12px;
    font-weight: 600;
}

.tips-card ul {
    margin: 0;
    padding-left: 20px;
    color: #0c4a6e;
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

.photo-update-container {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-top: 12px;
}

.current-photo-section label,
.photo-upload-area label {
    display: block;
    font-weight: 500;
    color: #374151;
    margin-bottom: 8px;
    font-size: 0.9rem;
}

.current-photo {
    text-align: center;
}

.current-photo img {
    width: 100%;
    height: 180px;
    object-fit: cover;
    border-radius: 12px;
    border: 2px solid #e2e8f0;
}

.no-photo {
    width: 100%;
    height: 180px;
    background: #f1f5f9;
    border: 2px dashed #d1d5db;
    border-radius: 12px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: #9ca3af;
}

.no-photo i {
    font-size: 2rem;
    margin-bottom: 8px;
}

.photo-upload-area {
    text-align: center;
}

.photo-preview {
    width: 100%;
    height: 120px;
    border: 2px dashed #d1d5db;
    border-radius: 12px;
    margin-bottom: 12px;
    transition: all 0.3s ease;
    overflow: hidden;
}

.photo-preview:hover {
    border-color: #667eea;
    background: #f8f9ff;
}

.photo-preview.has-image {
    border-color: #667eea;
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
    font-size: 1.5rem;
    margin-bottom: 6px;
}

.upload-placeholder span {
    font-weight: 600;
    margin-bottom: 2px;
}

.upload-placeholder small {
    font-size: 0.75rem;
}

.photo-input {
    display: none;
}

.photo-upload-btn {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    padding: 8px 16px;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-block;
    font-weight: 600;
    font-size: 0.9rem;
    margin-bottom: 8px;
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
    
    .current-pet-card {
        position: static;
        margin-bottom: 24px;
    }
    
    .photo-update-container {
        grid-template-columns: 1fr;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .gender-selector {
        flex-direction: column;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Photo upload preview
    const photoInput = document.getElementById('pet_image');
    const photoPreview = document.getElementById('photoPreview');
    const clearImageBtn = document.getElementById('clearImageBtn');
    
    if (photoInput && photoPreview) {
        photoInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                // Validate file type
                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                if (!allowedTypes.includes(file.type)) {
                    alert('Please select a valid image file (JPG, PNG, or GIF)');
                    this.value = '';
                    return;
                }
                
                // Validate file size (5MB)
                if (file.size > 5000000) {
                    alert('File size must be less than 5MB');
                    this.value = '';
                    return;
                }
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    photoPreview.innerHTML = `<img src="${e.target.result}" style="width: 100%; height: 100%; object-fit: cover; border-radius: 8px;">`;
                    photoPreview.classList.add('has-image');
                    clearImageBtn.style.display = 'inline-block';
                };
                reader.readAsDataURL(file);
            }
        });
        
        clearImageBtn.addEventListener('click', function() {
            photoInput.value = '';
            photoPreview.innerHTML = `
                <div class="upload-placeholder">
                    <i class="fas fa-camera"></i>
                    <span>Choose New Photo</span>
                    <small>JPG, PNG, GIF up to 5MB</small>
                </div>
            `;
            photoPreview.classList.remove('has-image');
            this.style.display = 'none';
        });
    }
    
    // Form validation
    const form = document.getElementById('editPetForm');
    if (form) {
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
                return false;
            }
            
            // Show loading state
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Updating...';
            }
        });
    }
});
</script>

<?php
// Include footer
include_once '../includes/footer.php';

// Close database connection
$conn->close();
?>