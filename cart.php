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
        $update_sql = "UPDATE cart_items SET quantity = ? WHERE id = ? AND cartID IN (SELECT cartID FROM cart WHERE userID = ? AND orderID IS NULL)";
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
        $delete_sql = "DELETE FROM cart_items WHERE id = ? AND cartID IN (SELECT cartID FROM cart WHERE userID = ? AND orderID IS NULL)";
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
        $clear_sql = "DELETE FROM cart_items WHERE cartID IN (SELECT cartID FROM cart WHERE userID = ? AND orderID IS NULL)";
        $clear_stmt = $conn->prepare($clear_sql);
        $clear_stmt->bind_param("i", $_SESSION['user_id']);
        $clear_stmt->execute();
        $clear_stmt->close();
        
        // Update cart total
        updateCartTotal($conn, $_SESSION['user_id']);
        
        // Redirect to refresh page
        header("Location: cart.php");
        exit();
    }
}

// Function to update cart total
function updateCartTotal($conn, $user_id) {
    // Get cart ID
    $cart_sql = "SELECT cartID FROM cart WHERE userID = ? AND orderID IS NULL";
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
        
        // Update cart total
        $update_sql = "UPDATE cart SET total_amount = ? WHERE cartID = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("di", $total_amount, $cart_id);
        $update_stmt->execute();
        
        $update_stmt->close();
        $total_stmt->close();
    }
    
    $cart_stmt->close();
}

