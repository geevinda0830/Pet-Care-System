<?php
// Debug Regular Login System
// File: debug_login.php

session_start();
require_once 'config/db_connect.php';

echo "<h2>🔍 Regular Login System Debug</h2>";
echo "<div style='font-family: Arial, sans-serif; margin: 20px; line-height: 1.6;'>";

// 1. Database Connection Test
echo "<h3>🗄️ Database Connection Test</h3>";

if (isset($conn) && $conn) {
    echo "<p style='color: green;'>✅ Database connection successful</p>";
    echo "<p><strong>Server Info:</strong> " . $conn->server_info . "</p>";
    
    // Test basic query
    $test_query = "SELECT 1 as test";
    $result = $conn->query($test_query);
    if ($result) {
        echo "<p style='color: green;'>✅ Database queries working</p>";
    } else {
        echo "<p style='color: red;'>❌ Database query failed: " . $conn->error . "</p>";
    }
} else {
    echo "<p style='color: red;'>❌ Database connection failed</p>";
    if (isset($conn)) {
        echo "<p><strong>Error:</strong> " . $conn->connect_error . "</p>";
    }
    echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px;'>";
    echo "<h4>Database Connection Issue</h4>";
    echo "<p>Check your config/db_connect.php file settings.</p>";
    echo "</div>";
}

// 2. Pet Owner Table Structure Check
echo "<h3>📋 Pet Owner Table Analysis</h3>";

if (isset($conn) && $conn) {
    $table_check = $conn->query("SHOW TABLES LIKE 'pet_owner'");
    
    if ($table_check && $table_check->num_rows > 0) {
        echo "<p style='color: green;'>✅ pet_owner table exists</p>";
        
        // Show table structure
        $structure = $conn->query("DESCRIBE pet_owner");
        echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 15px 0; background: white;'>";
        echo "<tr style='background-color: #667eea; color: white;'>";
        echo "<th style='padding: 10px;'>Field</th><th style='padding: 10px;'>Type</th><th style='padding: 10px;'>Null</th><th style='padding: 10px;'>Key</th><th style='padding: 10px;'>Default</th></tr>";
        
        $has_password = false;
        $has_email = false;
        
        while ($row = $structure->fetch_assoc()) {
            echo "<tr>";
            echo "<td style='padding: 8px;'>" . $row['Field'] . "</td>";
            echo "<td style='padding: 8px;'>" . $row['Type'] . "</td>";
            echo "<td style='padding: 8px;'>" . $row['Null'] . "</td>";
            echo "<td style='padding: 8px;'>" . $row['Key'] . "</td>";
            echo "<td style='padding: 8px;'>" . ($row['Default'] ?? 'NULL') . "</td>";
            echo "</tr>";
            
            if ($row['Field'] === 'password') $has_password = true;
            if ($row['Field'] === 'email') $has_email = true;
        }
        echo "</table>";
        
        if (!$has_email) {
            echo "<p style='color: red;'>❌ No 'email' field found in pet_owner table</p>";
        }
        if (!$has_password) {
            echo "<p style='color: red;'>❌ No 'password' field found in pet_owner table</p>";
        }
        
        // Count records
        $count_result = $conn->query("SELECT COUNT(*) as total FROM pet_owner");
        $total_records = $count_result ? $count_result->fetch_assoc()['total'] : 0;
        echo "<p><strong>Total pet owners:</strong> {$total_records}</p>";
        
        if ($total_records > 0) {
            // Show sample records (without password)
            echo "<h4>📊 Sample Pet Owner Records:</h4>";
            $sample_query = "SELECT ownerID, fullName, email, " . ($has_password ? "CASE WHEN password IS NOT NULL THEN 'SET' ELSE 'NULL' END as password_status" : "'NO_PASSWORD_FIELD'") . " FROM pet_owner LIMIT 5";
            $sample_result = $conn->query($sample_query);
            
            if ($sample_result && $sample_result->num_rows > 0) {
                echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 15px 0; background: white;'>";
                echo "<tr style='background-color: #28a745; color: white;'>";
                echo "<th style='padding: 10px;'>ID</th><th style='padding: 10px;'>Name</th><th style='padding: 10px;'>Email</th><th style='padding: 10px;'>Password</th></tr>";
                
                while ($row = $sample_result->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td style='padding: 8px;'>" . $row['ownerID'] . "</td>";
                    echo "<td style='padding: 8px;'>" . htmlspecialchars($row['fullName']) . "</td>";
                    echo "<td style='padding: 8px;'>" . htmlspecialchars($row['email']) . "</td>";
                    echo "<td style='padding: 8px;'>" . ($has_password ? $row['password_status'] : 'NO FIELD') . "</td>";
                    echo "</tr>";
                }
                echo "</table>";
            }
        } else {
            echo "<p style='color: orange;'>⚠️ No pet owner records found - you need to create a test account</p>";
        }
        
    } else {
        echo "<p style='color: red;'>❌ pet_owner table not found</p>";
    }
} else {
    echo "<p style='color: red;'>❌ Cannot check table - no database connection</p>";
}

