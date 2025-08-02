<?php
// Complete Login Page with Google OAuth
// File: login.php

session_start();
require_once 'config/db_connect.php';
require_once 'config/google_config.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    $user_type = $_SESSION['user_type'] ?? '';
    if ($user_type === 'admin') {
        header("Location: admin/dashboard.php");
    } elseif ($user_type === 'pet_owner') {
        header("Location: user/dashboard.php");
    } elseif ($user_type === 'pet_sitter') {
        header("Location: pet_sitter/dashboard.php");
    }
    exit();
}

$errors = [];
$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';

// Clear messages after displaying
unset($_SESSION['success_message'], $_SESSION['error_message']);

// Initialize Google Auth
$googleAuth = new GoogleAuth();
$google_login_url = $googleAuth->getLoginUrl();

// Handle regular login
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $user_type = $_POST['user_type'];
    
    if (empty($email) || empty($password) || empty($user_type)) {
        $errors[] = "All fields are required";
    } else {
        // Determine table based on user type
        $table = '';
        switch ($user_type) {
            case 'admin':
                $table = 'admin';
                break;
            case 'pet_owner':
                $table = 'pet_owner';
                break;
            case 'pet_sitter':
                $table = 'pet_sitter';
                break;
            default:
                $errors[] = "Invalid user type";
        }
        
        if (empty($errors)) {
            $stmt = $conn->prepare("SELECT * FROM $table WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($user = $result->fetch_assoc()) {
                // Check if account is approved (for pet_sitter)
                if ($user_type === 'pet_sitter' && isset($user['approval_status'])) {
                    if ($user['approval_status'] === 'Pending') {
                        $errors[] = "Your pet sitter application is still pending approval. Please check back later.";
                    } elseif ($user['approval_status'] === 'Rejected') {
                        $errors[] = "Your pet sitter application has been rejected. Please contact the administrator.";
                    }
                }
                
                if (empty($errors)) {
                    // Verify password
                    if (password_verify($password, $user['password'])) {
                        // Set session variables
                        $_SESSION['user_id'] = $user['userID'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['email'] = $user['email'];
                        $_SESSION['user_type'] = $user_type;
                        $_SESSION['user_name'] = $user['fullName'] ?? $user['username'];
                        
                        // Redirect to appropriate dashboard
                        if ($user_type === "admin") {
                            header("Location: admin/dashboard.php");
                        } elseif ($user_type === "pet_owner") {
                            header("Location: user/dashboard.php");
                        } elseif ($user_type === "pet_sitter") {
                            header("Location: pet_sitter/dashboard.php");
                        }
                        exit();
                    } else {
                        $errors[] = "Invalid email or password";
                    }
                }
            } else {
                $errors[] = "Invalid email or password";
            }
            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Pet Care & Sitting System</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .auth-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            width: 100%;
            max-width: 900px;
            min-height: 600px;
            display: flex;
        }
        
        .auth-left {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 3rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .auth-left::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="50" cy="50" r="2" fill="rgba(255,255,255,0.1)"/></svg>') repeat;
            animation: float 20s linear infinite;
        }
        
        @keyframes float {
            0% { transform: translate(-50%, -50%) rotate(0deg); }
            100% { transform: translate(-50%, -50%) rotate(360deg); }
        }
        
        .auth-brand {
            position: relative;
            z-index: 2;
        }
        
        .auth-brand i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.9;
        }
        
        .auth-brand h2 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }
        
        .auth-brand p {
            font-size: 1.1rem;
            opacity: 0.8;
            line-height: 1.6;
        }
        
        .auth-right {
            padding: 3rem;
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .auth-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .auth-header h3 {
            color: #333;
            font-size: 2rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .auth-header p {
            color: #666;
            font-size: 1rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            color: #333;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }
        
        .form-control {
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            padding: 0.75rem 1rem;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .user-type-select {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .user-type-option {
            flex: 1;
            position: relative;
        }
        
        .user-type-option input[type="radio"] {
            display: none;
        }
        
        .user-type-option label {
            display: block;
            background: #f8f9fa;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            padding: 0.75rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
        }
        
        .user-type-option input[type="radio"]:checked + label {
            background: #667eea;
            border-color: #667eea;
            color: white;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            padding: 0.75rem 2rem;
            font-size: 1rem;
            font-weight: 600;
            transition: all 0.3s ease;
            width: 100%;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        
        .google-btn {
            background: white;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            padding: 0.75rem;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            text-decoration: none;
            color: #333;
            font-weight: 500;
            transition: all 0.3s ease;
            margin-bottom: 1rem;
        }
        
        .google-btn:hover {
            background: #f8f9fa;
            border-color: #667eea;
            color: #333;
            text-decoration: none;
        }
        
        .google-btn img {
            width: 20px;
            height: 20px;
        }
        
        .divider {
            text-align: center;
            margin: 1.5rem 0;
            position: relative;
        }
        
        .divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: #e1e5e9;
        }
        
        .divider span {
            background: white;
            padding: 0 1rem;
            color: #666;
            font-size: 0.9rem;
        }
        
        .alert {
            border-radius: 10px;
            border: none;
            margin-bottom: 1.5rem;
        }
        
        .register-link {
            text-align: center;
            margin-top: 1.5rem;
        }
        
        .register-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }
        
        .register-link a:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 768px) {
            .auth-container {
                flex-direction: column;
                margin: 1rem;
                min-height: auto;
            }
            
            .auth-left {
                padding: 2rem;
            }
            
            .auth-right {
                padding: 2rem;
            }
            
            .auth-brand h2 {
                font-size: 2rem;
            }
            
            .user-type-select {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <!-- Left Side - Branding -->
        <div class="auth-left col-lg-5 d-none d-lg-flex">
            <div class="auth-brand">
                <i class="fas fa-paw"></i>
                <h2>Pet Care & Sitting</h2>
                <p>Your trusted platform for professional pet care services. Connect with certified pet sitters and provide the best care for your furry friends.</p>
            </div>
        </div>
        
        <!-- Right Side - Login Form -->
        <div class="auth-right col-lg-7">
            <!-- Mobile Header -->
            <div class="d-lg-none text-center mb-4">
                <i class="fas fa-paw text-primary" style="font-size: 3rem;"></i>
                <h3 class="mt-2">Pet Care & Sitting</h3>
            </div>
            
            <div class="auth-header">
                <h3>Welcome Back</h3>
                <p>Sign in to your account</p>
            </div>
            
            <!-- Success Message -->
            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>
            
            <!-- Error Message -->
            <?php if ($error_message): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>
            
            <!-- Validation Errors -->
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <ul class="mb-0 list-unstyled">
                        <?php foreach($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <!-- Google Login (Pet Owners Only) -->
            <a href="<?php echo $google_login_url; ?>" class="google-btn">
                <img src="https://developers.google.com/identity/images/g-logo.png" alt="Google">
                Continue with Google (Pet Owners)
            </a>
            
            <div class="divider">
                <span>OR</span>
            </div>
            
            <!-- Regular Login Form -->
            <form method="POST" action="">
                <div class="form-group">
                    <label class="form-label">I am a</label>
                    <div class="user-type-select">
                        <div class="user-type-option">
                            <input type="radio" name="user_type" value="pet_owner" id="pet_owner" <?php echo (isset($_POST['user_type']) && $_POST['user_type'] == 'pet_owner') ? 'checked' : ''; ?>>
                            <label for="pet_owner">
                                <i class="fas fa-heart me-1"></i>
                                Pet Owner
                            </label>
                        </div>
                        <div class="user-type-option">
                            <input type="radio" name="user_type" value="pet_sitter" id="pet_sitter" <?php echo (isset($_POST['user_type']) && $_POST['user_type'] == 'pet_sitter') ? 'checked' : ''; ?>>
                            <label for="pet_sitter">
                                <i class="fas fa-user-friends me-1"></i>
                                Pet Sitter
                            </label>
                        </div>
                        <div class="user-type-option">
                            <input type="radio" name="user_type" value="admin" id="admin" <?php echo (isset($_POST['user_type']) && $_POST['user_type'] == 'admin') ? 'checked' : ''; ?>>
                            <label for="admin">
                                <i class="fas fa-user-shield me-1"></i>
                                Admin
                            </label>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" class="form-control" id="email" name="email" 
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                           placeholder="Enter your email address" required>
                </div>
                
                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" 
                           placeholder="Enter your password" required>
                </div>
                
                <button type="submit" name="login" class="btn btn-primary">
                    <i class="fas fa-sign-in-alt me-2"></i>
                    Sign In
                </button>
            </form>
            
            <div class="register-link">
                <p>Don't have an account? <a href="register.php">Create one here</a></p>
                <p><a href="forgot_password.php">Forgot your password?</a></p>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Auto-select pet_owner by default
        document.addEventListener('DOMContentLoaded', function() {
            const petOwnerRadio = document.getElementById('pet_owner');
            if (!document.querySelector('input[name="user_type"]:checked')) {
                petOwnerRadio.checked = true;
            }
        });
        
        // Add some interactive feedback
        document.querySelectorAll('.form-control').forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.style.transform = 'scale(1.02)';
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.style.transform = 'scale(1)';
            });
        });
    </script>
</body>
</html>