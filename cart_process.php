<?php
/**
 * Complete Cart Processing System
 * Handles all cart operations: add, remove, update, clear
 */

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Set content type to JSON
header('Content-Type: application/json');

// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in JSON response

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
    sendResponse(false, 'You must be logged in to manage your cart.', [
        'redirect' => 'login.php'
    ]);
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

// =============================================
// HELPER FUNCTIONS
// =============================================

/**
 * Get or create active cart for user
 */
function getCartId($conn, $user_id) {
    try {
        // Check if user has an active cart (not associated with an order)
        $cart_sql = "SELECT cartID FROM cart WHERE userID = ? AND (orderID IS NULL OR orderID = 0) LIMIT 1";
        $cart_stmt = $conn->prepare($cart_sql);
        
        if (!$cart_stmt) {
            throw new Exception("Database prepare error: " . $conn->error);
        }
        
        $cart_stmt->bind_param("i", $user_id);
        $cart_stmt->execute();
        $cart_result = $cart_stmt->get_result();
        
        if ($cart_result->num_rows === 0) {
            // Create new cart
            $new_cart_sql = "INSERT INTO cart (userID, total_amount, created_at, updated_at) VALUES (?, 0, NOW(), NOW())";
            $new_cart_stmt = $conn->prepare($new_cart_sql);
            
            if (!$new_cart_stmt) {
                throw new Exception("Database prepare error: " . $conn->error);
            }
            
            $new_cart_stmt->bind_param("i", $user_id);
            
            if (!$new_cart_stmt->execute()) {
                throw new Exception("Failed to create cart: " . $new_cart_stmt->error);
            }
            
            $cart_id = $new_cart_stmt->insert_id;
            $new_cart_stmt->close();
            
        } else {
            $cart = $cart_result->fetch_assoc();
            $cart_id = $cart['cartID'];
        }
        
        $cart_stmt->close();
        return $cart_id;
        
    } catch (Exception $e) {
        error_log("Error in getCartId: " . $e->getMessage());
        return false;
    }
}

/**
 * Get cart item count for user
 */
function getCartCount($conn, $user_id) {
    try {
        $count_sql = "SELECT COALESCE(SUM(ci.quantity), 0) as item_count 
                      FROM cart c 
                      LEFT JOIN cart_items ci ON c.cartID = ci.cartID 
                      WHERE c.userID = ? AND (c.orderID IS NULL OR c.orderID = 0)";
        $count_stmt = $conn->prepare($count_sql);
        
        if (!$count_stmt) {
            return 0;
        }
        
        $count_stmt->bind_param("i", $user_id);
        $count_stmt->execute();
        $count_result = $count_stmt->get_result();
        $count_row = $count_result->fetch_assoc();
        $count = intval($count_row['item_count']);
        
        $count_stmt->close();
        return $count;
        
    } catch (Exception $e) {
        error_log("Error in getCartCount: " . $e->getMessage());
        return 0;
    }
}

/**
 * Update cart total amount
 */
function updateCartTotal($conn, $cart_id) {
    try {
        // Calculate total from cart items
        $total_sql = "SELECT COALESCE(SUM(ci.quantity * ci.price), 0) as total FROM cart_items ci WHERE ci.cartID = ?";
        $total_stmt = $conn->prepare($total_sql);
        
        if (!$total_stmt) {
            throw new Exception("Database prepare error: " . $conn->error);
        }
        
        $total_stmt->bind_param("i", $cart_id);
        $total_stmt->execute();
        $total_result = $total_stmt->get_result();
        $total_row = $total_result->fetch_assoc();
        $total_amount = floatval($total_row['total']);
        
        // Update cart total
        $update_sql = "UPDATE cart SET total_amount = ?, updated_at = NOW() WHERE cartID = ?";
        $update_stmt = $conn->prepare($update_sql);
        
        if (!$update_stmt) {
            throw new Exception("Database prepare error: " . $conn->error);
        }
        
        $update_stmt->bind_param("di", $total_amount, $cart_id);
        
        if (!$update_stmt->execute()) {
            throw new Exception("Failed to update cart total: " . $update_stmt->error);
        }
        
        $update_stmt->close();
        $total_stmt->close();
        
        return $total_amount;
        
    } catch (Exception $e) {
        error_log("Error in updateCartTotal: " . $e->getMessage());
        return 0;
    }
}

