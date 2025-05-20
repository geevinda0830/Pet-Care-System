<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in as pet owner
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'pet_owner') {
    $_SESSION['error_message'] = "You must be logged in as a pet owner to access this page.";
    header("Location: ../login.php");
    exit();
}

// Check if order_id is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "Invalid order ID.";
    header("Location: orders.php");
    exit();
}

// Include database connection
require_once '../config/db_connect.php';

$user_id = $_SESSION['user_id'];
$order_id = $_GET['id'];

// Get order details
$order_sql = "SELECT o.* FROM `order` o WHERE o.orderID = ? AND o.userID = ?";
$order_stmt = $conn->prepare($order_sql);
$order_stmt->bind_param("ii", $order_id, $user_id);
$order_stmt->execute();
$order_result = $order_stmt->get_result();

if ($order_result->num_rows === 0) {
    $_SESSION['error_message'] = "Order not found or does not belong to you.";
    header("Location: orders.php");
    exit();
}

$order = $order_result->fetch_assoc();
$order_stmt->close();

// Get cart ID for this order
$cart_sql = "SELECT cartID FROM cart WHERE orderID = ?";
$cart_stmt = $conn->prepare($cart_sql);
$cart_stmt->bind_param("i", $order_id);
$cart_stmt->execute();
$cart_result = $cart_stmt->get_result();
$cart = $cart_result->fetch_assoc();
$cart_id = $cart['cartID'];
$cart_stmt->close();

// Get payment details for this order
$payment_sql = "SELECT * FROM payment WHERE cartID = ?";
$payment_stmt = $conn->prepare($payment_sql);
$payment_stmt->bind_param("i", $cart_id);
$payment_stmt->execute();
$payment_result = $payment_stmt->get_result();
$payment = $payment_result->num_rows > 0 ? $payment_result->fetch_assoc() : null;
$payment_stmt->close();

// Get order items
$items_sql = "SELECT ci.*, p.name, p.brand, p.image 
              FROM cart_items ci 
              JOIN pet_food_and_accessories p ON ci.productID = p.productID 
              WHERE ci.cartID = ?";
$items_stmt = $conn->prepare($items_sql);
$items_stmt->bind_param("i", $cart_id);
$items_stmt->execute();
$items_result = $items_stmt->get_result();
$order_items = [];
$order_total = 0;

while ($row = $items_result->fetch_assoc()) {
    $row['subtotal'] = $row['quantity'] * $row['price'];
    $order_total += $row['subtotal'];
    $order_items[] = $row;
}

$items_stmt->close();

// Include header
include_once '../includes/header.php';
?>

