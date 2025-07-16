<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error_message'] = "You must be logged in to view your cart.";
    header("Location: login.php");
    exit();
}

// Include database connection
require_once 'config/db_connect.php';

// Check if there's an action to perform
if (isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'update') {
        // Update quantity
        $cart_item_id = $_POST['cart_item_id'];
        $quantity = $_POST['quantity'];
        
        if ($quantity < 1) {
            $quantity = 1; // Minimum quantity
        }
        
        // Update cart item quantity
        $update_sql = "UPDATE cart_items SET quantity = ? WHERE id = ? AND cartID IN (SELECT cartID FROM cart WHERE userID = ? AND (orderID IS NULL OR orderID = 0))";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("iii", $quantity, $cart_item_id, $_SESSION['user_id']);
        $update_stmt->execute();
        $update_stmt->close();
        
        // Update cart total
        updateCartTotal($conn, $_SESSION['user_id']);
        
        // Redirect to refresh page
        header("Location: cart.php");
        exit();
    } elseif ($action === 'remove') {
        // Remove item from cart
        $cart_item_id = $_POST['cart_item_id'];
        
        // Delete cart item
        $delete_sql = "DELETE FROM cart_items WHERE id = ? AND cartID IN (SELECT cartID FROM cart WHERE userID = ? AND (orderID IS NULL OR orderID = 0))";
        $delete_stmt = $conn->prepare($delete_sql);
        $delete_stmt->bind_param("ii", $cart_item_id, $_SESSION['user_id']);
        $delete_stmt->execute();
        $delete_stmt->close();
        
        // Update cart total
        updateCartTotal($conn, $_SESSION['user_id']);
        
        // Redirect to refresh page
        header("Location: cart.php");
        exit();
    } elseif ($action === 'clear') {
        // Clear entire cart
        $clear_sql = "DELETE FROM cart_items WHERE cartID IN (SELECT cartID FROM cart WHERE userID = ? AND (orderID IS NULL OR orderID = 0))";
        $clear_stmt = $conn->prepare($clear_sql);
        $clear_stmt->bind_param("i", $_SESSION['user_id']);
        $clear_stmt->execute();
        $clear_stmt->close();
        
        // Update cart total
        updateCartTotal($conn, $_SESSION['user_id']);
        
        // Redirect to refresh page
        header("Location: cart.php");
        exit();
    } elseif ($action === 'checkout') {
        // NEW CHECKOUT FUNCTIONALITY
        try {
            // Start transaction
            $conn->autocommit(FALSE);
            
            // Get user's active cart
            $cart_sql = "SELECT cartID FROM cart WHERE userID = ? AND (orderID IS NULL OR orderID = 0) LIMIT 1";
            $cart_stmt = $conn->prepare($cart_sql);
            $cart_stmt->bind_param("i", $_SESSION['user_id']);
            $cart_stmt->execute();
            $cart_result = $cart_stmt->get_result();
            
            if ($cart_result->num_rows === 0) {
                throw new Exception("Cart is empty");
            }
            
            $cart = $cart_result->fetch_assoc();
            $cart_id = $cart['cartID'];
            $cart_stmt->close();
            
            // Check if cart has items and calculate total
            $items_check_sql = "SELECT COUNT(*) as item_count, SUM(quantity * price) as total_amount FROM cart_items WHERE cartID = ?";
            $items_check_stmt = $conn->prepare($items_check_sql);
            $items_check_stmt->bind_param("i", $cart_id);
            $items_check_stmt->execute();
            $items_check_result = $items_check_stmt->get_result();
            $cart_summary = $items_check_result->fetch_assoc();
            $items_check_stmt->close();
            
            if ($cart_summary['item_count'] == 0) {
                throw new Exception("Cart is empty");
            }
            
            $total_amount = $cart_summary['total_amount'];
            
            // Get user address and contact for order
            $user_sql = "SELECT address, contact FROM pet_owner WHERE userID = ?";
            $user_stmt = $conn->prepare($user_sql);
            $user_stmt->bind_param("i", $_SESSION['user_id']);
            $user_stmt->execute();
            $user_result = $user_stmt->get_result();
            $user_data = $user_result->fetch_assoc();
            $user_address = $user_data['address'] ?? 'Not provided';
            $user_contact = $user_data['contact'] ?? 'Not provided';
            $user_stmt->close();
            
            // Create new order
            $order_sql = "INSERT INTO `order` (userID, status, date, time, address, contact) VALUES (?, 'pending', CURDATE(), CURTIME(), ?, ?)";
            $order_stmt = $conn->prepare($order_sql);
            $order_stmt->bind_param("iss", $_SESSION['user_id'], $user_address, $user_contact);
            $order_stmt->execute();
            $order_id = $order_stmt->insert_id;
            $order_stmt->close();
            
            // Link cart to order
            $link_sql = "UPDATE cart SET orderID = ? WHERE cartID = ?";
            $link_stmt = $conn->prepare($link_sql);
            $link_stmt->bind_param("ii", $order_id, $cart_id);
            $link_stmt->execute();
            $link_stmt->close();
            
            // Commit transaction
            $conn->commit();
            $conn->autocommit(TRUE);
            
            // Redirect to payment page with cart ID
            header("Location: payment.php?cart_id=" . $cart_id);
            exit();
            
        } catch (Exception $e) {
            // Rollback transaction
            $conn->rollback();
            $conn->autocommit(TRUE);
            $_SESSION['error_message'] = "Checkout failed: " . $e->getMessage();
            header("Location: cart.php");
            exit();
        }
    }
}

