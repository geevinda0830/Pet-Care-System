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
$errors = array();
$success_message = '';

// Get current user data
$user_sql = "SELECT * FROM pet_sitter WHERE userID = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();

if ($user_result->num_rows === 0) {
    $_SESSION['error_message'] = "User profile not found.";
    header("Location: ../login.php");
    exit();
}

$user = $user_result->fetch_assoc();
$user_stmt->close();

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
    // Get and validate form data
    $fullName = trim($_POST['fullName']);
    $email = trim($_POST['email']);
    $contact = trim($_POST['contact']);
    $address = trim($_POST['address']);
    $gender = $_POST['gender'];
    $service = trim($_POST['service']);
    $qualifications = trim($_POST['qualifications']);
    $experience = trim($_POST['experience']);
    $specialization = trim($_POST['specialization']);
    $price = floatval($_POST['price']);
    $latitude = isset($_POST['latitude']) ? floatval($_POST['latitude']) : null;
    $longitude = isset($_POST['longitude']) ? floatval($_POST['longitude']) : null;
    
    // Validate required fields
    if (empty($fullName)) {
        $errors[] = "Full name is required";
    }
    
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    if (empty($contact)) {
        $errors[] = "Contact number is required";
    }
    
    if (empty($address)) {
        $errors[] = "Address is required";
    }
    
    if (empty($service)) {
        $errors[] = "Service type is required";
    }
    
    if ($price <= 0) {
        $errors[] = "Price must be greater than 0";
    }
    
    // Check if email already exists for other users
    if (empty($errors)) {
        $email_check_sql = "SELECT userID FROM pet_sitter WHERE email = ? AND userID != ?";
        $email_check_stmt = $conn->prepare($email_check_sql);
        $email_check_stmt->bind_param("si", $email, $user_id);
        $email_check_stmt->execute();
        $email_check_result = $email_check_stmt->get_result();
        
        if ($email_check_result->num_rows > 0) {
            $errors[] = "Email already exists";
        }
        $email_check_stmt->close();
    }
    
    // Process image upload if no errors
    $image_filename = $user['image']; // Keep existing image by default
    if (empty($errors) && isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $target_dir = "../assets/images/pet_sitters/";
        
        // Enhanced directory creation with proper permissions
        if (!file_exists($target_dir)) {
            if (!mkdir($target_dir, 0755, true)) {
                $errors[] = "Failed to create upload directory. Please check server permissions.";
            }
        }
        
        if (empty($errors)) {
            // Validate file upload
            if ($_FILES['profile_image']['error'] !== UPLOAD_ERR_OK) {
                $upload_errors = [
                    UPLOAD_ERR_INI_SIZE => "The uploaded file exceeds the upload_max_filesize directive in php.ini",
                    UPLOAD_ERR_FORM_SIZE => "The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form",
                    UPLOAD_ERR_PARTIAL => "The uploaded file was only partially uploaded",
                    UPLOAD_ERR_NO_TMP_DIR => "Missing a temporary folder",
                    UPLOAD_ERR_CANT_WRITE => "Failed to write file to disk",
                    UPLOAD_ERR_EXTENSION => "A PHP extension stopped the file upload"
                ];
                $error_message = isset($upload_errors[$_FILES['profile_image']['error']]) ? 
                               $upload_errors[$_FILES['profile_image']['error']] : "Unknown upload error";
                $errors[] = $error_message;
            } else {
                // Validate file type using getimagesize (more secure)
                $imageinfo = getimagesize($_FILES["profile_image"]["tmp_name"]);
                if ($imageinfo === false) {
                    $errors[] = "File is not a valid image.";
                } else {
                    // Check file size (limit to 5MB)
                    if ($_FILES["profile_image"]["size"] > 5000000) {
                        $errors[] = "File is too large. Maximum file size is 5MB.";
                    } else {
                        // Get file extension
                        $file_extension = strtolower(pathinfo($_FILES["profile_image"]["name"], PATHINFO_EXTENSION));
                        
                        // Allow only specific image formats
                        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
                        if (!in_array($file_extension, $allowed_types)) {
                            $errors[] = "Only JPG, JPEG, PNG, and GIF files are allowed.";
                        } else {
                            // Generate unique filename
                            $new_image_filename = "sitter_" . $user_id . "_" . time() . "_" . uniqid() . "." . $file_extension;
                            $target_file = $target_dir . $new_image_filename;
                            
                            // Attempt to move uploaded file
                            if (move_uploaded_file($_FILES["profile_image"]["tmp_name"], $target_file)) {
                                // Set proper file permissions
                                chmod($target_file, 0644);
                                
                                // Delete old image if it exists
                                if (!empty($user['image']) && file_exists($target_dir . $user['image'])) {
                                    unlink($target_dir . $user['image']);
                                }
                                
                                $image_filename = $new_image_filename;
                            } else {
                                $errors[] = "Failed to move uploaded file. Please check directory permissions.";
                            }
                        }
                    }
                }
            }
        }
    }
    
    // If no errors, update profile
    if (empty($errors)) {
        try {
            // Check if latitude and longitude columns exist
            $check_columns = $conn->query("SHOW COLUMNS FROM pet_sitter LIKE 'latitude'");
            $has_location = $check_columns->num_rows > 0;
            
            if ($has_location) {
                $update_sql = "UPDATE pet_sitter SET fullName = ?, email = ?, contact = ?, address = ?, gender = ?, service = ?, qualifications = ?, experience = ?, specialization = ?, price = ?, latitude = ?, longitude = ?, image = ? WHERE userID = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("sssssssssddssi", $fullName, $email, $contact, $address, $gender, $service, $qualifications, $experience, $specialization, $price, $latitude, $longitude, $image_filename, $user_id);
            } else {
                $update_sql = "UPDATE pet_sitter SET fullName = ?, email = ?, contact = ?, address = ?, gender = ?, service = ?, qualifications = ?, experience = ?, specialization = ?, price = ?, image = ? WHERE userID = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("sssssssssdsi", $fullName, $email, $contact, $address, $gender, $service, $qualifications, $experience, $specialization, $price, $image_filename, $user_id);
            }
            
            if ($update_stmt->execute()) {
                $success_message = "Profile updated successfully!";
                // Refresh user data
                $user_stmt = $conn->prepare("SELECT * FROM pet_sitter WHERE userID = ?");
                $user_stmt->bind_param("i", $user_id);
                $user_stmt->execute();
                $user_result = $user_stmt->get_result();
                $user = $user_result->fetch_assoc();
                $user_stmt->close();
            } else {
                $errors[] = "Error updating profile: " . $conn->error;
            }
            
            $update_stmt->close();
        } catch (Exception $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}

// Include header
include_once '../includes/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pet Sitter Profile - Pet Care System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .profile-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .profile-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        
        .profile-form-card {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .image-upload-section {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .current-image {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            overflow: hidden;
            border: 3px solid #667eea;
        }
        
        .current-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .no-image-placeholder {
            width: 100%;
            height: 100%;
            background: #f8f9fa;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #6c757d;
        }
        
        .no-image-placeholder i {
            font-size: 2rem;
            margin-bottom: 5px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }
        
        .form-control {
            border-radius: 8px;
            border: 1px solid #ddd;
            padding: 10px 15px;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .btn-primary {
            background: #667eea;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 600;
        }
        
        .btn-primary:hover {
            background: #5a6fd8;
        }
        
        .alert {
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .section-title {
            color: #333;
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }
    </style>
</head>
<body>
    <div class="profile-container">
        <!-- Profile Header -->
        <div class="profile-header">
            <h1><i class="fas fa-user-edit me-2"></i>Pet Sitter Profile</h1>
            <p>Update your profile information and settings</p>
        </div>
        
        <!-- Success Message -->
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>
        
        <!-- Error Messages -->
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i>
                <strong>Please fix the following errors:</strong>
                <ul class="mb-0 mt-2">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <!-- Profile Form -->
        <div class="profile-form-card">
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data" class="profile-form">
                <input type="hidden" name="update_profile" value="1">
                
                <!-- Profile Image Section -->
                <div class="form-section">
                    <h6 class="section-title">Profile Photo</h6>
                    <div class="image-upload-section">
                        <div class="current-image">
                            <?php if (!empty($user['image']) && file_exists("../assets/images/pet_sitters/" . $user['image'])): ?>
                                <img src="../assets/images/pet_sitters/<?php echo htmlspecialchars($user['image']); ?>" 
                                     alt="Current Profile" id="current-profile-image">
                            <?php else: ?>
                                <div class="no-image-placeholder" id="current-profile-image">
                                    <i class="fas fa-user"></i>
                                    <span>No photo</span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="image-upload-controls">
                            <input type="file" id="profile_image" name="profile_image" accept="image/*" style="display: none;">
                            <button type="button" class="btn btn-outline-primary" onclick="document.getElementById('profile_image').click()">
                                <i class="fas fa-camera me-2"></i> Change Photo
                            </button>
                            <small class="text-muted d-block mt-2">JPG, PNG, GIF up to 5MB</small>
                        </div>
                    </div>
                </div>
                
                <!-- Personal Information -->
                <div class="form-section">
                    <h6 class="section-title">Personal Information</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Full Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="fullName" 
                                       value="<?php echo htmlspecialchars($user['fullName']); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" name="email" 
                                       value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Contact Number <span class="text-danger">*</span></label>
                                <input type="tel" class="form-control" name="contact" 
                                       value="<?php echo htmlspecialchars($user['contact']); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Gender</label>
                                <select class="form-control" name="gender">
                                    <option value="Male" <?php echo ($user['gender'] === 'Male') ? 'selected' : ''; ?>>Male</option>
                                    <option value="Female" <?php echo ($user['gender'] === 'Female') ? 'selected' : ''; ?>>Female</option>
                                    <option value="Other" <?php echo ($user['gender'] === 'Other') ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Address <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="address" rows="3" required><?php echo htmlspecialchars($user['address']); ?></textarea>
                    </div>
                </div>
                
                <!-- Professional Information -->
                <div class="form-section">
                    <h6 class="section-title">Professional Information</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Service Type <span class="text-danger">*</span></label>
                                <select class="form-control" name="service" required>
                                    <option value="">Select Service</option>
                                    <option value="Pet Sitting" <?php echo ($user['service'] === 'Pet Sitting') ? 'selected' : ''; ?>>Pet Sitting</option>
                                    <option value="Dog Walking" <?php echo ($user['service'] === 'Dog Walking') ? 'selected' : ''; ?>>Dog Walking</option>
                                    <option value="Pet Grooming" <?php echo ($user['service'] === 'Pet Grooming') ? 'selected' : ''; ?>>Pet Grooming</option>
                                    <option value="Pet Training" <?php echo ($user['service'] === 'Pet Training') ? 'selected' : ''; ?>>Pet Training</option>
                                    <option value="Veterinary Care" <?php echo ($user['service'] === 'Veterinary Care') ? 'selected' : ''; ?>>Veterinary Care</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Hourly Rate (Rs.) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="price" 
                                       value="<?php echo htmlspecialchars($user['price']); ?>" 
                                       min="1" step="0.01" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Qualifications</label>
                        <textarea class="form-control" name="qualifications" rows="3" 
                                  placeholder="List your relevant qualifications and certifications"><?php echo htmlspecialchars($user['qualifications']); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Experience</label>
                        <textarea class="form-control" name="experience" rows="3" 
                                  placeholder="Describe your experience with pets"><?php echo htmlspecialchars($user['experience']); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Specialization</label>
                        <textarea class="form-control" name="specialization" rows="3" 
                                  placeholder="Any special skills or areas of expertise"><?php echo htmlspecialchars($user['specialization']); ?></textarea>
                    </div>
                </div>
                
                <!-- Form Actions -->
                <div class="form-actions text-center">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-save me-2"></i> Update Profile
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const profileImageInput = document.getElementById('profile_image');
            const currentProfileImage = document.getElementById('current-profile-image');
            
            if (profileImageInput) {
                profileImageInput.addEventListener('change', function(e) {
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
                        
                        // Create preview
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            if (currentProfileImage) {
                                currentProfileImage.innerHTML = `<img src="${e.target.result}" alt="Preview" style="width: 100%; height: 100%; object-fit: cover;">`;
                            }
                        };
                        reader.readAsDataURL(file);
                    }
                });
            }
            
            // Form submission enhancement
            const profileForm = document.querySelector('.profile-form');
            if (profileForm) {
                profileForm.addEventListener('submit', function(e) {
                    const submitBtn = this.querySelector('button[type="submit"]');
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Updating Profile...';
                    }
                });
            }
        });
    </script>
</body>
</html>

<?php
// Include footer
include_once '../includes/footer.php';

// Close database connection
$conn->close();
?>