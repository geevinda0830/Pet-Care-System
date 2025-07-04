<?php
/**
 * Get Cart Count API
 * Returns the current number of items in user's cart
 */

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Set content type to JSON
header('Content-Type: application/json');

// Function to send JSON response and exit
function sendResponse($success, $message, $data = []) {
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ], $data));
    exit();
}

// Check if user is logged in (handle both session variable names)
$user_id = null;
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
} elseif (isset($_SESSION['userID'])) {
    $user_id = $_SESSION['userID'];
}

if (!$user_id) {
    sendResponse(true, 'User not logged in', ['count' => 0]);
}

// Include database connection
require_once 'config/db_connect.php';

// Check database connection
if (!$conn) {
    sendResponse(false, 'Database connection failed', ['count' => 0]);
}

try {
    // Get cart count for user
    $count_sql = "SELECT COALESCE(SUM(ci.quantity), 0) as item_count 
                  FROM cart c 
                  LEFT JOIN cart_items ci ON c.cartID = ci.cartID 
                  WHERE c.userID = ? AND (c.orderID IS NULL OR c.orderID = 0)";
    
    $count_stmt = $conn->prepare($count_sql);
    
    if (!$count_stmt) {
        sendResponse(false, 'Database prepare error', ['count' => 0]);
    }
    
    $count_stmt->bind_param("i", $user_id);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $count_row = $count_result->fetch_assoc();
    $count = intval($count_row['item_count']);
    
    $count_stmt->close();
    $conn->close();
    
    sendResponse(true, 'Cart count retrieved successfully', ['count' => $count]);
    
} catch (Exception $e) {
    error_log("Error in get_cart_count.php: " . $e->getMessage());
    sendResponse(false, 'Error retrieving cart count', ['count' => 0]);
}
?>