// Function to update cart total
function updateCartTotal($conn, $user_id) {
    // Get cart ID
    $cart_sql = "SELECT cartID FROM cart WHERE userID = ? AND (orderID IS NULL OR orderID = 0)";
    $cart_stmt = $conn->prepare($cart_sql);
    $cart_stmt->bind_param("i", $user_id);
    $cart_stmt->execute();
    $cart_result = $cart_stmt->get_result();
    
    if ($cart_result->num_rows > 0) {
        $cart = $cart_result->fetch_assoc();
        $cart_id = $cart['cartID'];
        
        // Calculate new total
        $total_sql = "SELECT SUM(ci.quantity * ci.price) as total FROM cart_items ci WHERE ci.cartID = ?";
        $total_stmt = $conn->prepare($total_sql);
        $total_stmt->bind_param("i", $cart_id);
        $total_stmt->execute();
        $total_result = $total_stmt->get_result();
        $total_row = $total_result->fetch_assoc();
        $total_amount = $total_row['total'] ? $total_row['total'] : 0;
        
        $total_stmt->close();
    }
    
    $cart_stmt->close();
}

// Get cart items
function getCartItems($conn, $user_id) {
    // Check if user has an active cart
    $cart_sql = "SELECT cartID FROM cart WHERE userID = ? AND (orderID IS NULL OR orderID = 0)";
    $cart_stmt = $conn->prepare($cart_sql);
    $cart_stmt->bind_param("i", $user_id);
    $cart_stmt->execute();
    $cart_result = $cart_stmt->get_result();
    
    if ($cart_result->num_rows === 0) {
        // Create new cart
        $new_cart_sql = "INSERT INTO cart (userID) VALUES (?)";
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
    
    // Get cart items
    $items_sql = "SELECT ci.*, p.name, p.brand, p.image, p.stock 
                  FROM cart_items ci 
                  JOIN pet_food_and_accessories p ON ci.productID = p.productID 
                  WHERE ci.cartID = ?";
    $items_stmt = $conn->prepare($items_sql);
    $items_stmt->bind_param("i", $cart_id);
    $items_stmt->execute();
    $items_result = $items_stmt->get_result();
    
    $cart_items = [];
    $total_amount = 0;
    while ($row = $items_result->fetch_assoc()) {
        $row['subtotal'] = $row['quantity'] * $row['price'];
        $total_amount += $row['subtotal'];
        $cart_items[] = $row;
    }
    
    $items_stmt->close();
    
    return [
        'cart_id' => $cart_id,
        'items' => $cart_items,
        'total' => $total_amount
    ];
}

$cart_data = getCartItems($conn, $_SESSION['user_id']);

// Include header
include_once 'includes/header.php';
?>

<style>
/* Page Header Modern */
.page-header-modern {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 80px 0;
    color: white;
    position: relative;
    overflow: hidden;
}

.page-header-modern::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 20"><defs><radialGradient id="a" cx="50%" cy="0%" r="100%"><stop offset="0%" stop-color="white" stop-opacity="0.1"/><stop offset="100%" stop-color="white" stop-opacity="0"/></radialGradient></defs><ellipse cx="50" cy="0" rx="50" ry="20" fill="url(%23a)"/></svg>');
    background-size: 100% 100%;
}

