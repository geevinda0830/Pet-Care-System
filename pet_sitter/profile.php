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

// Get current user data
$user_sql = "SELECT * FROM pet_sitter WHERE userID = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user = $user_result->fetch_assoc();
$user_stmt->close();

// Initialize variables with current data
$fullName = $user['fullName'];
$email = $user['email'];
$contact = $user['contact'];
$address = $user['address'];
$gender = $user['gender'];
$service = $user['service'];
$qualifications = $user['qualifications'];
$experience = $user['experience'];
$specialization = $user['specialization'];
$price = $user['price'];
$latitude = $user['latitude'];
$longitude = $user['longitude'];
$errors = array();
$success_message = '';

// Debug: Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    error_log("Form submitted. POST data: " . print_r($_POST, true));
}

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['update_profile'])) {
        // Get form data
        $fullName = trim($_POST['fullName']);
        $email = trim($_POST['email']);
        $contact = trim($_POST['contact']);
        $address = trim($_POST['address']);
        $gender = $_POST['gender'];
        $service = trim($_POST['service']);
        $qualifications = trim($_POST['qualifications']);
        $experience = trim($_POST['experience']);
        $specialization = trim($_POST['specialization']);
        $price = $_POST['price'];
        $latitude = $_POST['latitude'];
        $longitude = $_POST['longitude'];
        
        // Validate form data
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
        
        if (empty($price) || !is_numeric($price) || $price <= 0) {
            $errors[] = "Valid price is required";
        }
        
        // Check if email already exists for another user
        $email_check_sql = "SELECT userID FROM pet_sitter WHERE email = ? AND userID != ?";
        $email_check_stmt = $conn->prepare($email_check_sql);
        $email_check_stmt->bind_param("si", $email, $user_id);
        $email_check_stmt->execute();
        $email_check_result = $email_check_stmt->get_result();
        
        if ($email_check_result->num_rows > 0) {
            $errors[] = "Email already exists";
        }
        $email_check_stmt->close();
        
        // Process image upload if no errors
        $image_filename = $user['image']; // Keep existing image by default
        if (empty($errors) && isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $target_dir = "../assets/images/pet_sitters/";
            
            // Create directory if it doesn't exist
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            
            $file_extension = strtolower(pathinfo($_FILES["profile_image"]["name"], PATHINFO_EXTENSION));
            $new_image_filename = "sitter_" . time() . "_" . uniqid() . "." . $file_extension;
            $target_file = $target_dir . $new_image_filename;
            $upload_ok = true;
            
            // Check if image file is actual image
            $check = getimagesize($_FILES["profile_image"]["tmp_name"]);
            if ($check === false) {
                $errors[] = "File is not an image.";
                $upload_ok = false;
            }
            
            // Check file size (limit to 5MB)
            if ($_FILES["profile_image"]["size"] > 5000000) {
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
                if (move_uploaded_file($_FILES["profile_image"]["tmp_name"], $target_file)) {
                    // Delete old image if it exists
                    if (!empty($user['image']) && file_exists($target_dir . $user['image'])) {
                        unlink($target_dir . $user['image']);
                    }
                    $image_filename = $new_image_filename;
                } else {
                    $errors[] = "There was an error uploading your file.";
                }
            }
        }
        
        // If no errors, update profile
        if (empty($errors)) {
            try {
                // First, check if latitude and longitude columns exist
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
                    $_SESSION['success_message'] = "Profile updated successfully!";
                    header("Location: " . $_SERVER['PHP_SELF']);
                    exit();
                } else {
                    $errors[] = "Error updating profile: " . $conn->error;
                }
                
                $update_stmt->close();
            } catch (Exception $e) {
                $errors[] = "Database error: " . $e->getMessage();
            }
        }
    } elseif (isset($_POST['change_password'])) {
        // Change password
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Validate password change
        if (empty($current_password)) {
            $errors[] = "Current password is required";
        }
        
        if (empty($new_password)) {
            $errors[] = "New password is required";
        } elseif (strlen($new_password) < 6) {
            $errors[] = "New password must be at least 6 characters";
        }
        
        if ($new_password !== $confirm_password) {
            $errors[] = "New passwords do not match";
        }
        
        // Verify current password
        if (empty($errors)) {
            if (!password_verify($current_password, $user['password'])) {
                $errors[] = "Current password is incorrect";
            }
        }
        
        // Update password if no errors
        if (empty($errors)) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $password_sql = "UPDATE pet_sitter SET password = ? WHERE userID = ?";
            $password_stmt = $conn->prepare($password_sql);
            $password_stmt->bind_param("si", $hashed_password, $user_id);
            
            if ($password_stmt->execute()) {
                $success_message = "Password changed successfully!";
            } else {
                $errors[] = "Error changing password: " . $conn->error;
            }
            
            $password_stmt->close();
        }
    }
}