// 3. Test Login Form Processing
echo "<h3>🔐 Login Form Test</h3>";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_login'])) {
    $test_email = trim($_POST['email']);
    $test_password = $_POST['password'];
    
    echo "<div style='background: #e7f3ff; border: 1px solid #b8e6ff; padding: 15px; border-radius: 5px; margin: 15px 0;'>";
    echo "<h4>🧪 Testing Login Process</h4>";
    echo "<p><strong>Email:</strong> " . htmlspecialchars($test_email) . "</p>";
    echo "<p><strong>Password:</strong> " . (empty($test_password) ? 'Empty' : 'Provided (length: ' . strlen($test_password) . ')') . "</p>";
    
    if (empty($test_email) || empty($test_password)) {
        echo "<p style='color: red;'>❌ Email or password is empty</p>";
    } else {
        if (filter_var($test_email, FILTER_VALIDATE_EMAIL)) {
            echo "<p style='color: green;'>✅ Email format is valid</p>";
            
            // Test database query
            if (isset($conn) && $conn) {
                $stmt = $conn->prepare("SELECT ownerID, fullName, email, password FROM pet_owner WHERE email = ?");
                if ($stmt) {
                    $stmt->bind_param("s", $test_email);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result->num_rows > 0) {
                        $user = $result->fetch_assoc();
                        echo "<p style='color: green;'>✅ User found in database</p>";
                        echo "<p><strong>User ID:</strong> " . $user['ownerID'] . "</p>";
                        echo "<p><strong>Full Name:</strong> " . htmlspecialchars($user['fullName']) . "</p>";
                        
                        if (!empty($user['password'])) {
                            echo "<p style='color: green;'>✅ User has password set</p>";
                            
                            // Test password verification
                            if (password_verify($test_password, $user['password'])) {
                                echo "<p style='color: green;'>✅ Password verification SUCCESS</p>";
                                echo "<div style='background: #d4edda; padding: 10px; border-radius: 5px; margin: 10px 0;'>";
                                echo "<strong>🎉 LOGIN SHOULD WORK!</strong><br>";
                                echo "The login system is functioning correctly for this user.";
                                echo "</div>";
                            } else {
                                echo "<p style='color: red;'>❌ Password verification FAILED</p>";
                                echo "<p>The password you entered doesn't match the stored password.</p>";
                            }
                        } else {
                            echo "<p style='color: orange;'>⚠️ User has no password set (Google-only account?)</p>";
                        }
                    } else {
                        echo "<p style='color: red;'>❌ No user found with this email</p>";
                    }
                    $stmt->close();
                } else {
                    echo "<p style='color: red;'>❌ Database query preparation failed: " . $conn->error . "</p>";
                }
            } else {
                echo "<p style='color: red;'>❌ No database connection for testing</p>";
            }
        } else {
            echo "<p style='color: red;'>❌ Invalid email format</p>";
        }
    }
    echo "</div>";
}

