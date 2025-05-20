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

// Initialize variables
$user_type = $username = $email = $fullName = $contact = $address = '';
$errors = array();

// Process registration form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Include database connection
    require_once 'config/db_connect.php';
    
    // Get form data
    $user_type = trim($_POST['user_type']);
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $fullName = trim($_POST['fullName']);
    $contact = trim($_POST['contact']);
    $address = trim($_POST['address']);
    $gender = $_POST['gender'];
    
    // Validate form data
    if (empty($username)) {
        $errors[] = "Username is required";
    } elseif (!preg_match("/^[a-zA-Z0-9_]+$/", $username)) {
        $errors[] = "Username can only contain letters, numbers, and underscores";
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
    
    // Check if username or email already exists
    if (empty($errors)) {
        // Determine table based on user type
        $table = "";
        if ($user_type === "pet_owner") {
            $table = "pet_owner";
        } elseif ($user_type === "pet_sitter") {
            $table = "pet_sitter";
        }
        
        // Check username
        $sql = "SELECT * FROM $table WHERE username = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $errors[] = "Username already exists";
        }
        
        // Check email
        $sql = "SELECT * FROM $table WHERE email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $errors[] = "Email already exists";
        }
        
        $stmt->close();
    }
    
    // If no errors, proceed with registration
    if (empty($errors)) {
        // Hash the password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Determine table and fields based on user type
        if ($user_type === "pet_owner") {
            $sql = "INSERT INTO pet_owner (username, password, fullName, email, contact, address, gender) VALUES (?, ?, ?, ?, ?, ?, ?)";
        } elseif ($user_type === "pet_sitter") {
            // For pet sitters, additional fields would be set to default values initially
            $sql = "INSERT INTO pet_sitter (username, password, fullName, email, contact, address, gender, service, qualifications, experience, specialization, price) VALUES (?, ?, ?, ?, ?, ?, ?, '', '', '', '', 0)";
        }
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssss", $username, $hashed_password, $fullName, $email, $contact, $address, $gender);
        
        if ($stmt->execute()) {
            // Registration successful
            $_SESSION['success_message'] = "Registration successful! You can now login.";
            header("Location: login.php");
            exit();
        } else {
            $errors[] = "Registration failed: " . $conn->error;
        }
        
        $stmt->close();
    }
    
    $conn->close();
}

// Include header
include_once 'includes/header.php';
?>

<div class="container">
    <div class="form-container my-5">
        <h2 class="text-center mb-4">Create an Account</h2>
        
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
                <label for="user_type" class="form-label">Register As</label>
                <select class="form-select" id="user_type" name="user_type" required>
                    <option value="pet_owner" <?php echo ($user_type === 'pet_owner') ? 'selected' : ''; ?>>Pet Owner</option>
                    <option value="pet_sitter" <?php echo ($user_type === 'pet_sitter') ? 'selected' : ''; ?>>Pet Sitter</option>
                </select>
                <div class="form-text">Select whether you want to register as a pet owner or a pet sitter.</div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" required>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                    <div class="form-text">Password must be at least 6 characters long.</div>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="confirm_password" class="form-label">Confirm Password</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="fullName" class="form-label">Full Name</label>
                <input type="text" class="form-control" id="fullName" name="fullName" value="<?php echo htmlspecialchars($fullName); ?>" required>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="contact" class="form-label">Contact Number</label>
                    <input type="text" class="form-control" id="contact" name="contact" value="<?php echo htmlspecialchars($contact); ?>" required>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="gender" class="form-label">Gender</label>
                    <select class="form-select" id="gender" name="gender" required>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="address" class="form-label">Address</label>
                <textarea class="form-control" id="address" name="address" rows="3" required><?php echo htmlspecialchars($address); ?></textarea>
            </div>
            
            <div class="mb-3 form-check">
                <input type="checkbox" class="form-check-input" id="terms" name="terms" required>
                <label class="form-check-label" for="terms">I agree to the <a href="terms.php">Terms and Conditions</a></label>
            </div>
            
            <div class="text-center">
                <button type="submit" class="btn btn-primary btn-lg">Register</button>
            </div>
        </form>
        
        <div class="text-center mt-3">
            <p>Already have an account? <a href="login.php">Login</a></p>
        </div>
    </div>
</div>

<?php
// Include footer
include_once 'includes/footer.php';
?>