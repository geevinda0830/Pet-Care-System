<?php
header('Content-Type: application/json');
session_start();
require_once '../config/db_connect.php';

try {
    if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'pet_owner') {
        throw new Exception('Please login to add items to cart');
    }

    $product_id = intval($_POST['product_id']);
    $quantity = intval($_POST['quantity'] ?? 1);
    $user_id = $_SESSION['user_id'];

    if ($product_id <= 0) throw new Exception('Invalid product');
    if ($quantity <= 0) $quantity = 1;

    // Check product exists
    $product_sql = "SELECT price, stock FROM pet_food_and_accessories WHERE productID = ?";
    $product_stmt = $conn->prepare($product_sql);
    $product_stmt->bind_param("i", $product_id);
    $product_stmt->execute();
    $product_result = $product_stmt->get_result();
    
    if ($product_result->num_rows === 0) throw new Exception('Product not found');
    
    $product = $product_result->fetch_assoc();
    if ($product['stock'] < $quantity) throw new Exception('Insufficient stock');

    // Get or create cart
    $cart_sql = "SELECT cartID FROM cart WHERE userID = ? AND orderID IS NULL LIMIT 1";
    $cart_stmt = $conn->prepare($cart_sql);
    $cart_stmt->bind_param("i", $user_id);
    $cart_stmt->execute();
    $cart_result = $cart_stmt->get_result();

    if ($cart_result->num_rows === 0) {
        $create_cart_sql = "INSERT INTO cart (userID) VALUES (?)";
        $create_cart_stmt = $conn->prepare($create_cart_sql);
        $create_cart_stmt->bind_param("i", $user_id);
        $create_cart_stmt->execute();
        $cart_id = $create_cart_stmt->insert_id;
    } else {
        $cart_id = $cart_result->fetch_assoc()['cartID'];
    }

    // Check if item already in cart
    $check_sql = "SELECT cartItemID, quantity FROM cart_items WHERE cartID = ? AND productID = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ii", $cart_id, $product_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        // Update existing item
        $existing = $check_result->fetch_assoc();
        $new_quantity = $existing['quantity'] + $quantity;
        
        $update_sql = "UPDATE cart_items SET quantity = ? WHERE cartItemID = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("ii", $new_quantity, $existing['cartItemID']);
        $update_stmt->execute();
    } else {
        // Add new item
        $insert_sql = "INSERT INTO cart_items (cartID, productID, quantity, price) VALUES (?, ?, ?, ?)";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("iiid", $cart_id, $product_id, $quantity, $product['price']);
        $insert_stmt->execute();
    }

    // Get updated cart count
    $count_sql = "SELECT SUM(quantity) as count FROM cart_items WHERE cartID = ?";
    $count_stmt = $conn->prepare($count_sql);
    $count_stmt->bind_param("i", $cart_id);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $cart_count = $count_result->fetch_assoc()['count'] ?? 0;

    echo json_encode([
        'success' => true,
        'message' => 'Item added to cart',
        'cart_count' => $cart_count
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>