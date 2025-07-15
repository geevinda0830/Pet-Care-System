<?php

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    // Redirect to appropriate dashboard based on user type
    if ($_SESSION['user_type'] === 'admin') {
        header("Location: admin/dashboard.php");
    } elseif ($_SESSION['user_type'] === 'pet_owner') {
        header("Location: user/dashboard.php");
    } elseif ($_SESSION['user_type'] === 'pet_sitter') {
        header("Location: pet_sitter/dashboard.php");
    }
    exit();
}

// Include database connection
require_once 'config/db_connect.php';

// Initialize variables
$errors = array();
$username = $email = $fullName = $contact = $address = $gender = $user_type = '';

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $fullName = trim($_POST['fullName']);
    $contact = trim($_POST['contact']);
    $address = trim($_POST['address']);
    $gender = $_POST['gender'];
    $user_type = $_POST['user_type'];
    
    // Validate form data
    if (empty($username)) {
        $errors[] = "Username is required";
    }
    
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    if (empty($password)) {
        $errors[] = "Password is required";
    } elseif (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters long";
    }
    
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }
    
    if (empty($fullName)) {
        $errors[] = "Full name is required";
    }
    
    if (empty($contact)) {
        $errors[] = "Contact number is required";
    }
    
    if (empty($address)) {
        $errors[] = "Address is required";
    }
    
    if (empty($gender)) {
        $errors[] = "Gender is required";
    }
    
    if (empty($user_type) || !in_array($user_type, ['pet_owner', 'pet_sitter'])) {
        $errors[] = "Invalid user type selected";
    }
    
    // Check if username or email already exists
    if (empty($errors)) {
        $table = ($user_type === 'pet_sitter') ? 'pet_sitter' : 'pet_owner';
        
        // Check for existing username/email
        $check_sql = "SELECT * FROM $table WHERE username = ? OR email = ?";
        $check_stmt = $conn->prepare($check_sql);
        if (!$check_stmt) {
            $errors[] = "Database error: " . $conn->error;
        } else {
            $check_stmt->bind_param("ss", $username, $email);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $existing_user = $check_result->fetch_assoc();
                if ($existing_user['username'] === $username) {
                    $errors[] = "Username already exists";
                }
                if ($existing_user['email'] === $email) {
                    $errors[] = "Email already exists";
                }
            }
            
            $check_stmt->close();
        }
    }
    
    // If no errors, proceed with registration
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        try {
            if ($user_type === 'pet_sitter') {
                // Insert into pet_sitter table with all fields
                $insert_sql = "INSERT INTO pet_sitter (username, password, fullName, email, contact, address, gender, service, qualifications, experience, specialization, price, latitude, longitude, approval_status) VALUES (?, ?, ?, ?, ?, ?, ?, '', '', '', '', 0.00, NULL, NULL, 'Pending')";
                $insert_stmt = $conn->prepare($insert_sql);
                if (!$insert_stmt) {
                    throw new Exception("Prepare failed: " . $conn->error);
                }
                $insert_stmt->bind_param("sssssss", $username, $hashed_password, $fullName, $email, $contact, $address, $gender);
            } else {
                // Insert into pet_owner table
                $insert_sql = "INSERT INTO pet_owner (username, password, fullName, email, contact, address, gender) VALUES (?, ?, ?, ?, ?, ?, ?)";
                $insert_stmt = $conn->prepare($insert_sql);
                if (!$insert_stmt) {
                    throw new Exception("Prepare failed: " . $conn->error);
                }
                $insert_stmt->bind_param("sssssss", $username, $hashed_password, $fullName, $email, $contact, $address, $gender);
            }
            
            if ($insert_stmt->execute()) {
                if ($user_type === 'pet_sitter') {
                    $_SESSION['success_message'] = "Registration successful! Your pet sitter account is pending approval by an administrator. You will be notified once approved.";
                } else {
                    $_SESSION['success_message'] = "Registration successful! You can now log in to your account.";
                }
                header("Location: login.php");
                exit();
            } else {
                throw new Exception("Execute failed: " . $insert_stmt->error);
            }
            
        } catch (Exception $e) {
            $errors[] = "Registration failed: " . $e->getMessage();
            error_log("Registration error: " . $e->getMessage());
        }
        
        if (isset($insert_stmt)) {
            $insert_stmt->close();
        }
    }
}

// Include header
include_once 'includes/header.php';
?>

