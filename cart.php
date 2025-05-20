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

<div class="container py-5">
    <h2 class="mb-4">Your Shopping Cart</h2>
    
    <?php if (empty($cart_data['items'])): ?>
        <div class="alert alert-info">
            <h4 class="alert-heading">Your cart is empty!</h4>
            <p>Looks like you haven't added any items to your cart yet.</p>
            <hr>
            <p class="mb-0">Browse our <a href="shop.php" class="alert-link">pet shop</a> to find products for your pets.</p>
        </div>
    <?php else: ?>
        <div class="row">
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="card-title mb-0">Cart Items (<?php echo count($cart_data['items']); ?>)</h5>
                            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                                <input type="hidden" name="action" value="clear">
                                <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to clear your cart?')">
                                    <i class="fas fa-trash-alt me-1"></i> Clear Cart
                                </button>
                            </form>
                        </div>
                        
                        <hr>
                        
                        <?php foreach ($cart_data['items'] as $item): ?>
                            <div class="cart-item">
                                <div class="row align-items-center">
                                    <div class="col-md-2">
                                        <?php if (!empty($item['image'])): ?>
                                            <img src="assets/images/products/<?php echo htmlspecialchars($item['image']); ?>" class="cart-item-img" alt="<?php echo htmlspecialchars($item['name']); ?>">
                                        <?php else: ?>
                                            <img src="assets/images/product-placeholder.jpg" class="cart-item-img" alt="Placeholder">
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <h5><?php echo htmlspecialchars($item['name']); ?></h5>
                                        <p class="text-muted small"><?php echo htmlspecialchars($item['brand']); ?></p>
                                        <p class="price">$<?php echo number_format($item['price'], 2); ?></p>
                                    </div>
                                    
                                    <div class="col-md-3">
                                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="update-cart-form">
                                            <input type="hidden" name="action" value="update">
                                            <input type="hidden" name="cart_item_id" value="<?php echo $item['id']; ?>">
                                            <div class="quantity-control input-group">
                                                <button type="button" class="btn btn-outline-secondary quantity-btn" data-action="decrease">-</button>
                                                <input type="number" name="quantity" class="form-control text-center" value="<?php echo $item['quantity']; ?>" min="1" max="<?php echo $item['stock']; ?>">
                                                <button type="button" class="btn btn-outline-secondary quantity-btn" data-action="increase">+</button>
                                            </div>
                                            <button type="submit" class="btn btn-sm btn-outline-primary mt-2 update-cart-btn">Update</button>
                                        </form>
                                    </div>
                                    
                                    <div class="col-md-2 text-end">
                                        <p class="fw-bold">$<?php echo number_format($item['price'] * $item['quantity'], 2); ?></p>
                                    </div>
                                    
                                    <div class="col-md-1 text-end">
                                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                                            <input type="hidden" name="action" value="remove">
                                            <input type="hidden" name="cart_item_id" value="<?php echo $item['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Remove Item">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if (!$item === end($cart_data['items'])): ?>
                                <hr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="d-flex justify-content-between">
                    <a href="shop.php" class="btn btn-outline-primary">
                        <i class="fas fa-arrow-left me-1"></i> Continue Shopping
                    </a>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="card cart-summary">
                    <div class="card-body">
                        <h5 class="card-title mb-4">Order Summary</h5>
                        
                        <div class="d-flex justify-content-between mb-2">
                            <p>Subtotal</p>
                            <p>$<?php echo number_format($cart_data['total'], 2); ?></p>
                        </div>
                        
                        <div class="d-flex justify-content-between mb-2">
                            <p>Shipping</p>
                            <p>Free</p>
                        </div>
                        
                        <div class="d-flex justify-content-between mb-2">
                            <p>Tax</p>
                            <p>$0.00</p>
                        </div>
                        
                        <hr>
                        
                        <div class="d-flex justify-content-between mb-4">
                            <h5>Total</h5>
                            <h5>$<?php echo number_format($cart_data['total'], 2); ?></h5>
                        </div>
                        
                        <div class="mb-3">
                            <label for="coupon_code" class="form-label">Promo Code</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="coupon_code" placeholder="Enter code">
                                <button class="btn btn-outline-secondary" type="button">Apply</button>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <a href="payment.php?type=cart" class="btn btn-primary btn-lg">Proceed to Checkout</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Recently Viewed Products -->
<section class="py-5 bg-light-gray">
    <div class="container">
        <h3 class="mb-4">You Might Also Like</h3>
        <div class="row">
            <!-- These would normally be dynamically populated based on user's browsing history or recommended products -->
            <div class="col-md-3 col-sm-6 mb-4">
                <div class="card product-card h-100">
                    <img src="assets/images/product-placeholder.jpg" class="card-img-top" alt="Product">
                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title">Premium Dog Food</h5>
                        <p class="text-muted small">Brand Name</p>
                        <div class="rating mb-2">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star-half-alt"></i>
                        </div>
                        <p class="price mt-auto mb-3">$24.99</p>
                        <button type="button" class="btn btn-sm btn-success" onclick="addToCart(1)">
                            <i class="fas fa-cart-plus me-1"></i> Add to Cart
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 col-sm-6 mb-4">
                <div class="card product-card h-100">
                    <img src="assets/images/product-placeholder.jpg" class="card-img-top" alt="Product">
                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title">Cat Scratching Post</h5>
                        <p class="text-muted small">Brand Name</p>
                        <div class="rating mb-2">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="far fa-star"></i>
                        </div>
                        <p class="price mt-auto mb-3">$34.99</p>
                        <button type="button" class="btn btn-sm btn-success" onclick="addToCart(2)">
                            <i class="fas fa-cart-plus me-1"></i> Add to Cart
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 col-sm-6 mb-4">
                <div class="card product-card h-100">
                    <img src="assets/images/product-placeholder.jpg" class="card-img-top" alt="Product">
                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title">Dog Chew Toy</h5>
                        <p class="text-muted small">Brand Name</p>
                        <div class="rating mb-2">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                        </div>
                        <p class="price mt-auto mb-3">$12.99</p>
                        <button type="button" class="btn btn-sm btn-success" onclick="addToCart(3)">
                            <i class="fas fa-cart-plus me-1"></i> Add to Cart
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 col-sm-6 mb-4">
                <div class="card product-card h-100">
                    <img src="assets/images/product-placeholder.jpg" class="card-img-top" alt="Product">
                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title">Pet Carrier</h5>
                        <p class="text-muted small">Brand Name</p>
                        <div class="rating mb-2">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star-half-alt"></i>
                            <i class="far fa-star"></i>
                        </div>
                        <p class="price mt-auto mb-3">$45.99</p>
                        <button type="button" class="btn btn-sm btn-success" onclick="addToCart(4)">
                            <i class="fas fa-cart-plus me-1"></i> Add to Cart
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
// Include footer
include_once 'includes/footer.php';

// Close database connection
$conn->close();
?>