// Get cart items
function getCartItems($conn, $user_id) {
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
    while ($row = $items_result->fetch_assoc()) {
        $cart_items[] = $row;
    }
    
    $items_stmt->close();
    
    // Get cart total
    $total_sql = "SELECT total_amount FROM cart WHERE cartID = ?";
    $total_stmt = $conn->prepare($total_sql);
    $total_stmt->bind_param("i", $cart_id);
    $total_stmt->execute();
    $total_result = $total_stmt->get_result();
    $total_row = $total_result->fetch_assoc();
    $total_amount = $total_row['total_amount'];
    
    $total_stmt->close();
    
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
                <p>Looks like you haven't added any items to your cart yet. Browse our collection of premium pet products.</p>
                
                <div class="empty-cart-actions">
                    <a href="shop.php" class="btn btn-primary-gradient btn-lg">
                        <i class="fas fa-store me-2"></i> Start Shopping
                    </a>
                </div>
                
                <div class="suggested-categories">
                    <h6>Popular Categories</h6>
                    <div class="category-links">
                        <a href="shop.php?category=Dog Food" class="category-link">
                            <i class="fas fa-bone"></i>
                            Dog Food
                        </a>
                        <a href="shop.php?category=Cat Food" class="category-link">
                            <i class="fas fa-fish"></i>
                            Cat Food
                        </a>
                        <a href="shop.php?category=Toys" class="category-link">
                            <i class="fas fa-baseball-ball"></i>
                            Toys
                        </a>
                        <a href="shop.php?category=Accessories" class="category-link">
                            <i class="fas fa-collar"></i>
                            Accessories
                        </a>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Cart Items -->
            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="cart-items-card">
                        <div class="cart-header">
                            <h5><i class="fas fa-shopping-bag me-2"></i> Cart Items</h5>
                            <div class="cart-actions-header">
                                <span class="item-count"><?php echo count($cart_data['items']); ?> items</span>
                                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="clear-cart-form">
                                    <input type="hidden" name="action" value="clear">
                                    <button type="submit" class="btn btn-outline-danger btn-sm" onclick="return confirm('Are you sure you want to clear your cart?')">
                                        <i class="fas fa-trash-alt me-1"></i> Clear Cart
                                    </button>
                                </form>
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
                                        <div class="item-price">Rs. <?php echo number_format($item['price'], 2); ?></div>
                                        
                                        <div class="stock-info">
                                            <?php if ($item['stock'] <= 5): ?>
                                                <span class="stock-warning">⚠️ Only <?php echo $item['stock']; ?> left in stock</span>
                                            <?php else: ?>
                                                <span class="stock-available">✅ In Stock</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="item-quantity">
                                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="quantity-form">
                                            <input type="hidden" name="action" value="update">
                                            <input type="hidden" name="cart_item_id" value="<?php echo $item['id']; ?>">
                                            <div class="quantity-controls">
                                                <button type="button" class="quantity-btn decrease" onclick="changeQuantity(this, -1)">
                                                    <i class="fas fa-minus"></i>
                                                </button>
                                                <input type="number" name="quantity" class="quantity-input" 
                                                       value="<?php echo $item['quantity']; ?>" 
                                                       min="1" max="<?php echo $item['stock']; ?>"
                                                       onchange="this.form.submit()">
                                                <button type="button" class="quantity-btn increase" onclick="changeQuantity(this, 1)">
                                                    <i class="fas fa-plus"></i>
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                    
                                    <div class="item-total">
                                        <div class="total-price">Rs. <?php echo number_format($item['price'] * $item['quantity'], 2); ?></div>
                                    </div>
                                    
                                    <div class="item-remove">
                                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                                            <input type="hidden" name="action" value="remove">
                                            <input type="hidden" name="cart_item_id" value="<?php echo $item['id']; ?>">
                                            <button type="submit" class="remove-btn" onclick="return confirm('Remove this item from your cart?')">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Cart Summary -->
                <div class="col-lg-4">
                    <div class="cart-summary-modern">
                        <div class="summary-header">
                            <h5><i class="fas fa-receipt me-2"></i> Order Summary</h5>
                        </div>
                        
                        <div class="summary-details">
                            <div class="summary-line">
                                <span>Subtotal (<?php echo count($cart_data['items']); ?> items)</span>
                                <span>Rs. <?php echo number_format($cart_data['total'], 2); ?></span>
                            </div>
                            
                            <div class="summary-line">
                                <span>Shipping</span>
                                <span class="free-shipping">Free</span>
                            </div>
                            
                            <div class="summary-line">
                                <span>Tax</span>
                                <span>Rs. 0.00</span>
                            </div>
                            
                            <hr class="summary-divider">
                            
                            <div class="summary-total">
                                <span>Total</span>
                                <span>Rs. <?php echo number_format($cart_data['total'], 2); ?></span>
                            </div>
                        </div>
                        
                        <!-- Promo Code -->
                        <div class="promo-section">
                            <div class="promo-header">
                                <h6>🎁 Have a promo code?</h6>
                            </div>
                            <form class="promo-form">
                                <div class="input-group">
                                    <input type="text" class="form-control promo-input" placeholder="Enter promo code" id="promo_code">
                                    <button class="btn btn-outline-primary" type="button" onclick="applyPromo()">Apply</button>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Checkout Button -->
                        <div class="checkout-section">
                            <a href="payment.php?type=cart" class="btn btn-primary-gradient btn-lg w-100 checkout-btn">
                                <i class="fas fa-lock me-2"></i> Secure Checkout
                            </a>
                            <p class="checkout-note">
                                <i class="fas fa-shield-alt me-1"></i>
                                Your payment information is secure and encrypted
                            </p>
                        </div>
                        
                        <!-- Payment Methods -->
                        <div class="payment-methods">
                            <h6>We Accept</h6>
                            <div class="payment-icons">
                                <i class="fab fa-cc-visa"></i>
                                <i class="fab fa-cc-mastercard"></i>
                                <i class="fab fa-paypal"></i>
                                <i class="fas fa-university"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Recommended Products -->
<section class="recommendations-section">
    <div class="container">
        <div class="section-header">
            <h3>You Might Also Like</h3>
            <p>Popular products from our collection</p>
        </div>
        
        <div class="recommendations-grid">
            <div class="product-card-mini">
                <div class="product-image">
                    <img src="https://images.unsplash.com/photo-1589924691995-400dc9ecc119?ixlib=rb-4.0.3&auto=format&fit=crop&w=300&q=80" alt="Premium Dog Food">
                </div>
                <div class="product-info">
                    <h6>Premium Dog Food</h6>
                    <p class="price">Rs. 2,999</p>
                    <button class="btn btn-primary-gradient btn-sm" onclick="addToCart(1)">
                        <i class="fas fa-cart-plus me-1"></i> Add
                    </button>
                </div>
            </div>
            
            <div class="product-card-mini">
                <div class="product-image">
                    <img src="https://images.unsplash.com/photo-1541781408260-3c61143b63d5?ixlib=rb-4.0.3&auto=format&fit=crop&w=300&q=80" alt="Cat Toy">
                </div>
                <div class="product-info">
                    <h6>Interactive Cat Toy</h6>
                    <p class="price">Rs. 1,299</p>
                    <button class="btn btn-primary-gradient btn-sm" onclick="addToCart(2)">
                        <i class="fas fa-cart-plus me-1"></i> Add
                    </button>
                </div>
            </div>
            
            <div class="product-card-mini">
                <div class="product-image">
                    <img src="https://images.unsplash.com/photo-1583337687581-3f6ba0a9c709?ixlib=rb-4.0.3&auto=format&fit=crop&w=300&q=80" alt="Pet Collar">
                </div>
                <div class="product-info">
                    <h6>Stylish Pet Collar</h6>
                    <p class="price">Rs. 899</p>
                    <button class="btn btn-primary-gradient btn-sm" onclick="addToCart(3)">
                        <i class="fas fa-cart-plus me-1"></i> Add
                    </button>
                </div>
            </div>
            
            <div class="product-card-mini">
                <div class="product-image">
                    <img src="https://images.unsplash.com/photo-1548199973-03cce0bbc87b?ixlib=rb-4.0.3&auto=format&fit=crop&w=300&q=80" alt="Grooming Kit">
                </div>
                <div class="product-info">
                    <h6>Pet Grooming Kit</h6>
                    <p class="price">Rs. 2,499</p>
                    <button class="btn btn-primary-gradient btn-sm" onclick="addToCart(4)">
                        <i class="fas fa-cart-plus me-1"></i> Add
                    </button>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
.page-header-modern {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 80px 0;
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
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="20" cy="20" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="80" cy="40" r="0.5" fill="rgba(255,255,255,0.1)"/><circle cx="40" cy="80" r="1.5" fill="rgba(255,255,255,0.1)"/></svg>');
    animation: float 20s infinite linear;
}

.page-header-content {
    position: relative;
    z-index: 2;
}

.page-badge {
    background: rgba(255, 255, 255, 0.2);
    padding: 8px 16px;
    border-radius: 50px;
    font-size: 0.9rem;
    font-weight: 600;
    margin-bottom: 16px;
    display: inline-block;
    backdrop-filter: blur(10px);
}

.page-title {
    font-size: 3.5rem;
    font-weight: 800;
    line-height: 1.2;
    margin-bottom: 16px;
}

.page-subtitle {
    font-size: 1.2rem;
    opacity: 0.9;
    margin-bottom: 0;
}

.page-actions {
    text-align: right;
    position: relative;
    z-index: 2;
}

.cart-section-modern {
    padding: 80px 0;
    background: white;
}

.empty-cart-modern {
    text-align: center;
    max-width: 600px;
    margin: 0 auto;
    padding: 80px 40px;
}

.empty-cart-icon {
    font-size: 5rem;
    color: #9ca3af;
    margin-bottom: 32px;
}

.empty-cart-modern h4 {
    font-size: 2rem;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 16px;
}

.empty-cart-modern p {
    font-size: 1.1rem;
    color: #64748b;
    margin-bottom: 40px;
    line-height: 1.6;
}

.empty-cart-actions {
    margin-bottom: 50px;
}

.suggested-categories h6 {
    color: #1e293b;
    font-weight: 600;
    margin-bottom: 20px;
}

.category-links {
    display: flex;
    gap: 16px;
    justify-content: center;
    flex-wrap: wrap;
}

.category-link {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px 20px;
    background: #f8f9ff;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    color: #64748b;
    text-decoration: none;
    font-weight: 500;
    transition: all 0.3s ease;
}

.category-link:hover {
    border-color: #667eea;
    color: #667eea;
    background: white;
}

.cart-items-card, .cart-summary-modern {
    background: white;
    border-radius: 20px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(0, 0, 0, 0.05);
}

.cart-header {
    padding: 24px;
    border-bottom: 1px solid #f1f5f9;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.cart-header h5 {
    color: #1e293b;
    font-weight: 600;
    margin: 0;
}

.cart-actions-header {
    display: flex;
    align-items: center;
    gap: 16px;
}

.item-count {
    background: #f1f5f9;
    color: #64748b;
    padding: 4px 12px;
    border-radius: 50px;
    font-size: 0.8rem;
    font-weight: 500;
}

.cart-items-list {
    padding: 0;
}

.cart-item-modern {
    display: flex;
    align-items: center;
    gap: 20px;
    padding: 24px;
    border-bottom: 1px solid #f1f5f9;
    transition: all 0.3s ease;
}

.cart-item-modern:hover {
    background: #f8f9ff;
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
    color: #9ca3af;
    font-size: 2rem;
}

.item-details {
    flex: 1;
}

.item-name {
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 4px;
    font-size: 1.1rem;
}

.item-brand {
    color: #64748b;
    font-size: 0.9rem;
    margin-bottom: 8px;
}

.item-price {
    font-size: 1.1rem;
    font-weight: 600;
    color: #667eea;
    margin-bottom: 8px;
}

.stock-info {
    font-size: 0.8rem;
}

.stock-warning {
    color: #f59e0b;
}

.stock-available {
    color: #10b981;
}

.item-quantity {
    margin: 0 20px;
}

.quantity-controls {
    display: flex;
    align-items: center;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    overflow: hidden;
}

.quantity-btn {
    width: 40px;
    height: 40px;
    border: none;
    background: #f8f9ff;
    color: #667eea;
    cursor: pointer;
    transition: all 0.3s ease;
}

.quantity-btn:hover {
    background: #667eea;
    color: white;
}

.quantity-input {
    width: 60px;
    height: 40px;
    border: none;
    text-align: center;
    font-weight: 600;
    background: white;
}

.quantity-input:focus {
    outline: none;
}

.item-total {
    text-align: right;
    margin-right: 20px;
}

.total-price {
    font-size: 1.2rem;
    font-weight: 700;
    color: #1e293b;
}

.item-remove {
    flex-shrink: 0;
}

.remove-btn {
    width: 40px;
    height: 40px;
    border: none;
    background: #fee2e2;
    color: #ef4444;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.remove-btn:hover {
    background: #ef4444;
    color: white;
}

.cart-summary-modern {
    position: sticky;
    top: 100px;
}

.summary-header {
    padding: 24px 24px 0;
    margin-bottom: 20px;
}

.summary-header h5 {
    color: #1e293b;
    font-weight: 600;
    margin: 0;
}

.summary-details {
    padding: 0 24px;
    margin-bottom: 24px;
}

.summary-line {
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
</script>

<?php
// Include footer
include_once 'includes/footer.php';

// Close database connection
$conn->close();
?>