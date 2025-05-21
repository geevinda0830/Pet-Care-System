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

<div class="container">
    <div class="auth-container">
        <div class="logo">
            <i class="fas fa-paw"></i>
            <h2>Login to Your Account</h2>
        </div>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach($errors as $error): ?>
                        <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <div class="mb-3">
                <label for="user_type" class="form-label">Login As</label>
                <select class="form-select" id="user_type" name="user_type" required>
                    <option value="pet_owner">Pet Owner</option>
                    <option value="pet_sitter">Pet Sitter</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            
            <div class="mb-3">
                <label for="email" class="form-label">Email Address</label>
                <input type="email" class="form-control" id="email" name="email" value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>" required>
            </div>
            
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            
            <div class="mb-3 form-check">
                <input type="checkbox" class="form-check-input" id="remember" name="remember">
                <label class="form-check-label" for="remember">Remember me</label>
            </div>
            
            <button type="submit" class="btn btn-primary w-100">Login</button>
        </form>
        
        <div class="auth-links mt-3">
            <p>Don't have an account? <a href="register.php">Register</a></p>
            <p><a href="forgot_password.php">Forgot Password?</a></p>
        </div>
    </div>
</div>

<?php
// Include footer
include_once 'includes/footer.php';
?>