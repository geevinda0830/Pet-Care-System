<?php
/**
 * Enhanced PHP validation functions for Pet Care System
 * Add this to your existing validation logic
 */

class ValidationHelper {
    
    /**
     * Validate email address with comprehensive checks
     */
    public static function validateEmail($email) {
        $email = trim($email);
        
        // Check if email is empty
        if (empty($email)) {
            return ['valid' => false, 'message' => 'Email is required'];
        }
        
        // Check email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['valid' => false, 'message' => 'Invalid email format'];
        }
        
        // Check email length
        if (strlen($email) > 254) {
            return ['valid' => false, 'message' => 'Email is too long'];
        }
        
        // Additional checks for common issues
        if (substr_count($email, '@') !== 1) {
            return ['valid' => false, 'message' => 'Invalid email format'];
        }
        
        list($local, $domain) = explode('@', $email);
        
        // Check local part length (before @)
        if (strlen($local) > 64) {
            return ['valid' => false, 'message' => 'Email local part is too long'];
        }
        
        // Check for consecutive dots
        if (strpos($email, '..') !== false) {
            return ['valid' => false, 'message' => 'Invalid email format'];
        }
        
        return ['valid' => true, 'message' => 'Valid email'];
    }
    
    /**
     * Validate password with strength requirements
     */
    public static function validatePassword($password) {
        $errors = [];
        
        // Check minimum length
        if (strlen($password) < 6) {
            $errors[] = 'Password must be at least 6 characters long';
        }
        
        // Check for at least one letter
        if (!preg_match('/[a-zA-Z]/', $password)) {
            $errors[] = 'Password must contain at least one letter';
        }
        
        // Check for at least one number or special character
        if (!preg_match('/[0-9!@#$%^&*(),.?":{}|<>]/', $password)) {
            $errors[] = 'Password must contain at least one number or special character';
        }
        
        // Check for common weak passwords
        $weakPasswords = ['123456', 'password', '123456789', '12345678', '12345', 
                         '1234567', 'qwerty', 'abc123', 'password123', 'admin'];
        
        if (in_array(strtolower($password), $weakPasswords)) {
            $errors[] = 'Password is too common and weak';
        }
        
        if (empty($errors)) {
            return ['valid' => true, 'message' => 'Strong password'];
        } else {
            return ['valid' => false, 'message' => implode('. ', $errors)];
        }
    }
    
    /**
     * Sanitize input data
     */
    public static function sanitizeInput($data) {
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data);
        return $data;
    }
    
    /**
     * Validate username
     */
    public static function validateUsername($username) {
        $username = trim($username);
        
        if (empty($username)) {
            return ['valid' => false, 'message' => 'Username is required'];
        }
        
        if (strlen($username) < 3) {
            return ['valid' => false, 'message' => 'Username must be at least 3 characters long'];
        }
        
        if (strlen($username) > 30) {
            return ['valid' => false, 'message' => 'Username cannot exceed 30 characters'];
        }
        
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            return ['valid' => false, 'message' => 'Username can only contain letters, numbers, and underscores'];
        }
        
        return ['valid' => true, 'message' => 'Valid username'];
    }
    
    /**
     * Validate phone number
     */
    public static function validatePhone($phone) {
        $phone = trim($phone);
        
        if (empty($phone)) {
            return ['valid' => false, 'message' => 'Phone number is required'];
        }
        
        // Remove all non-digit characters for validation
        $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
        
        // Check length (Sri Lankan numbers are typically 10 digits)
        if (strlen($cleanPhone) < 10 || strlen($cleanPhone) > 12) {
            return ['valid' => false, 'message' => 'Invalid phone number format'];
        }
        
        return ['valid' => true, 'message' => 'Valid phone number'];
    }
    
    /**
     * Check if email exists in database
     */
    public static function emailExists($conn, $email, $userType, $excludeId = null) {
        $table = ($userType === 'pet_sitter') ? 'pet_sitter' : 'pet_owner';
        
        $sql = "SELECT id FROM $table WHERE email = ?";
        if ($excludeId) {
            $sql .= " AND id != ?";
        }
        
        $stmt = $conn->prepare($sql);
        if ($excludeId) {
            $stmt->bind_param("si", $email, $excludeId);
        } else {
            $stmt->bind_param("s", $email);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->num_rows > 0;
    }
    
    /**
     * Check if username exists in database
     */
    public static function usernameExists($conn, $username, $userType, $excludeId = null) {
        $table = ($userType === 'pet_sitter') ? 'pet_sitter' : 'pet_owner';
        
        $sql = "SELECT id FROM $table WHERE username = ?";
        if ($excludeId) {
            $sql .= " AND id != ?";
        }
        
        $stmt = $conn->prepare($sql);
        if ($excludeId) {
            $stmt->bind_param("si", $username, $excludeId);
        } else {
            $stmt->bind_param("s", $username);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->num_rows > 0;
    }
}

// Usage example in your registration/login forms:

// For registration form processing:
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    
    // Sanitize inputs
    $username = ValidationHelper::sanitizeInput($_POST['username']);
    $email = ValidationHelper::sanitizeInput($_POST['email']);
    $password = $_POST['password']; // Don't sanitize password, just validate
    $confirmPassword = $_POST['confirm_password'];
    $userType = ValidationHelper::sanitizeInput($_POST['user_type']);
    
    // Validate username
    $usernameValidation = ValidationHelper::validateUsername($username);
    if (!$usernameValidation['valid']) {
        $errors[] = $usernameValidation['message'];
    } else if (ValidationHelper::usernameExists($conn, $username, $userType)) {
        $errors[] = 'Username already exists';
    }
    
    // Validate email
    $emailValidation = ValidationHelper::validateEmail($email);
    if (!$emailValidation['valid']) {
        $errors[] = $emailValidation['message'];
    } else if (ValidationHelper::emailExists($conn, $email, $userType)) {
        $errors[] = 'Email already exists';
    }
    
    // Validate password
    $passwordValidation = ValidationHelper::validatePassword($password);
    if (!$passwordValidation['valid']) {
        $errors[] = $passwordValidation['message'];
    }
    
    // Check password confirmation
    if ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match';
    }
    
    // If no errors, proceed with registration
    if (empty($errors)) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        // Continue with your existing registration logic...
    } else {
        // Return errors to display
        $errorMessage = implode('<br>', $errors);
    }
}

// For login form processing:
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $errors = [];
    
    $email = ValidationHelper::sanitizeInput($_POST['email']);
    $password = $_POST['password'];
    
    // Validate email format
    $emailValidation = ValidationHelper::validateEmail($email);
    if (!$emailValidation['valid']) {
        $errors[] = 'Please enter a valid email address';
    }
    
    if (empty($password)) {
        $errors[] = 'Password is required';
    }
    
    if (empty($errors)) {
        // Continue with your existing login logic...
    } else {
        $errorMessage = implode('<br>', $errors);
    }
}
?>