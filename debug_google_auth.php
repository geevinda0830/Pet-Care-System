<?php
// Debug Google Authentication Issues
// File: debug_google_auth.php

session_start();

echo "<h2>🔍 Google Authentication Debug Tool</h2>";
echo "<div style='font-family: Arial, sans-serif; margin: 20px; line-height: 1.6;'>";

// 1. Check if config files exist
echo "<h3>📁 File Check</h3>";

$requiredFiles = [
    'config/google_config.php' => 'Google OAuth configuration',
    'auth/google_callback.php' => 'Google callback handler',
    'config/db_connect.php' => 'Database connection'
];

$allFilesExist = true;
echo "<table border='1' style='border-collapse: collapse; width: 100%; margin-bottom: 20px;'>";
echo "<tr style='background-color: #667eea; color: white;'>";
echo "<th style='padding: 10px;'>File</th><th style='padding: 10px;'>Purpose</th><th style='padding: 10px;'>Status</th>";
echo "</tr>";

foreach ($requiredFiles as $file => $purpose) {
    $exists = file_exists($file);
    $status = $exists ? "✅ EXISTS" : "❌ MISSING";
    $color = $exists ? "green" : "red";
    
    if (!$exists) $allFilesExist = false;
    
    echo "<tr>";
    echo "<td style='padding: 10px;'>{$file}</td>";
    echo "<td style='padding: 10px;'>{$purpose}</td>";
    echo "<td style='padding: 10px; color: {$color}; font-weight: bold;'>{$status}</td>";
    echo "</tr>";
}
echo "</table>";

// 2. Check Google configuration
echo "<h3>⚙️ Google Configuration Check</h3>";