.page-badge {
    background: rgba(255, 255, 255, 0.2);
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 0.9rem;
    font-weight: 600;
    display: inline-block;
    margin-bottom: 16px;
    backdrop-filter: blur(10px);
}

.page-title {
    font-size: 3.5rem;
    font-weight: 800;
    margin-bottom: 16px;
    line-height: 1.1;
}

.text-gradient {
    background: linear-gradient(135deg, #ffd89b 0%, #19547b 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.page-subtitle {
    font-size: 1.2rem;
    opacity: 0.9;
    margin-bottom: 0;
}

.page-actions {
    text-align: right;
}

/* Cart Section Modern */
.cart-section-modern {
    padding: 80px 0;
    background: #f8f9ff;
    min-height: 600px;
}

.empty-cart-modern {
    text-align: center;
    background: white;
    border-radius: 20px;
    padding: 80px 40px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(0, 0, 0, 0.05);
}

.empty-cart-icon {
    width: 120px;
    height: 120px;
    margin: 0 auto 32px;
    background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 3rem;
    color: #94a3b8;
}

.cart-container-modern {
    display: grid;
    grid-template-columns: 1fr 400px;
    gap: 40px;
    align-items: start;
}

.cart-items-container {
    background: white;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(0, 0, 0, 0.05);
}

.cart-header {
    padding: 32px 32px 24px;
    border-bottom: 1px solid #f1f5f9;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.cart-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: #1e293b;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 12px;
}

.cart-count {
    background: #667eea;
    color: white;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 0.85rem;
    font-weight: 600;
}

.cart-actions {
    display: flex;
    gap: 12px;
}

.btn-clear {
    background: #ef4444;
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 8px;
    font-size: 0.9rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 6px;
}

.btn-clear:hover {
    background: #dc2626;
    transform: translateY(-1px);
}

.cart-items-list {
    padding: 0 32px 32px;
}

.cart-item-modern {
    display: flex;
    align-items: center;
    gap: 20px;
    padding: 24px 0;
    border-bottom: 1px solid #f1f5f9;
}

.cart-item-modern:last-child {
    border-bottom: none;
}

.item-image {
    width: 100px;
    height: 100px;
    border-radius: 12px;
    overflow: hidden;
    flex-shrink: 0;
}

.item-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.image-placeholder {
    width: 100%;
    height: 100%;
    background: #f1f5f9;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #94a3b8;
    font-size: 2rem;
}

.item-details {
    flex: 1;
    min-width: 0;
}

.item-name {
    font-size: 1.1rem;
    font-weight: 600;
    color: #1e293b;
    margin: 0 0 6px 0;
    line-height: 1.3;
}

.item-brand {
    color: #64748b;
    font-size: 0.9rem;
    margin: 0 0 8px 0;
}

.item-price {
    color: #667eea;
    font-weight: 600;
    font-size: 0.95rem;
}

.item-quantity {
    display: flex;
    align-items: center;
    gap: 12px;
    margin: 0 20px;
}

.quantity-controls {
    display: flex;
    align-items: center;
    background: #f8fafc;
    border-radius: 8px;
    padding: 4px;
}

.quantity-btn {
    width: 32px;
    height: 32px;
    border: none;
    background: white;
    border-radius: 6px;
    color: #374151;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
}

.quantity-btn:hover {
    background: #667eea;
    color: white;
    transform: scale(1.05);
}

.quantity-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none;
}

.quantity-input {
    width: 50px;
    text-align: center;
    border: none;
    background: transparent;
    font-weight: 600;
    color: #1e293b;
    padding: 8px 4px;
}

.item-total {
    font-size: 1.1rem;
    font-weight: 700;
    color: #1e293b;
    margin-right: 20px;
    min-width: 80px;
    text-align: right;
}

