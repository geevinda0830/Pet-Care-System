<?php
// Session Debug & Fix Tool
// File: session_debug.php

session_start();

echo "<h2>🔧 OAuth State Issue Debug & Fix</h2>";
echo "<div style='font-family: Arial, sans-serif; margin: 20px; line-height: 1.6;'>";

// Check current session configuration
echo "<h3>⚙️ Session Configuration</h3>";

echo "<table border='1' style='border-collapse: collapse; width: 100%; margin-bottom: 20px; background: white;'>";
echo "<tr style='background-color: #667eea; color: white;'>";
echo "<th style='padding: 10px;'>Setting</th><th style='padding: 10px;'>Value</th><th style='padding: 10px;'>Recommendation</th>";
echo "</tr>";

$sessionConfig = [
    'session.cookie_lifetime' => ini_get('session.cookie_lifetime'),
    'session.gc_maxlifetime' => ini_get('session.gc_maxlifetime'),
    'session.cookie_secure' => ini_get('session.cookie_secure'),
    'session.cookie_httponly' => ini_get('session.cookie_httponly'),
    'session.cookie_samesite' => ini_get('session.cookie_samesite'),
    'session.save_path' => ini_get('session.save_path'),
];

foreach ($sessionConfig as $setting => $value) {
    $recommendation = '';
    $color = 'black';
    
    switch ($setting) {
        case 'session.cookie_lifetime':
            $recommendation = $value > 0 ? 'Good' : 'Consider setting to 3600';
            $color = $value > 0 ? 'green' : 'orange';
            break;
        case 'session.gc_maxlifetime':
            $recommendation = $value >= 1440 ? 'Good' : 'Should be at least 1440';
            $color = $value >= 1440 ? 'green' : 'orange';
            break;
        case 'session.cookie_secure':
            $recommendation = 'Should be 0 for localhost, 1 for HTTPS';
            break;
        case 'session.cookie_httponly':
            $recommendation = $value ? 'Good (secure)' : 'Should be enabled';
            $color = $value ? 'green' : 'orange';
            break;
    }
    
    echo "<tr>";
    echo "<td style='padding: 10px;'>{$setting}</td>";
    echo "<td style='padding: 10px; color: {$color};'>" . ($value === '' ? 'Not set' : $value) . "</td>";
    echo "<td style='padding: 10px;'>{$recommendation}</td>";
    echo "</tr>";
}
echo "</table>";

// Current session info
echo "<h3>📊 Current Session Info</h3>";
echo "<p><strong>Session ID:</strong> " . session_id() . "</p>";
echo "<p><strong>Session Status:</strong> " . (session_status() === PHP_SESSION_ACTIVE ? 'Active' : 'Not Active') . "</p>";
echo "<p><strong>Session Save Path:</strong> " . session_save_path() . "</p>";

// Check if session save path is writable
$savePath = session_save_path();
if (empty($savePath)) {
    $savePath = sys_get_temp_dir();
}

if (is_writable($savePath)) {
    echo "<p style='color: green;'>✅ Session save path is writable</p>";
} else {
    echo "<p style='color: red;'>❌ Session save path is not writable: {$savePath}</p>";
}

// Current session data
echo "<h3>📋 Current Session Data</h3>";
echo "<pre style='background: #f1f3f4; padding: 15px; border-radius: 5px; max-height: 200px; overflow-y: auto;'>";
print_r($_SESSION);
echo "</pre>";

// Test session persistence
echo "<h3>🧪 Session Persistence Test</h3>";

if (!isset($_SESSION['test_counter'])) {
    $_SESSION['test_counter'] = 1;
    echo "<p>✅ Created test session variable</p>";
} else {
    $_SESSION['test_counter']++;
    echo "<p>✅ Session is persisting - counter: {$_SESSION['test_counter']}</p>";
}

// OAuth State Management Test
echo "<h3>🔐 OAuth State Test</h3>";