// Calculate profile completion
$total_fields = 11;
$filled_fields = 0;

if (!empty($user['fullName'])) $filled_fields++;
if (!empty($user['email'])) $filled_fields++;
if (!empty($user['contact'])) $filled_fields++;
if (!empty($user['address'])) $filled_fields++;
if (!empty($user['gender'])) $filled_fields++;
if (!empty($user['service'])) $filled_fields++;
if (!empty($user['qualifications'])) $filled_fields++;
if (!empty($user['experience'])) $filled_fields++;
if (!empty($user['specialization'])) $filled_fields++;
if (!empty($user['price'])) $filled_fields++;
if (!empty($user['image'])) $filled_fields++;

$completion_percentage = round(($filled_fields / $total_fields) * 100);

// Include header
include_once '../includes/header.php';
?>

<!-- Modern Profile Header -->
<section class="profile-header-section">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <div class="profile-header-content">
                    <span class="section-badge">👤 Profile Management</span>
                    <h1 class="profile-title">Manage Your <span class="text-gradient">Profile</span></h1>
                    <p class="profile-subtitle">Keep your information up-to-date to attract more pet owners.</p>
                </div>
            </div>
            <div class="col-lg-4 text-lg-end">
                <a href="dashboard.php" class="btn btn-outline-light btn-lg">
                    <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </div>
</section>

<!-- Profile Completion Status -->
<section class="profile-completion-section">
    <div class="container">
        <div class="completion-status-card">
            <div class="completion-header">
                <div class="completion-avatar">
                    <?php if (!empty($user['image'])): ?>
                        <img src="../assets/images/pet_sitters/<?php echo htmlspecialchars($user['image']); ?>" alt="Profile">
                    <?php else: ?>
                        <div class="avatar-placeholder">
                            <i class="fas fa-user"></i>
                        </div>
                    <?php endif; ?>
                    <div class="completion-badge"><?php echo $completion_percentage; ?>%</div>
                </div>
                <div class="completion-info">
                    <h4>Profile Completion</h4>
                    <div class="progress-container">
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo $completion_percentage; ?>%"></div>
                        </div>
                        <span class="progress-text"><?php echo $completion_percentage; ?>% Complete</span>
                    </div>
                    <?php if ($completion_percentage < 100): ?>
                        <p>Complete your profile to get more visibility and bookings.</p>
                    <?php else: ?>
                        <p class="text-success">Excellent! Your profile is complete.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Profile Form Section -->