/**
 * Get product details and validate
 */
function getProduct($conn, $product_id) {
    try {
        $product_sql = "SELECT productID, name, price, stock, image, brand FROM pet_food_and_accessories WHERE productID = ? LIMIT 1";
        $product_stmt = $conn->prepare($product_sql);
        
        if (!$product_stmt) {
            return false;
        }
        
        $product_stmt->bind_param("i", $product_id);
        $product_stmt->execute();
        $product_result = $product_stmt->get_result();
        
        if ($product_result->num_rows === 0) {
            $product_stmt->close();
            return false;
        }
        
        $product = $product_result->fetch_assoc();
        $product_stmt->close();
        return $product;
        
    } catch (Exception $e) {
        error_log("Error in getProduct: " . $e->getMessage());
        return false;
    }
}

// =============================================
// MAIN PROCESSING LOGIC
// =============================================

try {
    switch ($action) {
        
        case 'add':
            // ADD ITEM TO CART
            if (!isset($_POST['product_id']) || !isset($_POST['quantity'])) {
                sendResponse(false, 'Product ID and quantity are required.');
            }
            
            $product_id = intval($_POST['product_id']);
            $quantity = max(1, intval($_POST['quantity'])); // Ensure minimum quantity of 1
            
            if ($product_id <= 0) {
                sendResponse(false, 'Invalid product ID.');
            }
            
            // Get product details
            $product = getProduct($conn, $product_id);
            if (!$product) {
                sendResponse(false, 'Product not found or no longer available.');
            }
            
            // Check stock availability
            if ($product['stock'] < $quantity) {
                sendResponse(false, "Insufficient stock. Only {$product['stock']} units available.");
            }
            
            // Get cart ID
            $cart_id = getCartId($conn, $user_id);
            if (!$cart_id) {
                sendResponse(false, 'Unable to create or access cart.');
            }
            
            // Check if product already exists in cart
            $check_sql = "SELECT id, quantity FROM cart_items WHERE cartID = ? AND productID = ? LIMIT 1";
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
                    $check_stmt->close();
                    sendResponse(false, "Cannot add more items. Total would exceed available stock ({$product['stock']} units).");
                }
                
                $update_sql = "UPDATE cart_items SET quantity = ?, updated_at = NOW() WHERE id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("ii", $new_quantity, $cart_item['id']);
                $update_stmt->execute();
                $update_stmt->close();
                
                $message = "Cart updated! Quantity increased to {$new_quantity}.";
                
            } else {
                // Add new cart item
                $insert_sql = "INSERT INTO cart_items (cartID, productID, quantity, price, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())";
                $insert_stmt = $conn->prepare($insert_sql);
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
            
            // Update cart total and get counts
            $cart_total = updateCartTotal($conn, $cart_id);
            $cart_count = getCartCount($conn, $user_id);
            
            sendResponse(true, $message, [
                'cart_count' => $cart_count,
                'cart_total' => $cart_total,
                'product_name' => $product['name']
            ]);
            break;
            
        case 'remove':
            // REMOVE ITEM FROM CART
            if (!isset($_POST['cart_item_id'])) {
                sendResponse(false, 'Cart item ID is required.');
            }
            
            $cart_item_id = intval($_POST['cart_item_id']);
            
            if ($cart_item_id <= 0) {
                sendResponse(false, 'Invalid cart item ID.');
            }
            
            // Verify the cart item belongs to the user
            $verify_sql = "SELECT ci.id, p.name 
                          FROM cart_items ci 
                          JOIN cart c ON ci.cartID = c.cartID 
                          JOIN pet_food_and_accessories p ON ci.productID = p.productID
                          WHERE ci.id = ? AND c.userID = ? LIMIT 1";
            $verify_stmt = $conn->prepare($verify_sql);
            $verify_stmt->bind_param("ii", $cart_item_id, $user_id);
            $verify_stmt->execute();
            $verify_result = $verify_stmt->get_result();
            
            if ($verify_result->num_rows === 0) {
                $verify_stmt->close();
                sendResponse(false, 'Cart item not found or access denied.');
            }
            
            $item_data = $verify_result->fetch_assoc();
            $verify_stmt->close();
            
            // Delete the cart item
            $delete_sql = "DELETE FROM cart_items WHERE id = ?";
            $delete_stmt = $conn->prepare($delete_sql);
            $delete_stmt->bind_param("i", $cart_item_id);
            
            if (!$delete_stmt->execute()) {
                $delete_stmt->close();
                sendResponse(false, 'Failed to remove item from cart.');
            }
            
            $delete_stmt->close();
            
            // Get cart ID and update totals
            $cart_id = getCartId($conn, $user_id);
            $cart_total = updateCartTotal($conn, $cart_id);
            $cart_count = getCartCount($conn, $user_id);
            
            sendResponse(true, "'{$item_data['name']}' removed from cart.", [
                'cart_count' => $cart_count,
                'cart_total' => $cart_total
            ]);
            break;
            
        case 'update':
            // UPDATE ITEM QUANTITY
            if (!isset($_POST['cart_item_id']) || !isset($_POST['quantity'])) {
                sendResponse(false, 'Cart item ID and quantity are required.');
            }
            
            $cart_item_id = intval($_POST['cart_item_id']);
            $new_quantity = max(1, intval($_POST['quantity']));
            
            if ($cart_item_id <= 0) {
                sendResponse(false, 'Invalid cart item ID.');
            }
            
            // Get cart item and product info
            $item_sql = "SELECT ci.id, ci.productID, p.name, p.stock 
                        FROM cart_items ci 
                        JOIN cart c ON ci.cartID = c.cartID 
                        JOIN pet_food_and_accessories p ON ci.productID = p.productID
                        WHERE ci.id = ? AND c.userID = ? LIMIT 1";
            $item_stmt = $conn->prepare($item_sql);
            $item_stmt->bind_param("ii", $cart_item_id, $user_id);
            $item_stmt->execute();
            $item_result = $item_stmt->get_result();
            
            if ($item_result->num_rows === 0) {
                $item_stmt->close();
                sendResponse(false, 'Cart item not found or access denied.');
            }
            
            $item_data = $item_result->fetch_assoc();
            $item_stmt->close();
            
            // Check stock availability
            if ($new_quantity > $item_data['stock']) {
                sendResponse(false, "Quantity exceeds available stock ({$item_data['stock']} units).");
            }
            
            // Update quantity
            $update_sql = "UPDATE cart_items SET quantity = ?, updated_at = NOW() WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("ii", $new_quantity, $cart_item_id);
            
            if (!$update_stmt->execute()) {
                $update_stmt->close();
                sendResponse(false, 'Failed to update quantity.');
            }
            
            $update_stmt->close();
            
            // Get cart ID and update totals
            $cart_id = getCartId($conn, $user_id);
            $cart_total = updateCartTotal($conn, $cart_id);
            $cart_count = getCartCount($conn, $user_id);
            
            sendResponse(true, "Quantity updated to {$new_quantity}.", [
                'cart_count' => $cart_count,
                'cart_total' => $cart_total,
                'item_name' => $item_data['name']
            ]);
            break;
            
        case 'clear':
            // CLEAR ENTIRE CART
            $cart_id = getCartId($conn, $user_id);
            if (!$cart_id) {
                sendResponse(false, 'No active cart found.');
            }
            
            // Delete all cart items
            $clear_sql = "DELETE FROM cart_items WHERE cartID = ?";
            $clear_stmt = $conn->prepare($clear_sql);
            $clear_stmt->bind_param("i", $cart_id);
            
            if (!$clear_stmt->execute()) {
                $clear_stmt->close();
                sendResponse(false, 'Failed to clear cart.');
            }
            
            $clear_stmt->close();
            
            // Update cart total
            $cart_total = updateCartTotal($conn, $cart_id);
            
            sendResponse(true, 'Cart cleared successfully.', [
                'cart_count' => 0,
                'cart_total' => 0
            ]);
            break;
            
        case 'get_count':
            // GET CART COUNT (for AJAX updates)
            $cart_count = getCartCount($conn, $user_id);
            sendResponse(true, 'Cart count retrieved.', [
                'cart_count' => $cart_count
            ]);
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