<!-- Modern Registration Section -->
<section class="auth-section">
    <div class="auth-background">
        <div class="auth-particles"></div>
    </div>
    <div class="container-fluid">
        <div class="row min-vh-100">
            <!-- Left Side - Branding -->
            <div class="col-lg-5 d-none d-lg-flex auth-brand-side">
                <div class="auth-brand-content">
                    <div class="brand-logo">
                        <i class="fas fa-paw brand-icon"></i>
                        <h2>Join Our Community!</h2>
                    </div>
                    <h1 class="brand-title">Start Your Pet Care Journey</h1>
                    <p class="brand-subtitle">Create your account and join thousands of happy pet owners and trusted pet sitters in our growing community.</p>
                    
                    <div class="brand-features">
                        <div class="feature-item">
                            <div class="feature-icon">
                                <i class="fas fa-user-friends"></i>
                            </div>
                            <div class="feature-text">
                                <h6>Trusted Community</h6>
                                <small>Join 10,000+ verified pet owners and sitters</small>
                            </div>
                        </div>
                        <div class="feature-item">
                            <div class="feature-icon">
                                <i class="fas fa-shield-alt"></i>
                            </div>
                            <div class="feature-text">
                                <h6>Safe & Secure</h6>
                                <small>Your information is protected with advanced security</small>
                            </div>
                        </div>
                        <div class="feature-item">
                            <div class="feature-icon">
                                <i class="fas fa-heart"></i>
                            </div>
                            <div class="feature-text">
                                <h6>Pet-Focused</h6>
                                <small>Everything designed with your pet's happiness in mind</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Right Side - Registration Form -->
            <div class="col-lg-7 d-flex align-items-center justify-content-center">
                <div class="auth-form-container register-form">
                    <div class="text-center mb-4 d-lg-none">
                        <i class="fas fa-paw text-primary" style="font-size: 3rem;"></i>
                        <h3 class="mt-2">Pet Care & Sitting</h3>
                    </div>
                    
                    <div class="auth-header">
                        <h3>Create Account</h3>
                        <p>Join our pet care community today</p>
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
                    
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="auth-form">
                        <div class="form-group-modern mb-4">
                            <label for="user_type" class="form-label">I want to register as</label>
                            <div class="user-type-selector">
                                <input type="radio" id="pet_owner" name="user_type" value="pet_owner" <?php echo ($user_type !== 'pet_sitter') ? 'checked' : ''; ?>>
                                <label for="pet_owner" class="user-type-option">
                                    <i class="fas fa-heart"></i>
                                    <span class="type-title">Pet Owner</span>
                                    <small>Find trusted pet sitters</small>
                                </label>
                                
                                <input type="radio" id="pet_sitter" name="user_type" value="pet_sitter" <?php echo ($user_type === 'pet_sitter') ? 'checked' : ''; ?>>
                                <label for="pet_sitter" class="user-type-option">
                                    <i class="fas fa-hands-helping"></i>
                                    <span class="type-title">Pet Sitter</span>
                                    <small>Provide pet care services</small>
                                </label>
                            </div>
                        </div>
                        
                        <!-- Pet Sitter Notice -->
                        <div id="pet-sitter-notice" class="alert alert-info alert-modern" style="display: none;">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Note:</strong> Pet sitter accounts require admin approval before activation. You'll be notified once approved.
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group-modern mb-3">
                                    <label for="username" class="form-label">Username</label>
                                    <div class="input-group-modern">
                                        <div class="input-icon">
                                            <i class="fas fa-user"></i>
                                        </div>
                                        <input type="text" class="form-control form-control-modern" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" placeholder="Choose a username" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group-modern mb-3">
                                    <label for="email" class="form-label">Email Address</label>
                                    <div class="input-group-modern">
                                        <div class="input-icon">
                                            <i class="fas fa-envelope"></i>
                                        </div>
                                        <input type="email" class="form-control form-control-modern" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" placeholder="Enter your email" required>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group-modern mb-3">
                                    <label for="password" class="form-label">Password</label>
                                    <div class="input-group-modern">
                                        <div class="input-icon">
                                            <i class="fas fa-lock"></i>
                                        </div>
                                        <input type="password" class="form-control form-control-modern" id="password" name="password" placeholder="Create a password" required>
                                        <button type="button" class="password-toggle" data-toggle="#password">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <small class="form-text">Password must be at least 6 characters long</small>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group-modern mb-3">
                                    <label for="confirm_password" class="form-label">Confirm Password</label>
                                    <div class="input-group-modern">
                                        <div class="input-icon">
                                            <i class="fas fa-lock"></i>
                                        </div>
                                        <input type="password" class="form-control form-control-modern" id="confirm_password" name="confirm_password" placeholder="Confirm your password" required>
                                        <button type="button" class="password-toggle" data-toggle="#confirm_password">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group-modern mb-3">
                            <label for="fullName" class="form-label">Full Name</label>
                            <div class="input-group-modern">
                                <div class="input-icon">
                                    <i class="fas fa-id-card"></i>
                                </div>
                                <input type="text" class="form-control form-control-modern" id="fullName" name="fullName" value="<?php echo htmlspecialchars($fullName); ?>" placeholder="Enter your full name" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group-modern mb-3">
                                    <label for="contact" class="form-label">Contact Number</label>
                                    <div class="input-group-modern">
                                        <div class="input-icon">
                                            <i class="fas fa-phone"></i>
                                        </div>
                                        <input type="text" class="form-control form-control-modern" id="contact" name="contact" value="<?php echo htmlspecialchars($contact); ?>" placeholder="Enter your phone number" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group-modern mb-3">
                                    <label for="gender" class="form-label">Gender</label>
                                    <div class="input-group-modern">
                                        <div class="input-icon">
                                            <i class="fas fa-venus-mars"></i>
                                        </div>
                                        <select class="form-control form-control-modern" id="gender" name="gender" required>
                                            <option value="">Select Gender</option>
                                            <option value="Male" <?php echo ($gender === 'Male') ? 'selected' : ''; ?>>Male</option>
                                            <option value="Female" <?php echo ($gender === 'Female') ? 'selected' : ''; ?>>Female</option>
                                            <option value="Other" <?php echo ($gender === 'Other') ? 'selected' : ''; ?>>Other</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group-modern mb-4">
                            <label for="address" class="form-label">Address</label>
                            <div class="input-group-modern">
                                <div class="input-icon">
                                    <i class="fas fa-map-marker-alt"></i>
                                </div>
                                <textarea class="form-control form-control-modern" id="address" name="address" rows="3" placeholder="Enter your address" required><?php echo htmlspecialchars($address); ?></textarea>
                            </div>
                        </div>
                        
                        <div class="form-check mb-4">
                            <input type="checkbox" class="form-check-input" id="terms" name="terms" required>
                            <label class="form-check-label" for="terms">
                                I agree to the <a href="terms.php" target="_blank" class="auth-link">Terms and Conditions</a> and <a href="privacy.php" target="_blank" class="auth-link">Privacy Policy</a>
                            </label>
                        </div>
                        
                        <button type="submit" class="btn btn-primary-gradient w-100 btn-lg mb-4">
                            Create Account
                            <i class="fas fa-arrow-right ms-2"></i>
                        </button>
                    </form>
                    
                    <div class="auth-footer">
                        <p>Already have an account? <a href="login.php" class="auth-link-primary">Sign In</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
