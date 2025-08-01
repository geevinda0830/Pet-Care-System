<?php
// Google OAuth Configuration - FIXED VERSION
// File: config/google_config.php

// Fix session configuration FIRST (before any session operations)
ini_set("session.cookie_lifetime", 7200); // 2 hours
ini_set("session.gc_maxlifetime", 7200);
ini_set("session.cookie_httponly", 1);
ini_set("session.use_strict_mode", 1);
ini_set("session.cookie_samesite", "Lax");

// Start session with error handling
if (session_status() !== PHP_SESSION_ACTIVE) {
    if (!session_start()) {
        error_log('Failed to start session for Google OAuth');
        die('Session error. Please try again.');
    }
}

// Google OAuth 2.0 credentials
// Replace these with your ACTUAL credentials from Google Cloud Console
define('GOOGLE_CLIENT_ID', '1074425734254-elud0r9epn80sha25bsfg63j6p4sjqm7.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', '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'); // Replace with your real secret
define('GOOGLE_REDIRECT_URI', 'http://localhost/pet_care_system/auth/google_callback.php');

// Google OAuth URLs
define('GOOGLE_OAUTH_URL', 'https://accounts.google.com/o/oauth2/auth');
define('GOOGLE_TOKEN_URL', 'https://oauth2.googleapis.com/token');
define('GOOGLE_USERINFO_URL', 'https://www.googleapis.com/oauth2/v2/userinfo');

// OAuth scopes
define('GOOGLE_OAUTH_SCOPE', 'https://www.googleapis.com/auth/userinfo.email https://www.googleapis.com/auth/userinfo.profile');

class GoogleAuth {
    private $client_id;
    private $client_secret;
    private $redirect_uri;
    
    public function __construct() {
        $this->client_id = GOOGLE_CLIENT_ID;
        $this->client_secret = GOOGLE_CLIENT_SECRET;
        $this->redirect_uri = GOOGLE_REDIRECT_URI;
    }
    
    /**
     * Generate Google OAuth login URL with improved state handling
     */
    public function getLoginUrl() {
        // Generate a more secure state parameter
        $state = bin2hex(random_bytes(32)); // Increased from 16 to 32 bytes
        
        // Store state with timestamp for expiration
        $_SESSION['google_oauth_state'] = $state;
        $_SESSION['google_oauth_state_time'] = time();
        
        // Log state creation for debugging
        error_log("Google OAuth: Created state {$state} at " . date('Y-m-d H:i:s'));
        
        $params = [
            'client_id' => $this->client_id,
            'redirect_uri' => $this->redirect_uri,
            'scope' => GOOGLE_OAUTH_SCOPE,
            'response_type' => 'code',
            'state' => $state,
            'access_type' => 'offline',
            'prompt' => 'consent'
        ];
        
        return GOOGLE_OAUTH_URL . '?' . http_build_query($params);
    }
    
    /**
     * Exchange authorization code for access token
     */
    public function getAccessToken($code) {
        $data = [
            'client_id' => $this->client_id,
            'client_secret' => $this->client_secret,
            'redirect_uri' => $this->redirect_uri,
            'grant_type' => 'authorization_code',
            'code' => $code
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, GOOGLE_TOKEN_URL);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For localhost only
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            error_log("Google OAuth cURL error: {$curlError}");
            return false;
        }
        
        if ($httpCode !== 200) {
            error_log("Google OAuth token request failed with HTTP {$httpCode}: {$response}");
            return false;
        }
        
        $result = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("Google OAuth invalid JSON response: {$response}");
            return false;
        }
        
        return $result;
    }
    
    /**
     * Get user information from Google
     */
    public function getUserInfo($access_token) {
        $url = GOOGLE_USERINFO_URL . '?access_token=' . $access_token;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $access_token,
            'Accept: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For localhost only
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            error_log("Google OAuth cURL error getting user info: {$curlError}");
            return false;
        }
        
        if ($httpCode !== 200) {
            error_log("Google OAuth user info request failed with HTTP {$httpCode}: {$response}");
            return false;
        }
        
        $result = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("Google OAuth invalid JSON response for user info: {$response}");
            return false;
        }
        
        return $result;
    }
    
    /**
     * Verify state parameter with improved security and debugging
     */
    public function verifyState($receivedState) {
        $storedState = $_SESSION['google_oauth_state'] ?? null;
        $stateTime = $_SESSION['google_oauth_state_time'] ?? 0;
        $currentTime = time();
        
        // Log state verification for debugging
        error_log("Google OAuth: Verifying state");
        error_log("  Received: {$receivedState}");
        error_log("  Stored: {$storedState}");
        error_log("  State age: " . ($currentTime - $stateTime) . " seconds");
        
        // Check if state exists
        if (!$storedState) {
            error_log("Google OAuth: No stored state found in session");
            return false;
        }
        
        // Check state expiration (10 minutes max)
        if (($currentTime - $stateTime) > 600) {
            error_log("Google OAuth: State expired (older than 10 minutes)");
            unset($_SESSION['google_oauth_state']);
            unset($_SESSION['google_oauth_state_time']);
            return false;
        }
        
        // Check if states match
        $statesMatch = hash_equals($storedState, $receivedState);
        
        if (!$statesMatch) {
            error_log("Google OAuth: State mismatch - possible CSRF attack");
            return false;
        }
        
        error_log("Google OAuth: State verification successful");
        return true;
    }
}
?>