if (isset($_GET['test_oauth'])) {
    // Simulate OAuth state creation
    $test_state = bin2hex(random_bytes(16));
    $_SESSION['test_oauth_state'] = $test_state;
    
    echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px;'>";
    echo "<h4>✅ Test OAuth State Created</h4>";
    echo "<p><strong>Generated State:</strong> <code>{$test_state}</code></p>";
    echo "<p><strong>Stored in Session:</strong> <code>{$_SESSION['test_oauth_state']}</code></p>";
    echo "<p><a href='?verify_oauth={$test_state}' style='color: #667eea;'>→ Click here to test state verification</a></p>";
    echo "</div>";
    
} elseif (isset($_GET['verify_oauth'])) {
    $received_state = $_GET['verify_oauth'];
    $stored_state = $_SESSION['test_oauth_state'] ?? '';
    
    if ($received_state === $stored_state) {
        echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px;'>";
        echo "<h4>✅ OAuth State Verification PASSED</h4>";
        echo "<p>Session state management is working correctly!</p>";
        echo "</div>";
    } else {
        echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 5px;'>";
        echo "<h4>❌ OAuth State Verification FAILED</h4>";
        echo "<p><strong>Expected:</strong> <code>{$stored_state}</code></p>";
        echo "<p><strong>Received:</strong> <code>{$received_state}</code></p>";
        echo "<p>This indicates a session problem!</p>";
        echo "</div>";
    }
    unset($_SESSION['test_oauth_state']);
} else {
    echo "<p><a href='?test_oauth=1' style='background: #667eea; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🧪 Test OAuth State Management</a></p>";
}

// Solutions for common state issues
echo "<h3>🛠️ Common Solutions for State Parameter Issues</h3>";

echo "<div style='background: #fff3cd; border: 1px solid #ffeaa7; padding: 20px; border-radius: 8px; margin: 15px 0;'>";
echo "<h4>💡 Quick Fixes to Try:</h4>";
echo "<ol>";
echo "<li><strong>Clear Browser Data:</strong> Clear cookies, cache, and session storage</li>";
echo "<li><strong>Try Incognito Mode:</strong> Test Google login in private browsing</li>";
echo "<li><strong>Single Tab Only:</strong> Don't open multiple login tabs simultaneously</li>";
echo "<li><strong>Check Session Path:</strong> Make sure PHP can write to session directory</li>";
echo "<li><strong>Disable Session Cache:</strong> Add session settings to fix caching issues</li>";
echo "</ol>";
echo "</div>";

// Advanced session configuration fix
echo "<h3>⚙️ Recommended Session Configuration Fix</h3>";

echo "<div style='background: #e7f3ff; border: 1px solid #b8e6ff; padding: 20px; border-radius: 8px;'>";
echo "<h4>🔧 Add this to your config/google_config.php (at the top):</h4>";
echo "<pre style='background: #f8f9fa; padding: 15px; border-radius: 5px; border: 1px solid #dee2e6;'>";
echo htmlspecialchars('<?php
// Fix session configuration for OAuth
ini_set("session.cookie_lifetime", 3600);
ini_set("session.gc_maxlifetime", 3600);
ini_set("session.cookie_httponly", 1);
ini_set("session.use_strict_mode", 1);
ini_set("session.cookie_samesite", "Lax");

// Start session with proper settings
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}');
echo "</pre>";
echo "</div>";

// Check for multiple session starts
echo "<h3>🔍 Multiple Session Start Detection</h3>";

$sessionStartCount = 0;
if (isset($_SESSION['session_start_count'])) {
    $_SESSION['session_start_count']++;
    $sessionStartCount = $_SESSION['session_start_count'];
} else {
    $_SESSION['session_start_count'] = 1;
    $sessionStartCount = 1;
}

if ($sessionStartCount > 3) {
    echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 5px;'>";
    echo "<h4>⚠️ Multiple Session Starts Detected</h4>";
    echo "<p>This page has been loaded {$sessionStartCount} times. Multiple session starts can cause state issues.</p>";
    echo "<p><a href='session_debug.php' style='color: #667eea;'>↻ Reset and start fresh</a></p>";
    echo "</div>";
} else {
    echo "<p style='color: green;'>✅ Session start count is normal: {$sessionStartCount}</p>";
}

echo "</div>";
?>

<style>
body {
    font-family: Arial, sans-serif;
    margin: 20px;
    background-color: #f8f9fa;
}

h2 {
    color: #333;
    border-bottom: 3px solid #667eea;
    padding-bottom: 10px;
}

h3 {
    color: #555;
    margin-top: 30px;
    border-left: 4px solid #667eea;
    padding-left: 15px;
}

h4 {
    color: #666;
    margin-top: 20px;
}

table {
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    border-radius: 5px;
    overflow: hidden;
}

th {
    background-color: #667eea !important;
    color: white !important;
}

tr:nth-child(even) {
    background-color: #f8f9fa;
}

code {
    background: #e9ecef;
    padding: 2px 6px;
    border-radius: 4px;
    font-family: 'Monaco', 'Consolas', monospace;
}

pre {
    font-family: 'Monaco', 'Consolas', monospace;
    font-size: 0.9em;
}

a {
    color: #667eea;
    text-decoration: none;
}

a:hover {
    text-decoration: underline;
}

ol, ul {
    margin: 10px 0;
    padding-left: 25px;
}

li {
    margin-bottom: 8px;
}
</style>