.item-remove {
    flex-shrink: 0;
}

.btn-remove {
    width: 36px;
    height: 36px;
    border: none;
    background: #fef2f2;
    color: #ef4444;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
}

.btn-remove:hover {
    background: #ef4444;
    color: white;
    transform: scale(1.1);
}

/* Cart Summary */
.cart-summary-modern {
    background: white;
    border-radius: 20px;
    padding: 32px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(0, 0, 0, 0.05);
    height: fit-content;
    position: sticky;
    top: 24px;
}

.summary-title {
    font-size: 1.3rem;
    font-weight: 700;
    color: #1e293b;
    margin: 0 0 24px 0;
    padding-bottom: 16px;
    border-bottom: 1px solid #f1f5f9;
}

.summary-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
    color: #64748b;
}

.free-shipping {
    background: #dcfce7;
    color: #166534;
    padding: 2px 8px;
    border-radius: 6px;
    font-size: 0.8rem;
    font-weight: 600;
}

.summary-divider {
    margin: 20px 0;
    border-color: #e2e8f0;
}

.summary-total {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 1.3rem;
    font-weight: 700;
    color: #1e293b;
}

.promo-section {
    padding: 0 24px;
    margin-bottom: 24px;
}

.promo-header h6 {
    color: #1e293b;
    font-weight: 600;
    margin-bottom: 12px;
}

.promo-input {
    border: 2px solid #e2e8f0;
    border-radius: 8px 0 0 8px;
}

.promo-input:focus {
    border-color: #667eea;
    box-shadow: none;
}

.checkout-section {
    padding: 0 24px;
    margin-bottom: 24px;
}

.checkout-btn {
    padding: 16px;
    font-size: 1.1rem;
    font-weight: 600;
    margin-bottom: 12px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    border: none;
    border-radius: 12px;
    color: white;
    width: 100%;
    cursor: pointer;
    transition: all 0.3s ease;
}

.checkout-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
}

.checkout-note {
    text-align: center;
    color: #64748b;
    font-size: 0.8rem;
    margin: 0;
}

.payment-methods {
    padding: 20px 24px 24px;
    border-top: 1px solid #f1f5f9;
}

.payment-methods h6 {
    color: #64748b;
    font-size: 0.9rem;
    margin-bottom: 12px;
}

.payment-icons {
    display: flex;
    gap: 12px;
}

.payment-icons i {
    font-size: 1.5rem;
    color: #9ca3af;
}

.recommendations-section {
    padding: 80px 0;
    background: #f8f9ff;
}

.section-header {
    text-align: center;
    margin-bottom: 50px;
}

.section-header h3 {
    font-size: 2.2rem;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 12px;
}

.section-header p {
    color: #64748b;
    font-size: 1.1rem;
}

.recommendations-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 24px;
}

.product-card-mini {
    background: white;
    border-radius: 16px;
    padding: 20px;
    text-align: center;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(0, 0, 0, 0.05);
    transition: all 0.3s ease;
}

.product-card-mini:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
}

.product-card-mini .product-image {
    width: 80px;
    height: 80px;
    border-radius: 12px;
    overflow: hidden;
    margin: 0 auto 16px;
}

.product-card-mini .product-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.product-card-mini h6 {
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 8px;
}

.product-card-mini .price {
    color: #667eea;
    font-weight: 700;
    margin-bottom: 12px;
}

@media (max-width: 768px) {
    .page-title {
        font-size: 2.5rem;
    }
    
    .page-actions {
        text-align: left;
        margin-top: 24px;
    }
    
    .cart-item-modern {
        flex-wrap: wrap;
        gap: 16px;
    }
    
    .item-quantity {
        margin: 0;
        order: 4;
    }
    
    .item-total {
        margin-right: 0;
        order: 5;
    }
    
    .item-remove {
        order: 6;
    }
    
    .cart-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 16px;
    }
    
    .category-links {
        flex-direction: column;
        align-items: center;
    }
    
    .recommendations-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>