// Login test form
echo "<div style='background: #f8f9fa; border: 1px solid #dee2e6; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
echo "<h4>🧪 Test Login Credentials</h4>";
echo "<form method='POST' action=''>";
echo "<div style='margin-bottom: 15px;'>";
echo "<label><strong>Email:</strong></label><br>";
echo "<input type='email' name='email' style='width: 300px; padding: 8px; border: 1px solid #ccc; border-radius: 4px;' placeholder='Enter test email' required>";
echo "</div>";
echo "<div style='margin-bottom: 15px;'>";
echo "<label><strong>Password:</strong></label><br>";
echo "<input type='password' name='password' style='width: 300px; padding: 8px; border: 1px solid #ccc; border-radius: 4px;' placeholder='Enter test password' required>";
echo "</div>";
echo "<button type='submit' name='test_login' style='background: #667eea; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;'>🧪 Test Login</button>";
echo "</form>";
echo "</div>";

// 4. Session Test
echo "<h3>📊 Session Status</h3>";
echo "<p><strong>Session ID:</strong> " . session_id() . "</p>";
echo "<p><strong>Session Status:</strong> " . (session_status() === PHP_SESSION_ACTIVE ? 'Active' : 'Not Active') . "</p>";

if (isset($_SESSION['user_id'])) {
    echo "<p style='color: green;'>✅ User is currently logged in</p>";
    echo "<p><strong>User ID:</strong> " . $_SESSION['user_id'] . "</p>";
    echo "<p><strong>User Type:</strong> " . ($_SESSION['user_type'] ?? 'Not set') . "</p>";
    echo "<p><strong>User Name:</strong> " . ($_SESSION['user_name'] ?? 'Not set') . "</p>";
} else {
    echo "<p style='color: orange;'>ℹ️ No user currently logged in</p>";
}

// 5. Create Test Account Option
echo "<h3>👤 Create Test Account</h3>";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_test'])) {
    $test_name = "Test User";
    $test_email = "test@example.com";
    $test_password = "password123";
    $hashed_password = password_hash($test_password, PASSWORD_DEFAULT);
    
    if (isset($conn) && $conn) {
        // Check if test user already exists
        $check_stmt = $conn->prepare("SELECT ownerID FROM pet_owner WHERE email = ?");
        $check_stmt->bind_param("s", $test_email);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            echo "<p style='color: orange;'>⚠️ Test user already exists</p>";
        } else {
            // Create test user
            $insert_stmt = $conn->prepare("INSERT INTO pet_owner (fullName, email, password, phoneNumber, address, registrationDate) VALUES (?, ?, ?, '', '', NOW())");
            $insert_stmt->bind_param("sss", $test_name, $test_email, $hashed_password);
            
            if ($insert_stmt->execute()) {
                echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px;'>";
                echo "<h4>✅ Test Account Created Successfully!</h4>";
                echo "<p><strong>Email:</strong> {$test_email}</p>";
                echo "<p><strong>Password:</strong> {$test_password}</p>";
                echo "<p>You can now test login with these credentials.</p>";
                echo "</div>";
            } else {
                echo "<p style='color: red;'>❌ Failed to create test account: " . $conn->error . "</p>";
            }
            $insert_stmt->close();
        }
        $check_stmt->close();
    }
}

echo "<div style='background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 15px 0;'>";
echo "<p>If you don't have any test accounts, click the button below to create one:</p>";
echo "<form method='POST' action=''>";
echo "<button type='submit' name='create_test' style='background: #28a745; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;'>👤 Create Test Account</button>";
echo "</form>";
echo "<p><small>This will create: <strong>test@example.com</strong> / <strong>password123</strong></small></p>";
echo "</div>";

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

tr:nth-child(even) {
    background-color: #f8f9fa;
}

input, button {
    font-family: inherit;
}

button:hover {
    opacity: 0.9;
}
</style>