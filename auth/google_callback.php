<?php
// Google OAuth Callback Handler - Pet Owner Only - FIXED
// File: auth/google_callback.php

session_start();
require_once '../config/db_connect.php';
require_once '../config/google_config.php';

// Initialize Google Auth
$googleAuth = new GoogleAuth();

// Check for errors
if (isset($_GET['error'])) {
    $_SESSION['error_message'] = 'Google authentication was cancelled or failed.';
    header('Location: ../login.php');
    exit();
}

// Verify state parameter to prevent CSRF attacks
if (!isset($_GET['state']) || !$googleAuth->verifyState($_GET['state'])) {
    $_SESSION['error_message'] = 'Invalid state parameter. Please try again.';
    header('Location: ../login.php');
    exit();
}

// Get authorization code
if (!isset($_GET['code'])) {
    $_SESSION['error_message'] = 'Authorization code not received.';
    header('Location: ../login.php');
    exit();
}

$code = $_GET['code'];

try {
    // Exchange code for access token
    $tokenData = $googleAuth->getAccessToken($code);
    
    if (!$tokenData || !isset($tokenData['access_token'])) {
        throw new Exception('Failed to get access token');
    }
    
    $access_token = $tokenData['access_token'];
    
    // Get user information from Google
    $userInfo = $googleAuth->getUserInfo($access_token);
    
    if (!$userInfo || !isset($userInfo['email'])) {
        throw new Exception('Failed to get user information');
    }
    
    // Extract user data
    $google_id = $userInfo['id'];
    $email = $userInfo['email'];
    $first_name = $userInfo['given_name'] ?? '';
    $last_name = $userInfo['family_name'] ?? '';
    $full_name = $userInfo['name'] ?? ($first_name . ' ' . $last_name);
    $profile_picture = $userInfo['picture'] ?? '';
    $verified_email = $userInfo['verified_email'] ?? false;
    
    // Check if pet owner already exists by email
    $stmt = $conn->prepare("SELECT * FROM pet_owner WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $existing_pet_owner = $result->fetch_assoc();
    $stmt->close();
    
    if ($existing_pet_owner) {
        // Pet owner exists - update Google ID and login
        $user_id = $existing_pet_owner['userID'];
        
        // Update Google ID if not set - FIXED SQL
        if (empty($existing_pet_owner['google_id'])) {
            $update_stmt = $conn->prepare("UPDATE pet_owner SET google_id = ?, auth_provider = 'google', email_verified = 1, updated_at = NOW() WHERE userID = ?");
            $update_stmt->bind_param("si", $google_id, $user_id);
            $update_stmt->execute();
            $update_stmt->close();
        }
        
        // Set session variables for pet owner
        $_SESSION['user_id'] = $user_id;
        $_SESSION['user_type'] = 'pet_owner';
        $_SESSION['user_name'] = $existing_pet_owner['fullName'];
        $_SESSION['email'] = $existing_pet_owner['email'];
        $_SESSION['success_message'] = 'Successfully logged in with Google!';
        
    } else {
        // New pet owner - create account
        $auth_provider = 'google';
        $email_verified = $verified_email ? 1 : 0;
        
        // Generate default values
        $default_contact = '';
        $default_address = '';
        $default_gender = 'Other';
        $default_username = strtolower(str_replace(' ', '', $first_name . $last_name)) . rand(100, 999);
        
        // Insert new pet owner - FIXED parameter count
        $insert_stmt = $conn->prepare("INSERT INTO pet_owner (username, fullName, email, google_id, auth_provider, email_verified, contact, address, gender, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
        $insert_stmt->bind_param("sssssssss", $default_username, $full_name, $email, $google_id, $auth_provider, $email_verified, $default_contact, $default_address, $default_gender);
        
        if ($insert_stmt->execute()) {
            $user_id = $conn->insert_id;
            $insert_stmt->close();
            
            // Set session variables for new pet owner
            $_SESSION['user_id'] = $user_id;
            $_SESSION['user_type'] = 'pet_owner';
            $_SESSION['user_name'] = $full_name;
            $_SESSION['email'] = $email;
            $_SESSION['success_message'] = 'Welcome to PetCare System! Your account has been created successfully with Google!';
            
        } else {
            throw new Exception('Failed to create pet owner account: ' . $conn->error);
        }
    }
    
    // Clean up session state
    unset($_SESSION['google_oauth_state']);
    unset($_SESSION['google_oauth_state_time']);
    
    // Redirect to pet owner dashboard
    header('Location: ../user/dashboard.php');
    exit();
    
} catch (Exception $e) {
    // Log error and redirect with message
    error_log('Google OAuth Error: ' . $e->getMessage());
    $_SESSION['error_message'] = 'Authentication failed: ' . $e->getMessage();
    header('Location: ../login.php');
    exit();
}
?>