<!-- Page Header -->
<div class="container-fluid bg-light py-4">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="display-5 mb-2">Order #<?php echo $order_id; ?></h1>
                <p class="lead">Placed on <?php echo date('F d, Y', strtotime($order['date'])) . ' at ' . date('h:i A', strtotime($order['time'])); ?></p>
            </div>
            <div class="col-md-4 text-md-end">
                <a href="orders.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-1"></i> Back to Orders
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Order Details -->
<div class="container py-5">
    <div class="row">
        <!-- Order Items -->
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Order Items</h5>
                    <span class="badge <?php 
                        switch ($order['status']) {
                            case 'Pending':
                                echo 'bg-warning';
                                break;
                            case 'Processing':
                                echo 'bg-primary';
                                break;
                            case 'Shipped':
                                echo 'bg-info';
                                break;
                            case 'Delivered':
                                echo 'bg-success';
                                break;
                            case 'Cancelled':
                                echo 'bg-danger';
                                break;
                        }
                    ?> py-2 px-3"><?php echo $order['status']; ?></span>
                </div>
                
                <div class="card-body">
                    <?php if (empty($order_items)): ?>
                        <p class="text-muted">No items found for this order.</p>
                    <?php else: ?>
                        <?php foreach ($order_items as $item): ?>
                            <div class="cart-item mb-3">
                                <div class="row align-items-center">
                                    <div class="col-md-2">
                                        <?php if (!empty($item['image'])): ?>
                                            <img src="../assets/images/products/<?php echo htmlspecialchars($item['image']); ?>" class="cart-item-img" alt="<?php echo htmlspecialchars($item['name']); ?>">
                                        <?php else: ?>
                                            <img src="../assets/images/product-placeholder.jpg" class="cart-item-img" alt="Placeholder">
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="col-md-5">
                                        <h5><?php echo htmlspecialchars($item['name']); ?></h5>
                                        <p class="text-muted small"><?php echo htmlspecialchars($item['brand']); ?></p>
                                    </div>
                                    
                                    <div class="col-md-2 text-center">
                                        <p class="mb-0">Qty: <?php echo $item['quantity']; ?></p>
                                    </div>
                                    
                                    <div class="col-md-1 text-center">
                                        <p class="mb-0">$<?php echo number_format($item['price'], 2); ?></p>
                                    </div>
                                    
                                    <div class="col-md-2 text-end">
                                        <p class="fw-bold mb-0">$<?php echo number_format($item['subtotal'], 2); ?></p>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if ($item !== end($order_items)): ?>
                                <hr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Actions -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Actions</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php if ($order['status'] === 'Delivered'): ?>
                            <div class="col-md-6 mb-3">
                                <a href="add_product_review.php?order_id=<?php echo $order_id; ?>" class="btn btn-primary w-100">
                                    <i class="fas fa-star me-1"></i> Leave a Review
                                </a>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($order['status'] === 'Pending'): ?>
                            <div class="col-md-6 mb-3">
                                <form action="cancel_order.php" method="post" onsubmit="return confirm('Are you sure you want to cancel this order?');">
                                    <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
                                    <button type="submit" class="btn btn-danger w-100">
                                        <i class="fas fa-times me-1"></i> Cancel Order
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($order['status'] === 'Shipped'): ?>
                            <div class="col-md-6 mb-3">
                                <a href="#" class="btn btn-info w-100">
                                    <i class="fas fa-truck me-1"></i> Track Shipment
                                </a>
                            </div>
                        <?php endif; ?>
                        
                        <div class="col-md-6 mb-3">
                            <a href="#" class="btn btn-outline-secondary w-100" onclick="window.print();">
                                <i class="fas fa-print me-1"></i> Print Order
                            </a>
                        </div>
                        
                        <?php if ($order['status'] === 'Delivered'): ?>
                            <div class="col-md-6 mb-3">
                                <a href="../shop.php" class="btn btn-success w-100">
                                    <i class="fas fa-redo me-1"></i> Buy Again
                                </a>
                            </div>
                        <?php endif; ?>
                        
                        <div class="col-md-6 mb-3">
                            <a href="contact_support.php?order_id=<?php echo $order_id; ?>" class="btn btn-outline-primary w-100">
                                <i class="fas fa-question-circle me-1"></i> Need Help?
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Order Summary -->
        <div class="col-lg-4">
            <!-- Order Summary -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Order Summary</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <p>Subtotal</p>
                        <p>$<?php echo number_format($order_total, 2); ?></p>
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
                    
                    <div class="d-flex justify-content-between mb-0">
                        <h5>Total</h5>
                        <h5>$<?php echo number_format($order_total, 2); ?></h5>
                    </div>
                </div>
            </div>
            
            <!-- Shipping Information -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Shipping Information</h5>
                </div>
                <div class="card-body">
                    <p><strong>Address:</strong><br><?php echo nl2br(htmlspecialchars($order['address'])); ?></p>
                    <p><strong>Contact:</strong> <?php echo htmlspecialchars($order['contact']); ?></p>
                </div>
            </div>
            
            <!-- Payment Information -->
            <?php if ($payment): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Payment Information</h5>
                    </div>
                    <div class="card-body">
                        <p><strong>Payment Method:</strong> <?php echo ucfirst(htmlspecialchars($payment['payment_method'])); ?></p>
                        <p><strong>Payment Date:</strong> <?php echo date('F d, Y', strtotime($payment['paymentDate'])); ?></p>
                        <p><strong>Payment Status:</strong> 
                            <span class="badge <?php echo ($payment['status'] === 'Completed') ? 'bg-success' : 'bg-warning'; ?>">
                                <?php echo $payment['status']; ?>
                            </span>
                        </p>
                        <p><strong>Amount:</strong> $<?php echo number_format($payment['amount'], 2); ?></p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// Include footer
include_once '../includes/footer.php';

// Close database connection
$conn->close();
?>