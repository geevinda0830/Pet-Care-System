<?php
header('Content-Type: application/json');
session_start();
require_once '../config/db_connect.php';

try {
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Not logged in');
    }

    $cart_item_id = intval($_POST['cart_item_id']);
    $user_id = $_SESSION['user_id'];

    if ($cart_item_id <= 0) throw new Exception('Invalid item ID');

    // Verify item belongs to user
    $verify_sql = "SELECT ci.cartItemID FROM cart_items ci 
                   JOIN cart c ON ci.cartID = c.cartID 
                   WHERE ci.cartItemID = ? AND c.userID = ?";
    $verify_stmt = $conn->prepare($verify_sql);
    $verify_stmt->bind_param("ii", $cart_item_id, $user_id);
    $verify_stmt->execute();
    
    if ($verify_stmt->get_result()->num_rows === 0) {
        throw new Exception('Item not found');
    }

    // Remove item
    $delete_sql = "DELETE FROM cart_items WHERE cartItemID = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("i", $cart_item_id);
    $delete_stmt->execute();

    // Get updated count
    $count_sql = "SELECT COALESCE(SUM(ci.quantity), 0) as count 
                  FROM cart c 
                  LEFT JOIN cart_items ci ON c.cartID = ci.cartID 
                  WHERE c.userID = ? AND c.orderID IS NULL";
    $count_stmt = $conn->prepare($count_sql);
    $count_stmt->bind_param("i", $user_id);
    $count_stmt->execute();
    $count = $count_stmt->get_result()->fetch_assoc()['count'] ?? 0;

    echo json_encode([
        'success' => true,
        'message' => 'Item removed',
        'cart_count' => intval($count)
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>