<section class="profile-form-section">
    <div class="container">
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
        
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success alert-modern">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-modern">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo htmlspecialchars($_SESSION['success_message']); ?>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>
        
        <!-- Debug info (remove in production) -->
        <?php if ($_SERVER["REQUEST_METHOD"] == "POST"): ?>
            <div class="alert alert-info">
                <strong>Debug:</strong> Form submitted. 
                <?php if (isset($_POST['update_profile'])): ?>
                    Update profile action detected.
                <?php else: ?>
                    No update_profile in POST data.
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <div class="row g-4">
            <!-- Profile Information -->
            <div class="col-lg-8">
                <div class="profile-form-card">
                    <div class="form-header">
                        <div class="form-icon">
                            <i class="fas fa-user-edit"></i>
                        </div>
                        <h3>Profile Information</h3>
                        <p>Update your personal and professional details</p>
                    </div>
                    
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data" class="profile-form">
                        <input type="hidden" name="update_profile" value="1">
                        
                        <!-- Profile Image Section -->
                        <div class="form-section">
                            <h6 class="section-title">Profile Photo</h6>
                            <div class="image-upload-section">
                                <div class="current-image">
                                    <?php if (!empty($user['image'])): ?>
                                        <img src="../assets/images/pet_sitters/<?php echo htmlspecialchars($user['image']); ?>" alt="Current Profile" id="current-profile-image">
                                    <?php else: ?>
                                        <div class="no-image-placeholder" id="current-profile-image">
                                            <i class="fas fa-user"></i>
                                            <span>No photo uploaded</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="image-upload-controls">
                                    <input type="file" id="profile_image" name="profile_image" accept="image/*" style="display: none;">
                                    <button type="button" class="btn btn-outline-primary" onclick="document.getElementById('profile_image').click()">
                                        <i class="fas fa-camera me-2"></i> Change Photo
                                    </button>
                                    <small class="text-muted d-block mt-2">JPG, PNG or JPEG. Max 5MB.</small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Personal Information -->
                        <div class="form-section">
                            <h6 class="section-title">Personal Information</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group-modern">
                                        <label for="fullName" class="form-label-modern">Full Name <span class="required">*</span></label>
                                        <input type="text" class="form-control-modern" id="fullName" name="fullName" value="<?php echo htmlspecialchars($fullName); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group-modern">
                                        <label for="email" class="form-label-modern">Email Address <span class="required">*</span></label>
                                        <input type="email" class="form-control-modern" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group-modern">
                                        <label for="contact" class="form-label-modern">Contact Number <span class="required">*</span></label>
                                        <input type="tel" class="form-control-modern" id="contact" name="contact" value="<?php echo htmlspecialchars($contact); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group-modern">
                                        <label for="gender" class="form-label-modern">Gender</label>
                                        <select class="form-control-modern" id="gender" name="gender">
                                            <option value="">Select Gender</option>
                                            <option value="Male" <?php echo ($gender === 'Male') ? 'selected' : ''; ?>>Male</option>
                                            <option value="Female" <?php echo ($gender === 'Female') ? 'selected' : ''; ?>>Female</option>
                                            <option value="Other" <?php echo ($gender === 'Other') ? 'selected' : ''; ?>>Other</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group-modern">
                                <label for="address" class="form-label-modern">Address <span class="required">*</span></label>
                                <textarea class="form-control-modern" id="address" name="address" rows="3" required><?php echo htmlspecialchars($address); ?></textarea>
                            </div>
                        </div>
                        
                        <!-- Professional Information -->
                        <div class="form-section">
                            <h6 class="section-title">Professional Information</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group-modern">
                                        <label for="service" class="form-label-modern">Primary Service <span class="required">*</span></label>
                                        <select class="form-control-modern" id="service" name="service" required>
                                            <option value="">Select Service Type</option>
                                            <option value="Pet Sitting" <?php echo ($service === 'Pet Sitting') ? 'selected' : ''; ?>>Pet Sitting</option>
                                            <option value="Dog Walking" <?php echo ($service === 'Dog Walking') ? 'selected' : ''; ?>>Dog Walking</option>
                                            <option value="Pet Boarding" <?php echo ($service === 'Pet Boarding') ? 'selected' : ''; ?>>Pet Boarding</option>
                                            <option value="Pet Grooming" <?php echo ($service === 'Pet Grooming') ? 'selected' : ''; ?>>Pet Grooming</option>
                                            <option value="Pet Training" <?php echo ($service === 'Pet Training') ? 'selected' : ''; ?>>Pet Training</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group-modern">
                                        <label for="price" class="form-label-modern">Hourly Rate (Rs.) <span class="required">*</span></label>
                                        <input type="number" class="form-control-modern" id="price" name="price" min="0" step="0.01" value="<?php echo htmlspecialchars($price); ?>" required>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group-modern">
                                <label for="qualifications" class="form-label-modern">Qualifications</label>
                                <textarea class="form-control-modern" id="qualifications" name="qualifications" rows="3" placeholder="List your relevant qualifications and certifications..."><?php echo htmlspecialchars($qualifications); ?></textarea>
                            </div>
                            <div class="form-group-modern">
                                <label for="experience" class="form-label-modern">Experience</label>
                                <textarea class="form-control-modern" id="experience" name="experience" rows="3" placeholder="Describe your experience with pets..."><?php echo htmlspecialchars($experience); ?></textarea>
                            </div>
                            <div class="form-group-modern">
                                <label for="specialization" class="form-label-modern">Specialization</label>
                                <textarea class="form-control-modern" id="specialization" name="specialization" rows="3" placeholder="Any special skills or pet types you specialize in..."><?php echo htmlspecialchars($specialization); ?></textarea>
                            </div>
                        </div>
                        
                        <!-- Location -->
                        <!-- <div class="form-section">
                            <h6 class="section-title">Location</h6>
                            <div class="map-container" id="map-container"></div>
                            <input type="hidden" id="latitude" name="latitude" value="<?php echo htmlspecialchars($latitude); ?>">
                            <input type="hidden" id="longitude" name="longitude" value="<?php echo htmlspecialchars($longitude); ?>">
                            <small class="text-muted">Drag the marker to set your exact location</small>
                        </div> -->
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary-gradient btn-lg">
                                <i class="fas fa-save me-2"></i> Update Profile
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Password Change -->
                <div class="profile-form-card">
                    <div class="form-header">
                        <div class="form-icon security">
                            <i class="fas fa-lock"></i>
                        </div>
                        <h4>Change Password</h4>
                        <p>Update your account password</p>
                    </div>
                    
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="password-form">
                        <input type="hidden" name="change_password" value="1">
                        
                        <div class="form-group-modern">
                            <label for="current_password" class="form-label-modern">Current Password</label>
                            <input type="password" class="form-control-modern" id="current_password" name="current_password" required>
                        </div>
                        
                        <div class="form-group-modern">
                            <label for="new_password" class="form-label-modern">New Password</label>
                            <input type="password" class="form-control-modern" id="new_password" name="new_password" required>
                        </div>
                        
                        <div class="form-group-modern">
                            <label for="confirm_password" class="form-label-modern">Confirm New Password</label>
                            <input type="password" class="form-control-modern" id="confirm_password" name="confirm_password" required>
                        </div>
                        
                        <button type="submit" class="btn btn-secondary w-100">
                            <i class="fas fa-key me-2"></i> Change Password
                        </button>
                    </form>
                </div>
                
                <!-- Quick Stats -->
                <div class="profile-form-card">
                    <div class="form-header">
                        <div class="form-icon stats">
                            <i class="fas fa-chart-bar"></i>
                        </div>
                        <h4>Profile Stats</h4>
                    </div>
                    
                    <div class="stats-list">
                        <div class="stat-item">
                            <span class="stat-label">Profile Completion</span>
                            <span class="stat-value"><?php echo $completion_percentage; ?>%</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Account Status</span>
                            <span class="stat-value status-<?php echo strtolower($user['approval_status']); ?>">
                                <?php echo $user['approval_status']; ?>
                            </span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Member Since</span>
                            <span class="stat-value"><?php echo date('M Y', strtotime($user['created_at'])); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
