<?php
// Include database connection
require_once 'config/db_connect.php';

// Admin credentials
$username = "admin";
$password = "admin123"; // Change this to your desired password
$email = "admin@example.com"; // Change this to your email
$fullName = "Administrator";
$contact = "1234567890";
$address = "Admin Address";
$gender = "Male";

// Hash the password
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// Check if admin table exists
$check_table = "SHOW TABLES LIKE 'admin'";
$table_result = $conn->query($check_table);

if ($table_result->num_rows == 0) {
    // Create admin table
    $create_table = "CREATE TABLE `admin` (
        `userID` int(11) NOT NULL AUTO_INCREMENT,
        `username` varchar(50) NOT NULL,
        `password` varchar(255) NOT NULL,
        `fullName` varchar(100) NOT NULL,
        `email` varchar(100) NOT NULL,
        `contact` varchar(20) DEFAULT NULL,
        `address` text DEFAULT NULL,
        `gender` varchar(10) DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`userID`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if (!$conn->query($create_table)) {
        die("Error creating table: " . $conn->error);
    }
    
    echo "Admin table created successfully.<br>";
}

// Check if admin already exists
$check_admin = "SELECT * FROM admin WHERE username = ? OR email = ?";
$check_stmt = $conn->prepare($check_admin);
$check_stmt->bind_param("ss", $username, $email);
$check_stmt->execute();
$result = $check_stmt->get_result();

if ($result->num_rows > 0) {
    // Update existing admin
    $update_sql = "UPDATE admin SET password = ?, fullName = ?, contact = ?, address = ?, gender = ? WHERE username = ? OR email = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("sssssss", $hashed_password, $fullName, $contact, $address, $gender, $username, $email);
    
    if ($update_stmt->execute()) {
        echo "Admin account updated successfully. You can now log in with:<br>";
        echo "Email: " . $email . "<br>";
        echo "Password: " . $password . "<br>";
    } else {
        echo "Error updating admin: " . $conn->error;
    }
    
    $update_stmt->close();
} else {
    // Insert new admin
    $insert_sql = "INSERT INTO admin (username, password, fullName, email, contact, address, gender) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $insert_stmt = $conn->prepare($insert_sql);
    $insert_stmt->bind_param("sssssss", $username, $hashed_password, $fullName, $email, $contact, $address, $gender);
    
    if ($insert_stmt->execute()) {
        echo "Admin account created successfully. You can now log in with:<br>";
        echo "Email: " . $email . "<br>";
        echo "Password: " . $password . "<br>";
    } else {
        echo "Error creating admin: " . $conn->error;
    }
    
    $insert_stmt->close();
}

$conn->close();
?>