<!-- Modern Page Header -->
<section class="page-header-modern">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <div class="page-header-content">
                    <span class="page-badge">🛒 Shopping Cart</span>
                    <h1 class="page-title">Your Shopping <span class="text-gradient">Cart</span></h1>
                    <p class="page-subtitle">Review your selected items before checkout</p>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="page-actions">
                    <a href="shop.php" class="btn btn-outline-light">
                        <i class="fas fa-store me-2"></i> Continue Shopping
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Cart Content -->
<section class="cart-section-modern">
    <div class="container">
        <?php if (empty($cart_data['items'])): ?>
            <!-- Empty Cart State -->
            <div class="empty-cart-modern">
                <div class="empty-cart-icon">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <h4>Your cart is empty!</h4>
                <p>Looks like you haven't added any items to your cart yet.</p>
                <a href="shop.php" class="btn btn-primary btn-lg mt-3">
                    <i class="fas fa-shopping-bag me-2"></i> Start Shopping
                </a>
            </div>
        <?php else: ?>
            <!-- Cart Items -->
            <div class="cart-container-modern">
                <div class="cart-items-container">
                    <div class="cart-header">
                        <h4 class="cart-title">
                            <i class="fas fa-shopping-cart"></i>
                            Cart Items
                            <span class="cart-count"><?php echo count($cart_data['items']); ?> items</span>
                        </h4>
                        <div class="cart-actions">
                            <div class="cart-actions">
                                <form method="post" style="display: inline;">
                                    <input type="hidden" name="action" value="clear">
                                    <button type="submit" class="btn-clear" onclick="return confirm('Are you sure you want to clear your cart?')">
                                        <i class="fas fa-trash-alt me-1"></i> Clear Cart
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="cart-items-list">
                        <?php foreach ($cart_data['items'] as $item): ?>
                            <div class="cart-item-modern">
                                <div class="item-image">
                                    <?php if (!empty($item['image'])): ?>
                                        <img src="assets/images/products/<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                                    <?php else: ?>
                                        <div class="image-placeholder">
                                            <i class="fas fa-image"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="item-details">
                                    <h6 class="item-name"><?php echo htmlspecialchars($item['name']); ?></h6>
                                    <p class="item-brand"><?php echo htmlspecialchars($item['brand']); ?></p>
                                    <div class="item-price">Rs.<?php echo number_format($item['price'], 2); ?></div>
                                </div>
                                
                                <div class="item-quantity">
                                    <div class="quantity-controls">
                                        <form method="post" style="display: inline;">
                                            <input type="hidden" name="action" value="update">
                                            <input type="hidden" name="cart_item_id" value="<?php echo $item['id']; ?>">
                                            <input type="hidden" name="quantity" value="<?php echo max(1, $item['quantity'] - 1); ?>">
                                            <button type="submit" class="quantity-btn" onclick="changeQuantity(this, -1)">-</button>
                                        </form>
                                        
                                        <input type="number" class="quantity-input" value="<?php echo $item['quantity']; ?>" min="1" max="<?php echo $item['stock']; ?>" readonly>
                                        
                                        <form method="post" style="display: inline;">
                                            <input type="hidden" name="action" value="update">
                                            <input type="hidden" name="cart_item_id" value="<?php echo $item['id']; ?>">
                                            <input type="hidden" name="quantity" value="<?php echo $item['quantity'] + 1; ?>">
                                            <button type="submit" class="quantity-btn" onclick="changeQuantity(this, 1)" <?php echo ($item['quantity'] >= $item['stock']) ? 'disabled' : ''; ?>>+</button>
                                        </form>
                                    </div>
                                </div>
                                
                                <div class="item-total">
                                    Rs.<?php echo number_format($item['subtotal'], 2); ?>
                                </div>
                                
                                <div class="item-remove">
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="action" value="remove">
                                        <input type="hidden" name="cart_item_id" value="<?php echo $item['id']; ?>">
                                        <button type="submit" class="btn-remove" onclick="return confirm('Remove this item from cart?')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Cart Summary -->
                <div class="cart-summary-modern">
                    <h4 class="summary-title">Order Summary</h4>
                    
                    <div class="summary-row">
                        <span>Subtotal (<?php echo count($cart_data['items']); ?> items):</span>
                        <span>Rs.<?php echo number_format($cart_data['total'], 2); ?></span>
                    </div>
                    
                    <div class="summary-row">
                        <span>Shipping:</span>
                        <span class="free-shipping">Free</span>
                    </div>
                    
                    <hr class="summary-divider">
                    
                    <div class="summary-total">
                        <span>Total:</span>
                        <span>Rs.<?php echo number_format($cart_data['total'], 2); ?></span>
                    </div>
                    
                    <!-- Promo Code Section -->
                    <div class="promo-section">
                        <div class="promo-header">
                            <h6>Have a promo code?</h6>
                        </div>
                        <div class="input-group">
                            <input type="text" class="form-control promo-input" id="promo_code" placeholder="Enter code">
                            <button class="btn btn-outline-secondary" type="button" onclick="applyPromo()">Apply</button>
                        </div>
                    </div>
                    
                    <!-- Checkout Section -->
                    <div class="checkout-section">
                        <form method="post">
                            <input type="hidden" name="action" value="checkout">
                            <button type="submit" class="checkout-btn">
                                <i class="fas fa-credit-card me-2"></i> Proceed to Checkout
                            </button>
                        </form>
                        <p class="checkout-note">Free shipping and returns</p>
                    </div>
                    
                    <!-- Payment Methods -->
                    <div class="payment-methods">
                        <h6>We accept</h6>
                        <div class="payment-icons">
                            <i class="fab fa-cc-visa"></i>
                            <i class="fab fa-cc-mastercard"></i>
                            <i class="fab fa-cc-paypal"></i>
                            <i class="fab fa-cc-amex"></i>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Recommendations Section -->