.profile-header-section {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 80px 0;
    position: relative;
    overflow: hidden;
}

.profile-header-section::before {
    content: '';
    position: absolute;
    width: 100%;
    height: 100%;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="20" cy="20" r="2" fill="rgba(255,255,255,0.1)"/><circle cx="80" cy="40" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="40" cy="80" r="1.5" fill="rgba(255,255,255,0.1)"/></svg>');
    animation: float 20s infinite linear;
}

.profile-title {
    font-size: 3.5rem;
    font-weight: 800;
    line-height: 1.2;
    margin-bottom: 24px;
}

.profile-subtitle {
    font-size: 1.2rem;
    opacity: 0.9;
}

.profile-completion-section {
    padding: 0;
    margin-top: -40px;
    position: relative;
    z-index: 2;
}

.completion-status-card {
    background: white;
    border-radius: 20px;
    padding: 32px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    border: 1px solid rgba(0, 0, 0, 0.05);
    margin: 0 15px;
}

.completion-header {
    display: flex;
    align-items: center;
    gap: 24px;
}

.completion-avatar {
    position: relative;
    flex-shrink: 0;
}

.completion-avatar img {
    width: 100px;
    height: 100px;
    border-radius: 20px;
    object-fit: cover;
    border: 3px solid white;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
}

