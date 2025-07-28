<?php
header('Content-Type: application/json');
session_start();
require_once '../config/db_connect.php';

try {
    if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'pet_owner') {
        echo json_encode(['success' => true, 'count' => 0]);
        exit;
    }

    $user_id = $_SESSION['user_id'];
    
    $sql = "SELECT COALESCE(SUM(ci.quantity), 0) as count 
            FROM cart c 
            LEFT JOIN cart_items ci ON c.cartID = ci.cartID 
            WHERE c.userID = ? AND c.orderID IS NULL";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $count = $result->fetch_assoc()['count'] ?? 0;

    echo json_encode([
        'success' => true,
        'count' => intval($count)
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'count' => 0,
        'message' => $e->getMessage()
    ]);
}
?>