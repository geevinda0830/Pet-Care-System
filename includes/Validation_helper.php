<?php
class ValidationHelper {
    
    public static function sanitizeInput($input) {
        return htmlspecialchars(strip_tags(trim($input)));
    }
    
    public static function validateEmail($email) {
        $email = trim($email);
        if (empty($email)) {
            return ['valid' => false, 'message' => 'Email is required'];
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['valid' => false, 'message' => 'Please enter a valid email address'];
        }
        
        return ['valid' => true, 'message' => ''];
    }
    
    // PASSWORD VALIDATION FOR SIGNUP - WITH STRENGTH REQUIREMENTS
    public static function validatePasswordForSignup($password) {
        if (empty($password)) {
            return ['valid' => false, 'message' => 'Password is required'];
        }
        
        $minLength = strlen($password) >= 6;
        $hasLetter = preg_match('/[a-zA-Z]/', $password);
        $hasNumberOrSpecial = preg_match('/[\d!@#$%^&*(),.?":{}|<>]/', $password);
        
        if (!$minLength) {
            return ['valid' => false, 'message' => 'Password must be at least 6 characters long'];
        }
        
        if (!$hasLetter) {
            return ['valid' => false, 'message' => 'Password must contain at least one letter'];
        }
        
        if (!$hasNumberOrSpecial) {
            return ['valid' => false, 'message' => 'Password must contain at least one number or special character'];
        }
        
        return ['valid' => true, 'message' => 'Password is strong'];
    }
    
    // PASSWORD VALIDATION FOR LOGIN - NO STRENGTH REQUIREMENTS
    public static function validatePasswordForLogin($password) {
        if (empty($password)) {
            return ['valid' => false, 'message' => 'Password is required'];
        }
        
        return ['valid' => true, 'message' => ''];
    }
    
    public static function emailExists($conn, $email, $userType) {
        $table = ($userType === 'pet_sitter') ? 'pet_sitter' : 'pet_owner';
        
        $sql = "SELECT id FROM $table WHERE email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->num_rows > 0;
    }
    
    public static function authenticateUser($conn, $email, $password, $userType) {
        $table = ($userType === 'pet_sitter') ? 'pet_sitter' : 'pet_owner';
        
        $sql = "SELECT id, username, email, password, full_name FROM $table WHERE email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                unset($user['password']);
                return ['success' => true, 'user' => $user];
            }
        }
        
        return ['success' => false, 'message' => 'Invalid email or password'];
    }
}

// USAGE EXAMPLES:

// For SIGNUP processing:
/*
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['signup'])) {
    $email = ValidationHelper::sanitizeInput($_POST['email']);
    $password = $_POST['password'];
    
    // Use signup validation - WITH strength requirements
    $passwordValidation = ValidationHelper::validatePasswordForSignup($password);
    if (!$passwordValidation['valid']) {
        $errors[] = $passwordValidation['message'];
    }
}
*/

// For LOGIN processing:
/*
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $email = ValidationHelper::sanitizeInput($_POST['email']);
    $password = $_POST['password'];
    
    // Use login validation - NO strength requirements
    $passwordValidation = ValidationHelper::validatePasswordForLogin($password);
    if (!$passwordValidation['valid']) {
        $errors[] = $passwordValidation['message'];
    }
    
    // Attempt authentication
    $authResult = ValidationHelper::authenticateUser($conn, $email, $password, $userType);
}
*/
?>