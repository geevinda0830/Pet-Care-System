<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    // Redirect to appropriate dashboard
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
    
    // If admin login, use direct query (temporary fix)
    if ($user_type === "admin") {
        $sql = "SELECT * FROM admin WHERE (email = '$email' OR username = '$email')";
        $result = $conn->query($sql);
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            // For admin, accept any password that matches "admin123"
            if ($password === "admin123") {
                $_SESSION['user_id'] = $user['userID'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['user_type'] = 'admin';
                header("Location: admin/dashboard.php");
                exit();
            }
        }
        $error = "Invalid admin credentials";
    } else {
        // Regular user login (pet owner or pet sitter)
        $table = ($user_type === "pet_owner") ? "pet_owner" : "pet_sitter";
        
        $sql = "SELECT * FROM $table WHERE email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['userID'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['user_type'] = $user_type;
                
                if ($user_type === "pet_owner") {
                    header("Location: user/dashboard.php");
                } else {
                    header("Location: pet_sitter/dashboard.php");
                }
                exit();
            }
        }
        $error = "Invalid email or password";
        // Debug
echo "User found: "; print_r($user); exit;
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
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <?php echo $error; ?>
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
                <input type="text" class="form-control" id="email" name="email" value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>" required>
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