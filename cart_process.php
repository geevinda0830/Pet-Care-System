<?php
/**
 * Complete Cart Processing System
 * Replace your entire cart_process.php file with this code
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
        'message' => $message
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
    sendResponse(false, 'You must be logged in to manage your cart.');
}

// Include database connection
require_once 'config/db_connect.php';

// Check database connection
if (!$conn) {
    sendResponse(false, 'Database connection failed. Please try again later.');
}

// Check if action is provided
if (!isset($_POST['action'])) {
    sendResponse(false, 'Invalid request - no action specified.');
}

$action = trim($_POST['action']);

try {
    switch ($action) {
        case 'add':
            // ADD ITEM TO CART
            if (!isset($_POST['product_id']) || !isset($_POST['quantity'])) {
                sendResponse(false, 'Product ID and quantity are required.');
            }
            
            $product_id = intval($_POST['product_id']);
            $quantity = max(1, intval($_POST['quantity']));
            
            if ($product_id <= 0) {
                sendResponse(false, 'Invalid product ID.');
            }
            
            // Get product details
            $product_sql = "SELECT productID, name, price, stock FROM pet_food_and_accessories WHERE productID = ? LIMIT 1";
            $product_stmt = $conn->prepare($product_sql);
            
            if (!$product_stmt) {
                sendResponse(false, 'Database error occurred.');
            }
            
            $product_stmt->bind_param("i", $product_id);
            $product_stmt->execute();
            $product_result = $product_stmt->get_result();
            
            if ($product_result->num_rows === 0) {
                $product_stmt->close();
                sendResponse(false, 'Product not found.');
            }
            
            $product = $product_result->fetch_assoc();
            $product_stmt->close();
            
            // Check stock availability
            if ($product['stock'] < $quantity) {
                sendResponse(false, "Insufficient stock. Only {$product['stock']} units available.");
            }
            
            // Get or create cart
            $cart_sql = "SELECT cartID FROM cart WHERE userID = ? AND (orderID IS NULL OR orderID = 0) LIMIT 1";
            $cart_stmt = $conn->prepare($cart_sql);
            
            if (!$cart_stmt) {
                sendResponse(false, 'Database error occurred.');
            }
            
            $cart_stmt->bind_param("i", $user_id);
            $cart_stmt->execute();
            $cart_result = $cart_stmt->get_result();
            
            if ($cart_result->num_rows === 0) {
                // Create new cart
                $new_cart_sql = "INSERT INTO cart (userID, total_amount) VALUES (?, 0)";
                $new_cart_stmt = $conn->prepare($new_cart_sql);
                
                if (!$new_cart_stmt) {
                    $cart_stmt->close();
                    sendResponse(false, 'Database error occurred.');
                }
                
                $new_cart_stmt->bind_param("i", $user_id);
                
                if (!$new_cart_stmt->execute()) {
                    $new_cart_stmt->close();
                    $cart_stmt->close();
                    sendResponse(false, 'Failed to create cart.');
                }
                
                $cart_id = $new_cart_stmt->insert_id;
                $new_cart_stmt->close();
            } else {
                $cart = $cart_result->fetch_assoc();
                $cart_id = $cart['cartID'];
            }
            
            $cart_stmt->close();
            
            // Check if product already exists in cart
            $check_sql = "SELECT id, quantity FROM cart_items WHERE cartID = ? AND productID = ? LIMIT 1";
            $check_stmt = $conn->prepare($check_sql);
            
            if (!$check_stmt) {
                sendResponse(false, 'Database error occurred.');
            }
            
            $check_stmt->bind_param("ii", $cart_id, $product_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                // Update existing cart item
                $cart_item = $check_result->fetch_assoc();
                $new_quantity = $cart_item['quantity'] + $quantity;
                
                // Check if new quantity exceeds stock
                if ($new_quantity > $product['stock']) {
                    $check_stmt->close();
                    sendResponse(false, "Cannot add more items. Total would exceed available stock ({$product['stock']} units).");
                }
                
                $update_sql = "UPDATE cart_items SET quantity = ? WHERE id = ?";
                $update_stmt = $conn->prepare($update_sql);
                
                if (!$update_stmt) {
                    $check_stmt->close();
                    sendResponse(false, 'Database error occurred.');
                }
                
                $update_stmt->bind_param("ii", $new_quantity, $cart_item['id']);
                
                if (!$update_stmt->execute()) {
                    $update_stmt->close();
                    $check_stmt->close();
                    sendResponse(false, 'Failed to update cart item.');
                }
                
                $update_stmt->close();
                $message = "Cart updated! Quantity increased to {$new_quantity}.";
                
            } else {
                // Add new cart item
                $insert_sql = "INSERT INTO cart_items (cartID, productID, quantity, price) VALUES (?, ?, ?, ?)";
                $insert_stmt = $conn->prepare($insert_sql);
                
                if (!$insert_stmt) {
                    $check_stmt->close();
                    sendResponse(false, 'Database error occurred.');
                }
                
                $insert_stmt->bind_param("iiid", $cart_id, $product_id, $quantity, $product['price']);
                
                if (!$insert_stmt->execute()) {
                    $insert_stmt->close();
                    $check_stmt->close();
                    sendResponse(false, 'Failed to add item to cart.');
                }
                
                $insert_stmt->close();
                $message = "Product added to cart successfully!";
            }
            
            $check_stmt->close();
            
            // Update cart total
            $total_sql = "SELECT SUM(ci.quantity * ci.price) as total FROM cart_items ci WHERE ci.cartID = ?";
            $total_stmt = $conn->prepare($total_sql);
            
            if ($total_stmt) {
                $total_stmt->bind_param("i", $cart_id);
                $total_stmt->execute();
                $total_result = $total_stmt->get_result();
                $total_row = $total_result->fetch_assoc();
                $total_amount = $total_row['total'] ? $total_row['total'] : 0;
                
                $update_total_sql = "UPDATE cart SET total_amount = ? WHERE cartID = ?";
                $update_total_stmt = $conn->prepare($update_total_sql);
                
                if ($update_total_stmt) {
                    $update_total_stmt->bind_param("di", $total_amount, $cart_id);
                    $update_total_stmt->execute();
                    $update_total_stmt->close();
                }
                
                $total_stmt->close();
            }
            
            // Get cart count
            $count_sql = "SELECT SUM(ci.quantity) as cart_count FROM cart_items ci WHERE ci.cartID = ?";
            $count_stmt = $conn->prepare($count_sql);
            $cart_count = 0;
            
            if ($count_stmt) {
                $count_stmt->bind_param("i", $cart_id);
                $count_stmt->execute();
                $count_result = $count_stmt->get_result();
                $count_row = $count_result->fetch_assoc();
                $cart_count = $count_row['cart_count'] ? $count_row['cart_count'] : 0;
                $count_stmt->close();
            }
            
            sendResponse(true, $message, [
                'cart_count' => $cart_count,
                'product_name' => $product['name']
            ]);
            
            break;
            
        case 'remove':
            // REMOVE ITEM FROM CART
            if (!isset($_POST['cart_item_id'])) {
                sendResponse(false, 'Cart item ID is required.');
            }
            
            $cart_item_id = intval($_POST['cart_item_id']);
            
            // Verify item belongs to user and remove it
            $remove_sql = "DELETE ci FROM cart_items ci 
                          JOIN cart c ON ci.cartID = c.cartID 
                          WHERE ci.id = ? AND c.userID = ?";
            $remove_stmt = $conn->prepare($remove_sql);
            
            if (!$remove_stmt) {
                sendResponse(false, 'Database error occurred.');
            }
            
            $remove_stmt->bind_param("ii", $cart_item_id, $user_id);
            
            if (!$remove_stmt->execute()) {
                $remove_stmt->close();
                sendResponse(false, 'Failed to remove item from cart.');
            }
            
            $remove_stmt->close();
            
            sendResponse(true, 'Item removed from cart successfully.');
            
            break;
            
        case 'clear':
            // CLEAR ENTIRE CART
            $clear_sql = "DELETE ci FROM cart_items ci 
                         JOIN cart c ON ci.cartID = c.cartID 
                         WHERE c.userID = ? AND (c.orderID IS NULL OR c.orderID = 0)";
            $clear_stmt = $conn->prepare($clear_sql);
            
            if (!$clear_stmt) {
                sendResponse(false, 'Database error occurred.');
            }
            
            $clear_stmt->bind_param("i", $user_id);
            
            if (!$clear_stmt->execute()) {
                $clear_stmt->close();
                sendResponse(false, 'Failed to clear cart.');
            }
            
            $clear_stmt->close();
            
            sendResponse(true, 'Cart cleared successfully.');
            
            break;
            
        default:
            sendResponse(false, 'Invalid action specified.');
            break;
    }
    
} catch (Exception $e) {
    error_log("Cart process error: " . $e->getMessage());
    sendResponse(false, 'An unexpected error occurred. Please try again.');
}

// Close database connection
if ($conn) {
    $conn->close();
}
?>