/* Include all the existing styles from the original file */
.auth-section {
    min-height: 100vh;
    position: relative;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.auth-background {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    overflow: hidden;
}

.auth-particles {
    position: absolute;
    width: 100%;
    height: 100%;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="20" cy="20" r="2" fill="rgba(255,255,255,0.1)"/><circle cx="80" cy="40" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="40" cy="80" r="1.5" fill="rgba(255,255,255,0.1)"/></svg>');
    animation: float 20s infinite linear;
}

.auth-brand-side {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(20px);
    border-right: 1px solid rgba(255, 255, 255, 0.1);
}

.auth-brand-content {
    padding: 60px;
    color: white;
    width: 100%;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.brand-logo {
    display: flex;
    align-items: center;
    margin-bottom: 40px;
}

.brand-icon {
    font-size: 2.5rem;
    margin-right: 16px;
    color: white;
}

.brand-logo h2 {
    color: white;
    margin: 0;
    font-weight: 700;
}

.brand-title {
    font-size: 3rem;
    font-weight: 800;
    margin-bottom: 16px;
    line-height: 1.2;
}

.brand-subtitle {
    font-size: 1.2rem;
    opacity: 0.9;
    margin-bottom: 40px;
    line-height: 1.6;
}

.brand-features {
    display: flex;
    flex-direction: column;
    gap: 24px;
}

.feature-item {
    display: flex;
    align-items: center;
    gap: 16px;
}

.feature-icon {
    width: 50px;
    height: 50px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    flex-shrink: 0;
}

.feature-text h6 {
    color: white;
    margin-bottom: 4px;
    font-weight: 600;
}

.feature-text small {
    color: rgba(255, 255, 255, 0.8);
}

.auth-form-container {
    max-width: 500px;
    width: 100%;
    padding: 40px;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    border-radius: 24px;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.register-form {
    max-width: 600px;
}

.auth-header {
    text-align: center;
    margin-bottom: 32px;
}

.auth-header h3 {
    font-size: 2rem;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 8px;
}

.auth-header p {
    color: #64748b;
    font-size: 1rem;
}

.alert-modern {
    border: none;
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 24px;
}

.form-group-modern {
    margin-bottom: 20px;
}

.form-label {
    font-weight: 600;
    color: #374151;
    margin-bottom: 8px;
    display: block;
}

.user-type-selector {
    display: flex;
    gap: 12px;
    margin-top: 8px;
}

.user-type-selector input[type="radio"] {
    display: none;
}

.user-type-option {
    flex: 1;
    padding: 20px 16px;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
    background: white;
}

.user-type-option i {
    display: block;
    font-size: 1.5rem;
    margin-bottom: 8px;
    color: #9ca3af;
}

.type-title {
    display: block;
    font-weight: 600;
    color: #6b7280;
    margin-bottom: 4px;
}

.user-type-option small {
    color: #9ca3af;
    font-size: 0.8rem;
}

.user-type-selector input[type="radio"]:checked + .user-type-option {
    border-color: #667eea;
    background: linear-gradient(135deg, #667eea, #764ba2);
}

.user-type-selector input[type="radio"]:checked + .user-type-option i,
.user-type-selector input[type="radio"]:checked + .user-type-option .type-title,
.user-type-selector input[type="radio"]:checked + .user-type-option small {
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
    padding: 16px 16px 16px 48px;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    font-size: 1rem;
    transition: all 0.3s ease;
    background: rgba(255, 255, 255, 0.8);
    width: 100%;
}

.form-control-modern:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    background: white;
}

.form-control-modern[rows] {
    padding-top: 16px;
    resize: vertical;
}

.password-toggle {
    position: absolute;
    right: 16px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: #9ca3af;
    cursor: pointer;
}

.form-text {
    color: #6b7280;
    font-size: 0.8rem;
    margin-top: 4px;
}

.auth-link {
    color: #667eea;
    text-decoration: none;
    font-weight: 500;
}

.auth-link:hover {
    color: #764ba2;
}

.auth-link-primary {
    color: #667eea;
    text-decoration: none;
    font-weight: 600;
}

.auth-link-primary:hover {
    color: #764ba2;
}

.auth-footer {
    text-align: center;
    color: #64748b;
}

@media (max-width: 991px) {
    .auth-form-container {
        margin: 20px;
        padding: 32px 24px;
    }
    
    .brand-title {
        font-size: 2rem;
    }
    
    .user-type-selector {
        flex-direction: column;
        gap: 8px;
    }
}

@media (max-width: 768px) {
    .user-type-option {
        padding: 16px 12px;
    }
    
    .type-title {
        font-size: 0.9rem;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // User type selector functionality
    const userTypeInputs = document.querySelectorAll('input[name="user_type"]');
    const petSitterNotice = document.getElementById('pet-sitter-notice');
    
    userTypeInputs.forEach(input => {
        input.addEventListener('change', function() {
            if (this.value === 'pet_sitter') {
                petSitterNotice.style.display = 'block';
            } else {
                petSitterNotice.style.display = 'none';
            }
        });
    });
    
    // Initialize notice visibility
    const checkedInput = document.querySelector('input[name="user_type"]:checked');
    if (checkedInput && checkedInput.value === 'pet_sitter') {
        petSitterNotice.style.display = 'block';
    }
    
    // Password toggle functionality
    const passwordToggles = document.querySelectorAll('.password-toggle');
    passwordToggles.forEach(toggle => {
        toggle.addEventListener('click', function() {
            const target = document.querySelector(this.getAttribute('data-toggle'));
            const icon = this.querySelector('i');
            
            if (target.type === 'password') {
                target.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                target.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
    });
    
    // Form validation
    const form = document.querySelector('form');
    form.addEventListener('submit', function(e) {
        const password = document.getElementById('password').value;
        const confirmPassword = document.getElementById('confirm_password').value;
        
        if (password !== confirmPassword) {
            e.preventDefault();
            alert('Passwords do not match!');
            return false;
        }
        
        if (password.length < 6) {
            e.preventDefault();
            alert('Password must be at least 6 characters long!');
            return false;
        }
    });
});
</script>

<?php
// Include footer
include_once 'includes/footer.php';

// Close database connection
$conn->close();
?>