if (file_exists('config/google_config.php')) {
    require_once 'config/google_config.php';
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%; margin-bottom: 20px;'>";
    echo "<tr style='background-color: #667eea; color: white;'>";
    echo "<th style='padding: 10px;'>Setting</th><th style='padding: 10px;'>Value</th><th style='padding: 10px;'>Status</th>";
    echo "</tr>";
    
    $configs = [
        'GOOGLE_CLIENT_ID' => defined('GOOGLE_CLIENT_ID') ? GOOGLE_CLIENT_ID : 'NOT SET',
        'GOOGLE_CLIENT_SECRET' => defined('GOOGLE_CLIENT_SECRET') ? (strlen(GOOGLE_CLIENT_SECRET) > 10 ? 'SET (Hidden)' : 'NOT SET') : 'NOT SET',
        'GOOGLE_REDIRECT_URI' => defined('GOOGLE_REDIRECT_URI') ? GOOGLE_REDIRECT_URI : 'NOT SET'
    ];
    
    foreach ($configs as $key => $value) {
        $isSet = $value !== 'NOT SET';
        $status = $isSet ? "✅ CONFIGURED" : "❌ MISSING";
        $color = $isSet ? "green" : "red";
        
        echo "<tr>";
        echo "<td style='padding: 10px;'>{$key}</td>";
        echo "<td style='padding: 10px; font-family: monospace;'>{$value}</td>";
        echo "<td style='padding: 10px; color: {$color}; font-weight: bold;'>{$status}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Check if GoogleAuth class exists
    if (class_exists('GoogleAuth')) {
        echo "<p style='color: green;'>✅ GoogleAuth class is available</p>";
        
        try {
            $googleAuth = new GoogleAuth();
            $loginUrl = $googleAuth->getLoginUrl();
            echo "<p style='color: green;'>✅ Google login URL generated successfully</p>";
            echo "<p><strong>Current Google Login URL:</strong><br>";
            echo "<code style='background: #f1f3f4; padding: 5px; border-radius: 3px; word-break: break-all;'>{$loginUrl}</code></p>";
        } catch (Exception $e) {
            echo "<p style='color: red;'>❌ Error creating GoogleAuth: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
    } else {
        echo "<p style='color: red;'>❌ GoogleAuth class not found</p>";
    }
} else {
    echo "<p style='color: red;'>❌ Google configuration file missing</p>";
}

// 3. Check database
echo "<h3>🗄️ Database Check</h3>";

if (file_exists('config/db_connect.php')) {
    require_once 'config/db_connect.php';
    
    if (isset($conn) && $conn) {
        echo "<p style='color: green;'>✅ Database connection successful</p>";
        
        // Check pet_owner table
        $sql = "DESCRIBE pet_owner";
        $result = $conn->query($sql);
        
        if ($result) {
            echo "<p style='color: green;'>✅ pet_owner table exists</p>";
            
            // Check for Google auth columns
            $columns = [];
            while ($row = $result->fetch_assoc()) {
                $columns[] = $row['Field'];
            }
            
            $requiredColumns = ['google_id', 'auth_provider', 'email_verified'];
            $missingColumns = [];
            
            foreach ($requiredColumns as $col) {
                if (!in_array($col, $columns)) {
                    $missingColumns[] = $col;
                }
            }
            
            if (empty($missingColumns)) {
                echo "<p style='color: green;'>✅ All required columns exist in pet_owner table</p>";
            } else {
                echo "<p style='color: red;'>❌ Missing columns in pet_owner table: " . implode(', ', $missingColumns) . "</p>";
                echo "<p><strong>Run this SQL to fix:</strong></p>";
                echo "<code style='background: #f1f3f4; padding: 10px; display: block; border-radius: 5px;'>";
                echo "ALTER TABLE `pet_owner` <br>";
                echo "ADD COLUMN `google_id` VARCHAR(255) NULL,<br>";
                echo "ADD COLUMN `auth_provider` VARCHAR(50) DEFAULT 'local',<br>";
                echo "ADD COLUMN `email_verified` TINYINT(1) DEFAULT 0;";
                echo "</code>";
            }
        } else {
            echo "<p style='color: red;'>❌ pet_owner table not found</p>";
        }
    } else {
        echo "<p style='color: red;'>❌ Database connection failed</p>";
    }
} else {
    echo "<p style='color: red;'>❌ Database config file missing</p>";
}

// 4. Check session and error messages
echo "<h3>🔍 Session Debug Info</h3>";

if (isset($_SESSION['error_message'])) {
    echo "<p style='color: red;'><strong>Current Error:</strong> " . htmlspecialchars($_SESSION['error_message']) . "</p>";
}

if (isset($_SESSION['google_oauth_state'])) {
    echo "<p style='color: blue;'><strong>OAuth State:</strong> " . htmlspecialchars($_SESSION['google_oauth_state']) . "</p>";
}

echo "<p><strong>Current Session Data:</strong></p>";
echo "<pre style='background: #f1f3f4; padding: 10px; border-radius: 5px; overflow-x: auto;'>";
print_r($_SESSION);
echo "</pre>";

// 5. Test URL accessibility
echo "<h3>🌐 URL Accessibility Check</h3>";

$currentDomain = $_SERVER['HTTP_HOST'];
$currentPath = dirname($_SERVER['REQUEST_URI']);
$callbackUrl = "http://{$currentDomain}{$currentPath}/auth/google_callback.php";

echo "<p><strong>Expected Callback URL:</strong><br>";
echo "<code style='background: #f1f3f4; padding: 5px; border-radius: 3px;'>{$callbackUrl}</code></p>";

if (file_exists('auth/google_callback.php')) {
    echo "<p style='color: green;'>✅ Callback file exists</p>";
} else {
    echo "<p style='color: red;'>❌ Callback file missing at auth/google_callback.php</p>";
}

// 6. Common solutions
echo "<h3>🛠️ Quick Fixes</h3>";

echo "<div style='background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
echo "<h4>🔧 Most Common Issues & Solutions:</h4>";
echo "<ol>";
echo "<li><strong>Missing Google Credentials:</strong> Make sure you've added your real Google Client ID and Secret to config/google_config.php</li>";
echo "<li><strong>Wrong Redirect URI:</strong> In Google Cloud Console, make sure your redirect URI exactly matches: <code>{$callbackUrl}</code></li>";
echo "<li><strong>API Not Enabled:</strong> Enable Google+ API or Google Identity API in Google Cloud Console</li>";
echo "<li><strong>Missing Database Columns:</strong> Run the SQL commands to add Google auth columns to pet_owner table</li>";
echo "<li><strong>File Permissions:</strong> Make sure PHP can read all the config and auth files</li>";
echo "</ol>";
echo "</div>";

// 7. Next steps
echo "<h3>📋 Next Steps</h3>";

if (!$allFilesExist) {
    echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 5px;'>";
    echo "<h4>❌ Critical: Missing Files</h4>";
    echo "<p>Upload the missing files first before proceeding.</p>";
    echo "</div>";
} elseif (!defined('GOOGLE_CLIENT_ID') || GOOGLE_CLIENT_ID === 'your-google-client-id.googleusercontent.com') {
    echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 5px;'>";
    echo "<h4>❌ Critical: Google Credentials Missing</h4>";
    echo "<p>You need to:</p>";
    echo "<ol>";
    echo "<li>Go to <a href='https://console.cloud.google.com' target='_blank'>Google Cloud Console</a></li>";
    echo "<li>Create OAuth 2.0 credentials</li>";
    echo "<li>Update config/google_config.php with your real credentials</li>";
    echo "</ol>";
    echo "</div>";
} else {
    echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px;'>";
    echo "<h4>✅ Setup Looks Good!</h4>";
    echo "<p>Try the Google login again. If it still fails, check the browser's Developer Console (F12) for JavaScript errors.</p>";
    echo "</div>";
}

echo "</div>";

// Clear any error messages after displaying
if (isset($_SESSION['error_message'])) {
    unset($_SESSION['error_message']);
}
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

table {
    background: white;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    border-radius: 5px;
    overflow: hidden;
}

tr:nth-child(even) {
    background-color: #f8f9fa;
}

code {
    font-family: 'Monaco', 'Consolas', monospace;
    font-size: 0.9em;
}

pre {
    max-height: 200px;
    overflow-y: auto;
}

a {
    color: #667eea;
    text-decoration: none;
}

a:hover {
    text-decoration: underline;
}
</style>