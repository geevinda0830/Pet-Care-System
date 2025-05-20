<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'You must be logged in to add items to your cart.'
    ]);
    exit();
}

// Include database connection
require_once 'config/db_connect.php';

// Check if action is provided
if (!isset($_POST['action'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request.'
    ]);
    exit();
}

$action = $_POST['action'];
$user_id = $_SESSION['user_id'];

// Get or create active cart
function getCartId($conn, $user_id) {
    // Check if user has an active cart
    $cart_sql = "SELECT cartID FROM cart WHERE userID = ? AND orderID IS NULL";
    $cart_stmt = $conn->prepare($cart_sql);
    $cart_stmt->bind_param("i", $user_id);
    $cart_stmt->execute();
    $cart_result = $cart_stmt->get_result();
    
    if ($cart_result->num_rows === 0) {
        // Create new cart
        $new_cart_sql = "INSERT INTO cart (userID, total_amount) VALUES (?, 0)";
        $new_cart_stmt = $conn->prepare($new_cart_sql);
        $new_cart_stmt->bind_param("i", $user_id);
        $new_cart_stmt->execute();
        $cart_id = $new_cart_stmt->insert_id;
        $new_cart_stmt->close();
    } else {
        $cart = $cart_result->fetch_assoc();
        $cart_id = $cart['cartID'];
    }
    
    $cart_stmt->close();
    
    return $cart_id;
}

// Get cart item count
function getCartCount($conn, $user_id) {
    $count_sql = "SELECT SUM(ci.quantity) as item_count 
                  FROM cart_items ci 
                  JOIN cart c ON ci.cartID = c.cartID 
                  WHERE c.userID = ? AND c.orderID IS NULL";
    $count_stmt = $conn->prepare($count_sql);
    $count_stmt->bind_param("i", $user_id);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $count_row = $count_result->fetch_assoc();
    $count = $count_row['item_count'] ?: 0;
    
    $count_stmt->close();
    
    return $count;
}

// Update cart total
function updateCartTotal($conn, $cart_id) {
    $total_sql = "SELECT SUM(ci.quantity * ci.price) as total FROM cart_items ci WHERE ci.cartID = ?";
    $total_stmt = $conn->prepare($total_sql);
    $total_stmt->bind_param("i", $cart_id);
    $total_stmt->execute();
    $total_result = $total_stmt->get_result();
    $total_row = $total_result->fetch_assoc();
    $total_amount = $total_row['total'] ?: 0;
    
    $update_sql = "UPDATE cart SET total_amount = ? WHERE cartID = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("di", $total_amount, $cart_id);
    $update_stmt->execute();
    
    $update_stmt->close();
    $total_stmt->close();
}

// Process based on action
if ($action === 'add') {
    // Add item to cart
    if (!isset($_POST['product_id']) || !isset($_POST['quantity'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid product data.'
        ]);
        exit();
    }
    
    $product_id = $_POST['product_id'];
    $quantity = max(1, intval($_POST['quantity'])); // Ensure minimum quantity of 1
    
    // Check if product exists and is in stock
    $product_sql = "SELECT * FROM pet_food_and_accessories WHERE productID = ?";
    $product_stmt = $conn->prepare($product_sql);
    $product_stmt->bind_param("i", $product_id);
    $product_stmt->execute();
    $product_result = $product_stmt->get_result();
    
    if ($product_result->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Product not found.'
        ]);
        exit();
    }
    
    $product = $product_result->fetch_assoc();
    
    if ($product['stock'] < $quantity) {
        echo json_encode([
            'success' => false,
            'message' => 'Not enough stock available. Only ' . $product['stock'] . ' units available.'
        ]);
        exit();
    }
    
    $product_stmt->close();
    
    // Get cart ID
    $cart_id = getCartId($conn, $user_id);
    
    // Check if product already in cart
    $check_sql = "SELECT * FROM cart_items WHERE cartID = ? AND productID = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ii", $cart_id, $product_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        // Update existing cart item
        $cart_item = $check_result->fetch_assoc();
        $new_quantity = $cart_item['quantity'] + $quantity;
        
        // Check if new quantity exceeds stock
        if ($new_quantity > $product['stock']) {
            echo json_encode([
                'success' => false,
                'message' => 'Cannot add more of this item. Maximum stock reached.'
            ]);
            exit();
        }
        
        $update_sql = "UPDATE cart_items SET quantity = ? WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("ii", $new_quantity, $cart_item['id']);
        $update_stmt->execute();
        $update_stmt->close();
    } else {
        // Add new cart item
        $insert_sql = "INSERT INTO cart_items (cartID, productID, quantity, price) VALUES (?, ?, ?, ?)";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("iiid", $cart_id, $product_id, $quantity, $product['price']);
        $insert_stmt->execute();
        $insert_stmt->close();
    }
    
    $check_stmt->close();
    
    // Update cart total
    updateCartTotal($conn, $cart_id);
    
    // Get updated cart count
    $cart_count = getCartCount($conn, $user_id);
    
    echo json_encode([
        'success' => true,
        'message' => 'Product added to cart successfully.',
        'cart_count' => $cart_count
    ]);
} elseif ($action === 'remove') {
    // Remove item from cart
    if (!isset($_POST['cart_item_id'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid cart item.'
        ]);
        exit();
    }
    
    $cart_item_id = $_POST['cart_item_id'];
    
    // Get cart ID
    $cart_sql = "SELECT c.cartID FROM cart c 
                 JOIN cart_items ci ON c.cartID = ci.cartID 
                 WHERE ci.id = ? AND c.userID = ? AND c.orderID IS NULL";
    $cart_stmt = $conn->prepare($cart_sql);
    $cart_stmt->bind_param("ii", $cart_item_id, $user_id);
    $cart_stmt->execute();
    $cart_result = $cart_stmt->get_result();
    
    if ($cart_result->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Cart item not found.'
        ]);
        exit();
    }
    
    $cart = $cart_result->fetch_assoc();
    $cart_id = $cart['cartID'];
    
    $cart_stmt->close();
    
    // Remove cart item
    $delete_sql = "DELETE FROM cart_items WHERE id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("i", $cart_item_id);
    $delete_stmt->execute();
    $delete_stmt->close();
    
    // Update cart total
    updateCartTotal($conn, $cart_id);
    
    // Get updated cart count
    $cart_count = getCartCount($conn, $user_id);
    
    echo json_encode([
        'success' => true,
        'message' => 'Item removed from cart.',
        'cart_count' => $cart_count
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid action.'
    ]);
}

// Close database connection
$conn->close();
?>