<section class="recommendations-section">
    <div class="container">
        <div class="section-header">
            <h3>You might also like</h3>
            <p>Complete your pet care collection with these popular items</p>
        </div>
        
        <div class="recommendations-grid">
            <?php
            // Get some random products for recommendations
            $rec_sql = "SELECT * FROM pet_food_and_accessories ORDER BY RAND() LIMIT 6";
            $rec_result = $conn->query($rec_sql);
            
            if ($rec_result && $rec_result->num_rows > 0):
                while ($product = $rec_result->fetch_assoc()):
            ?>
                <div class="product-card-mini">
                    <div class="product-image">
                        <?php if (!empty($product['image'])): ?>
                            <img src="assets/images/products/<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                        <?php else: ?>
                            <div class="image-placeholder">
                                <i class="fas fa-image"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <h6><?php echo htmlspecialchars($product['name']); ?></h6>
                    <div class="price">Rs.<?php echo number_format($product['price'], 2); ?></div>
                    <button class="btn btn-sm btn-primary" onclick="addToCart(<?php echo $product['productID']; ?>)">
                        <i class="fas fa-cart-plus"></i> Add to Cart
                    </button>
                </div>
            <?php 
                endwhile;
            endif;
            ?>
        </div>
    </div>
</section>

<script>
function changeQuantity(button, change) {
    const input = button.closest('.quantity-controls').querySelector('.quantity-input');
    const currentValue = parseInt(input.value);
    const newValue = Math.max(1, currentValue + change);
    const maxValue = parseInt(input.getAttribute('max'));
    
    if (newValue <= maxValue) {
        input.value = newValue;
        input.form.submit();
    }
}

function applyPromo() {
    const promoCode = document.getElementById('promo_code').value;
    if (promoCode) {
        // Mock promo code functionality
        alert('Promo code "' + promoCode + '" will be implemented in the next version!');
    } else {
        alert('Please enter a promo code.');
    }
}

function addToCart(productId) {
    // Add to cart functionality
    const formData = new FormData();
    formData.append('action', 'add');
    formData.append('product_id', productId);
    formData.append('quantity', 1);
    
    fetch('cart_process.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Product added to cart!');
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while adding to cart.');
    });
}
</script>

<?php
// Display messages
if (isset($_SESSION['error_message'])):
?>
<script>
    alert('<?php echo addslashes($_SESSION['error_message']); ?>');
</script>
<?php 
unset($_SESSION['error_message']);
endif;

if (isset($_SESSION['success_message'])):
?>
<script>
    alert('<?php echo addslashes($_SESSION['success_message']); ?>');
</script>
<?php 
unset($_SESSION['success_message']);
endif;

// Include footer
include_once 'includes/footer.php';

// Close database connection
$conn->close();
?>