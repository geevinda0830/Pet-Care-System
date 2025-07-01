<?php
// cart_process.php - Complete Cart Management System
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Set content type to JSON
header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in
if (!isset($_SESSION['user_id']) && !isset($_SESSION['userID'])) {
    echo json_encode([
        'success' => false,
        'message' => 'You must be logged in to manage your cart.',
        'redirect' => 'login.php'
    ]);
    exit();
}

// Include database connection
require_once 'config/db_connect.php';

// Get user ID (handle both session variables)
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : $_SESSION['userID'];

// Check if action is provided
if (!isset($_POST['action'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request - no action specified.'
    ]);
    exit();
}

$action = $_POST['action'];

// =============================================
// HELPER FUNCTIONS
// =============================================

// Get or create active cart
function getCartId($conn, $user_id) {
    try {
        // Check if user has an active cart
        $cart_sql = "SELECT cartID FROM cart WHERE userID = ? AND (orderID IS NULL OR orderID = 0)";
        $cart_stmt = $conn->prepare($cart_sql);
        if (!$cart_stmt) {
            throw new Exception("Database error: " . $conn->error);
        }
        
        $cart_stmt->bind_param("i", $user_id);
        $cart_stmt->execute();
        $cart_result = $cart_stmt->get_result();
        
        if ($cart_result->num_rows === 0) {
            // Create new cart
            $new_cart_sql = "INSERT INTO cart (userID, total_amount, created_at) VALUES (?, 0, NOW())";
            $new_cart_stmt = $conn->prepare($new_cart_sql);
            if (!$new_cart_stmt) {
                throw new Exception("Database error: " . $conn->error);
            }
            
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
        
    } catch (Exception $e) {
        error_log("Error in getCartId: " . $e->getMessage());
        return false;
    }
}

// Get cart item count
function getCartCount($conn, $user_id) {
    try {
        $count_sql = "SELECT SUM(ci.quantity) as item_count 
                      FROM cart_items ci 
                      JOIN cart c ON ci.cartID = c.cartID 
                      WHERE c.userID = ? AND (c.orderID IS NULL OR c.orderID = 0)";
        $count_stmt = $conn->prepare($count_sql);
        if (!$count_stmt) {
            return 0;
        }
        
        $count_stmt->bind_param("i", $user_id);
        $count_stmt->execute();
        $count_result = $count_stmt->get_result();
        $count_row = $count_result->fetch_assoc();
        $count = $count_row['item_count'] ?: 0;
        
        $count_stmt->close();
        return $count;
        
    } catch (Exception $e) {
        error_log("Error in getCartCount: " . $e->getMessage());
        return 0;
    }
}

// Update cart total
function updateCartTotal($conn, $cart_id) {
    try {
        $total_sql = "SELECT SUM(ci.quantity * ci.price) as total FROM cart_items ci WHERE ci.cartID = ?";
        $total_stmt = $conn->prepare($total_sql);
        if (!$total_stmt) {
            throw new Exception("Database error: " . $conn->error);
        }
        
        $total_stmt->bind_param("i", $cart_id);
        $total_stmt->execute();
        $total_result = $total_stmt->get_result();
        $total_row = $total_result->fetch_assoc();
        $total_amount = $total_row['total'] ?: 0;
        
        $update_sql = "UPDATE cart SET total_amount = ?, updated_at = NOW() WHERE cartID = ?";
        $update_stmt = $conn->prepare($update_sql);
        if (!$update_stmt) {
            throw new Exception("Database error: " . $conn->error);
        }
        
        $update_stmt->bind_param("di", $total_amount, $cart_id);
        $update_stmt->execute();
        
        $update_stmt->close();
        $total_stmt->close();
        
        return $total_amount;
        
    } catch (Exception $e) {
        error_log("Error in updateCartTotal: " . $e->getMessage());
        return 0;
    }
}

// Get product details
function getProduct($conn, $product_id) {
    try {
        $product_sql = "SELECT * FROM pet_food_and_accessories WHERE productID = ?";
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
                throw new Exception('Product ID and quantity are required.');
            }
            
            $product_id = intval($_POST['product_id']);
            $quantity = max(1, intval($_POST['quantity'])); // Ensure minimum quantity of 1
            
            // Get product details
            $product = getProduct($conn, $product_id);
            if (!$product) {
                throw new Exception('Product not found.');
            }
            
            // Check stock availability
            if ($product['stock'] < $quantity) {
                throw new Exception('Insufficient stock. Only ' . $product['stock'] . ' units available.');
            }
            
            // Get cart ID
            $cart_id = getCartId($conn, $user_id);
            if (!$cart_id) {
                throw new Exception('Unable to create or access cart.');
            }
            
            // Check if product already exists in cart
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
                    throw new Exception('Cannot add more items. Total would exceed available stock (' . $product['stock'] . ' units).');
                }
                
                $update_sql = "UPDATE cart_items SET quantity = ?, updated_at = NOW() WHERE id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("ii", $new_quantity, $cart_item['id']);
                $update_stmt->execute();
                $update_stmt->close();
                
                $message = 'Cart updated! Quantity increased to ' . $new_quantity;
            } else {
                // Add new cart item
                $insert_sql = "INSERT INTO cart_items (cartID, productID, quantity, price, created_at) VALUES (?, ?, ?, ?, NOW())";
                $insert_stmt = $conn->prepare($insert_sql);
                $insert_stmt->bind_param("iiid", $cart_id, $product_id, $quantity, $product['price']);
                $insert_stmt->execute();
                $insert_stmt->close();
                
                $message = 'Product added to cart successfully!';
            }
            
            $check_stmt->close();
            
            // Update cart total
            $new_total = updateCartTotal($conn, $cart_id);
            $cart_count = getCartCount($conn, $user_id);
            
            echo json_encode([
                'success' => true,
                'message' => $message,
                'cart_count' => $cart_count,
                'cart_total' => number_format($new_total, 2),
                'product_name' => $product['name']
            ]);
            break;
            
        case 'update':
            // UPDATE CART ITEM QUANTITY
            if (!isset($_POST['item_id']) || !isset($_POST['quantity'])) {
                throw new Exception('Item ID and quantity are required.');
            }
            
            $item_id = intval($_POST['item_id']);
            $quantity = max(0, intval($_POST['quantity'])); // Allow 0 to remove item
            
            // Get cart item details
            $item_sql = "SELECT ci.*, p.stock, p.name, c.userID 
                         FROM cart_items ci 
                         JOIN pet_food_and_accessories p ON ci.productID = p.productID 
                         JOIN cart c ON ci.cartID = c.cartID 
                         WHERE ci.id = ?";
            $item_stmt = $conn->prepare($item_sql);
            $item_stmt->bind_param("i", $item_id);
            $item_stmt->execute();
            $item_result = $item_stmt->get_result();
            
            if ($item_result->num_rows === 0) {
                throw new Exception('Cart item not found.');
            }
            
            $item = $item_result->fetch_assoc();
            
            // Verify ownership
            if ($item['userID'] != $user_id) {
                throw new Exception('Unauthorized access to cart item.');
            }
            
            if ($quantity === 0) {
                // Remove item from cart
                $delete_sql = "DELETE FROM cart_items WHERE id = ?";
                $delete_stmt = $conn->prepare($delete_sql);
                $delete_stmt->bind_param("i", $item_id);
                $delete_stmt->execute();
                $delete_stmt->close();
                
                $message = $item['name'] . ' removed from cart.';
            } else {
                // Check stock availability
                if ($quantity > $item['stock']) {
                    throw new Exception('Insufficient stock. Only ' . $item['stock'] . ' units available.');
                }
                
                // Update quantity
                $update_sql = "UPDATE cart_items SET quantity = ?, updated_at = NOW() WHERE id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("ii", $quantity, $item_id);
                $update_stmt->execute();
                $update_stmt->close();
                
                $message = 'Cart updated successfully.';
            }
            
            $item_stmt->close();
            
            // Update cart total
            $new_total = updateCartTotal($conn, $item['cartID']);
            $cart_count = getCartCount($conn, $user_id);
            
            echo json_encode([
                'success' => true,
                'message' => $message,
                'cart_count' => $cart_count,
                'cart_total' => number_format($new_total, 2)
            ]);
            break;
            
        case 'remove':
            // REMOVE ITEM FROM CART
            if (!isset($_POST['item_id'])) {
                throw new Exception('Item ID is required.');
            }
            
            $item_id = intval($_POST['item_id']);
            
            // Get cart item details for verification
            $item_sql = "SELECT ci.*, p.name, c.userID, c.cartID 
                         FROM cart_items ci 
                         JOIN pet_food_and_accessories p ON ci.productID = p.productID 
                         JOIN cart c ON ci.cartID = c.cartID 
                         WHERE ci.id = ?";
            $item_stmt = $conn->prepare($item_sql);
            $item_stmt->bind_param("i", $item_id);
            $item_stmt->execute();
            $item_result = $item_stmt->get_result();
            
            if ($item_result->num_rows === 0) {
                throw new Exception('Cart item not found.');
            }
            
            $item = $item_result->fetch_assoc();
            
            // Verify ownership
            if ($item['userID'] != $user_id) {
                throw new Exception('Unauthorized access to cart item.');
            }
            
            // Remove item
            $delete_sql = "DELETE FROM cart_items WHERE id = ?";
            $delete_stmt = $conn->prepare($delete_sql);
            $delete_stmt->bind_param("i", $item_id);
            $delete_stmt->execute();
            $delete_stmt->close();
            $item_stmt->close();
            
            // Update cart total
            $new_total = updateCartTotal($conn, $item['cartID']);
            $cart_count = getCartCount($conn, $user_id);
            
            echo json_encode([
                'success' => true,
                'message' => $item['name'] . ' removed from cart.',
                'cart_count' => $cart_count,
                'cart_total' => number_format($new_total, 2)
            ]);
            break;
            
        case 'clear':
            // CLEAR ENTIRE CART
            $cart_id = getCartId($conn, $user_id);
            if (!$cart_id) {
                throw new Exception('Cart not found.');
            }
            
            // Remove all items from cart
            $clear_sql = "DELETE FROM cart_items WHERE cartID = ?";
            $clear_stmt = $conn->prepare($clear_sql);
            $clear_stmt->bind_param("i", $cart_id);
            $clear_stmt->execute();
            $clear_stmt->close();
            
            // Update cart total to 0
            $update_sql = "UPDATE cart SET total_amount = 0, updated_at = NOW() WHERE cartID = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("i", $cart_id);
            $update_stmt->execute();
            $update_stmt->close();
            
            echo json_encode([
                'success' => true,
                'message' => 'Cart cleared successfully.',
                'cart_count' => 0,
                'cart_total' => '0.00'
            ]);
            break;
            
        case 'get_count':
            // GET CART COUNT
            $cart_count = getCartCount($conn, $user_id);
            echo json_encode([
                'success' => true,
                'cart_count' => $cart_count
            ]);
            break;
            
        case 'get_total':
            // GET CART TOTAL
            $cart_id = getCartId($conn, $user_id);
            if ($cart_id) {
                $total = updateCartTotal($conn, $cart_id);
                $count = getCartCount($conn, $user_id);
                echo json_encode([
                    'success' => true,
                    'cart_total' => number_format($total, 2),
                    'cart_count' => $count
                ]);
            } else {
                echo json_encode([
                    'success' => true,
                    'cart_total' => '0.00',
                    'cart_count' => 0
                ]);
            }
            break;
            
        default:
            throw new Exception('Invalid action specified.');
    }
    
} catch (Exception $e) {
    // Log error for debugging
    error_log("Cart Process Error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

// Close database connection
if (isset($conn)) {
    $conn->close();
}
?>