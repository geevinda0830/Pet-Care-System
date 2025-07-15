
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

// Process login form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Include database connection
    require_once 'config/db_connect.php';
    
    // Get form data
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $user_type = $_POST['user_type'];
    
    // Validate form data
    $errors = array();
    
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    if (empty($password)) {
        $errors[] = "Password is required";
    }
    
    // If no errors, proceed with login
    if (empty($errors)) {
        // Determine table based on user type
        $table = "";
        if ($user_type === "admin") {
            $table = "admin";
        } elseif ($user_type === "pet_owner") {
            $table = "pet_owner";
        } elseif ($user_type === "pet_sitter") {
            $table = "pet_sitter";
        }
        
        // Prepare and execute the query
        $sql = "SELECT * FROM $table WHERE email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Verify password
            if (password_verify($password, $user['password'])) {
                // Check if pet sitter account is approved
                if ($user_type === "pet_sitter" && isset($user['approval_status']) && $user['approval_status'] !== 'Approved') {
                    if ($user['approval_status'] === 'Pending') {
                        $errors[] = "Your pet sitter account is pending approval by an administrator. Please check back later.";
                    } elseif ($user['approval_status'] === 'Rejected') {
                        $errors[] = "Your pet sitter application has been rejected. Please contact the administrator for more information.";
                    }
                } else {
                    // Password is correct, start a new session
                    session_start();
                    
                    // Store data in session variables
                    $_SESSION['user_id'] = $user['userID'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['user_type'] = $user_type;
                    
                    // Redirect to appropriate dashboard
                    if ($user_type === "admin") {
                        header("Location: admin/dashboard.php");
                    } elseif ($user_type === "pet_owner") {
                        header("Location: user/dashboard.php");
                    } elseif ($user_type === "pet_sitter") {
                        header("Location: pet_sitter/dashboard.php");
                    }
                    exit();
                }
            } else {
                // Password is not correct
                $errors[] = "Invalid email or password";
            }
        } else {
            // Email not found
            $errors[] = "Invalid email or password";
        }
        
        $stmt->close();
    }
    
    $conn->close();
}

// Include header
include_once 'includes/header.php';
?>

<!-- Modern Login Section -->
<section class="auth-section">
    <div class="auth-background">
        <div class="auth-particles"></div>
    </div>
    <div class="container-fluid">
        <div class="row min-vh-100">
            <!-- Left Side - Branding -->
            <div class="col-lg-6 d-none d-lg-flex auth-brand-side">
                <div class="auth-brand-content">
                    <div class="brand-logo">
                        <i class="fas fa-paw brand-icon"></i>
                        <h2>Pet Care & Sitting</h2>
                    </div>
                    <h1 class="brand-title">Welcome Back!</h1>
                    <p class="brand-subtitle">Sign in to access your pet care dashboard and manage your furry friends with ease.</p>
                    
                    <div class="brand-features">
                        <div class="feature-item">
                            <div class="feature-icon">
                                <i class="fas fa-shield-alt"></i>
                            </div>
                            <div class="feature-text">
                                <h6>Secure & Safe</h6>
                                <small>Your data is protected with enterprise-grade security</small>
                            </div>
                        </div>
                        <div class="feature-item">
                            <div class="feature-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="feature-text">
                                <h6>Trusted Community</h6>
                                <small>Join thousands of happy pet owners and sitters</small>
                            </div>
                        </div>
                        <div class="feature-item">
                            <div class="feature-icon">
                                <i class="fas fa-heart"></i>
                            </div>
                            <div class="feature-text">
                                <h6>Pet-Focused Care</h6>
                                <small>Everything designed with your pet's happiness in mind</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Right Side - Login Form -->
            <div class="col-lg-6 d-flex align-items-center justify-content-center">
                <div class="auth-form-container">
                    <div class="text-center mb-4 d-lg-none">
                        <i class="fas fa-paw text-primary" style="font-size: 3rem;"></i>
                        <h3 class="mt-2">Pet Care & Sitting</h3>
                    </div>
                    
                    <div class="auth-header">
                        <h3>Sign In</h3>
                        <p>Access your account to manage your pets</p>
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
                    
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="auth-form">
                        <div class="form-group-modern mb-4">
                            <label for="user_type" class="form-label">I am a</label>
                            <div class="user-type-selector">
                                <input type="radio" id="pet_owner" name="user_type" value="pet_owner" checked>
                                <label for="pet_owner" class="user-type-option">
                                    <i class="fas fa-heart"></i>
                                    <span>Pet Owner</span>
                                </label>
                                
                                <input type="radio" id="pet_sitter" name="user_type" value="pet_sitter">
                                <label for="pet_sitter" class="user-type-option">
                                    <i class="fas fa-hands-helping"></i>
                                    <span>Pet Sitter</span>
                                </label>
                                
                                <input type="radio" id="admin" name="user_type" value="admin">
                                <label for="admin" class="user-type-option">
                                    <i class="fas fa-user-shield"></i>
                                    <span>Admin</span>
                                </label>
                            </div>
                        </div>
                        
                        <div class="form-group-modern mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <div class="input-group-modern">
                                <div class="input-icon">
                                    <i class="fas fa-envelope"></i>
                                </div>
                                <input type="email" class="form-control form-control-modern" id="email" name="email" value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>" placeholder="Enter your email" required>
                            </div>
                        </div>
                        
                        <div class="form-group-modern mb-4">
                            <label for="password" class="form-label">Password</label>
                            <div class="input-group-modern">
                                <div class="input-icon">
                                    <i class="fas fa-lock"></i>
                                </div>
                                <input type="password" class="form-control form-control-modern" id="password" name="password" placeholder="Enter your password" required>
                                <button type="button" class="password-toggle" data-toggle="#password">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="remember" name="remember">
                                <label class="form-check-label" for="remember">Remember me</label>
                            </div>
                            <a href="forgot_password.php" class="auth-link">Forgot Password?</a>
                        </div>
                        
                        <button type="submit" class="btn btn-primary-gradient w-100 btn-lg mb-4">
                            Sign In
                            <i class="fas fa-arrow-right ms-2"></i>
                        </button>
                    </form>
                    
                    <div class="auth-footer">
                        <p>Don't have an account? <a href="register.php" class="auth-link-primary">Create Account</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
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
    max-width: 450px;
    width: 100%;
    padding: 40px;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    border-radius: 24px;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
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
    gap: 8px;
    margin-top: 8px;
}

.user-type-selector input[type="radio"] {
    display: none;
}

.user-type-option {
    flex: 1;
    padding: 12px 8px;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
    background: white;
}

.user-type-option i {
    display: block;
    font-size: 1.2rem;
    margin-bottom: 4px;
    color: #9ca3af;
}

.user-type-option span {
    font-size: 0.8rem;
    font-weight: 500;
    color: #6b7280;
}

.user-type-selector input[type="radio"]:checked + .user-type-option {
    border-color: #667eea;
    background: linear-gradient(135deg, #667eea, #764ba2);
}

.user-type-selector input[type="radio"]:checked + .user-type-option i,
.user-type-selector input[type="radio"]:checked + .user-type-option span {
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
}

.form-control-modern:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    background: white;
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
}
</style>

<?php
// Include footer
include_once 'includes/footer.php';
?>