.avatar-placeholder {
    width: 100px;
    height: 100px;
    border-radius: 20px;
    background: linear-gradient(135deg, #e2e8f0, #cbd5e1);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    color: #64748b;
    border: 3px solid white;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
}

.completion-badge {
    position: absolute;
    bottom: -5px;
    right: -5px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    padding: 6px 10px;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 600;
    border: 2px solid white;
}

.completion-info h4 {
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 12px;
}

.progress-container {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 12px;
}

.progress-bar {
    flex: 1;
    height: 8px;
    background: #e2e8f0;
    border-radius: 50px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #667eea, #764ba2);
    border-radius: 50px;
    transition: width 0.3s ease;
}

.progress-text {
    font-weight: 600;
    color: #667eea;
    font-size: 0.9rem;
}

.profile-form-section {
    padding: 60px 0;
}

.profile-form-card {
    background: white;
    border-radius: 20px;
    padding: 32px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(0, 0, 0, 0.05);
    margin-bottom: 24px;
    height: fit-content;
}

.form-header {
    text-align: center;
    margin-bottom: 32px;
}

.form-icon {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
    margin: 0 auto 16px;
}

.form-icon.security {
    background: linear-gradient(135deg, #ef4444, #dc2626);
}

.form-icon.stats {
    background: linear-gradient(135deg, #10b981, #059669);
}

.form-header h3, .form-header h4 {
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 8px;
}

.form-header p {
    color: #64748b;
    margin-bottom: 0;
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
    font-size: 1.1rem;
}

.image-upload-section {
    display: flex;
    align-items: center;
    gap: 24px;
}

.current-image img {
    width: 120px;
    height: 120px;
    border-radius: 15px;
    object-fit: cover;
    border: 3px solid #f1f5f9;
}

.no-image-placeholder {
    width: 120px;
    height: 120px;
    border-radius: 15px;
    background: #f8f9ff;
    border: 3px dashed #d1d5db;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: #9ca3af;
}

.no-image-placeholder i {
    font-size: 2rem;
    margin-bottom: 8px;
}

.image-upload-controls {
    flex: 1;
}

.form-group-modern {
    margin-bottom: 24px;
}

.form-label-modern {
    display: block;
    font-weight: 600;
    color: #374151;
    margin-bottom: 8px;
    font-size: 0.95rem;
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

.map-container {
    height: 300px;
    border-radius: 12px;
    margin-bottom: 12px;
    border: 2px solid #e5e7eb;
}

.form-actions {
    text-align: center;
    margin-top: 40px;
}

.alert-modern {
    border: none;
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 32px;
}

.alert-danger {
    background: #fef2f2;
    color: #991b1b;
    border-left: 4px solid #ef4444;
}

.alert-success {
    background: #f0fdf4;
    color: #166534;
    border-left: 4px solid #10b981;
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

.stats-list {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.stat-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid #f1f5f9;
}

.stat-item:last-child {
    border-bottom: none;
}

.stat-label {
    color: #64748b;
    font-weight: 500;
}

.stat-value {
    font-weight: 600;
    color: #1e293b;
}

.status-approved {
    color: #166534 !important;
}

.status-pending {
    color: #92400e !important;
}

.status-rejected {
    color: #991b1b !important;
}

@media (max-width: 991px) {
    .profile-title {
        font-size: 2.5rem;
    }
    
    .completion-header {
        flex-direction: column;
        text-align: center;
        gap: 20px;
    }
    
    .image-upload-section {
        flex-direction: column;
        text-align: center;
    }
}

@media (max-width: 768px) {
    .profile-header-section {
        text-align: center;
    }
    
    .completion-status-card {
        margin: 0;
        padding: 24px;
    }
    
    .profile-form-card {
        padding: 24px;
    }
    
    .form-actions {
        margin-top: 32px;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Image preview functionality
    const profileImageInput = document.getElementById('profile_image');
    const currentProfileImage = document.getElementById('current-profile-image');
    
    profileImageInput.addEventListener('change', function() {
        if (this.files && this.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                if (currentProfileImage.tagName === 'IMG') {
                    currentProfileImage.src = e.target.result;
                } else {
                    // Replace placeholder with image
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.style.width = '120px';
                    img.style.height = '120px';
                    img.style.borderRadius = '15px';
                    img.style.objectFit = 'cover';
                    img.style.border = '3px solid #f1f5f9';
                    img.id = 'current-profile-image';
                    currentProfileImage.parentNode.replaceChild(img, currentProfileImage);
                }
            };
            reader.readAsDataURL(this.files[0]);
        }
    });
    
    // Initialize Google Maps
    // const mapContainer = document.getElementById('map-container');
    // if (mapContainer && typeof google !== 'undefined') {
    //     const defaultLocation = { 
    //         lat: <?php echo !empty($latitude) ? $latitude : '6.9271'; ?>, 
    //         lng: <?php echo !empty($longitude) ? $longitude : '79.8612'; ?>
    //     };
        
    //     const map = new google.maps.Map(mapContainer, {
    //         center: defaultLocation,
    //         zoom: 12
    //     });
        
    //     const marker = new google.maps.Marker({
    //         position: defaultLocation,
    //         map: map,
    //         draggable: true
    //     });
        
    //     const latInput = document.getElementById('latitude');
    //     const lngInput = document.getElementById('longitude');
        
        // Update coordinates when marker is dragged
        // google.maps.event.addListener(marker, 'dragend', function() {
        //     const position = marker.getPosition();
        //     latInput.value = position.lat();
        //     lngInput.value = position.lng();
        // });
        
        // Update marker position if coordinates are manually entered
    //     function updateMarkerPosition() {
    //         if (latInput.value && lngInput.value) {
    //             const position = {
    //                 lat: parseFloat(latInput.value),
    //                 lng: parseFloat(lngInput.value)
    //             };
    //             marker.setPosition(position);
    //             map.setCenter(position);
    //         }
    //     }
        
    //     latInput.addEventListener('change', updateMarkerPosition);
    //     lngInput.addEventListener('change', updateMarkerPosition);
    // }
    
    // Password strength indicator
    const newPasswordInput = document.getElementById('new_password');
    if (newPasswordInput) {
        newPasswordInput.addEventListener('input', function() {
            const password = this.value;
            const strength = calculatePasswordStrength(password);
            // You can add visual feedback here
        });
    }
    
    function calculatePasswordStrength(password) {
        let strength = 0;
        if (password.length >= 6) strength++;
        if (password.match(/[a-z]/)) strength++;
        if (password.match(/[A-Z]/)) strength++;
        if (password.match(/[0-9]/)) strength++;
        if (password.match(/[^a-zA-Z0-9]/)) strength++;
        return strength;
    }
});
</script>

<!-- Google Maps API (replace YOUR_API_KEY with actual key) -->
<script src="https://maps.googleapis.com/maps/api/js?key=YOUR_API_KEY&libraries=places" async defer></script>

<?php
// Include footer
include_once '../includes/footer.php';

// Close database connection
$conn->close();
?>