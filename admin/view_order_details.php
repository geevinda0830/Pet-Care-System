<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in as admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    $_SESSION['error_message'] = "You must be logged in as an administrator to access this page.";
    header("Location: ../login.php");
    exit();
}

// Check if order ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "Invalid order ID.";
    header("Location: order_management.php");
    exit();
}

// Include database connection
require_once '../config/db_connect.php';

$order_id = intval($_GET['id']);

// Get order details with customer information
$order_sql = "SELECT o.*, 
                     po.fullName as customerName, 
                     po.email as customerEmail,
                     po.contact as customerPhone,
                     po.address as customerAddress
              FROM `order` o 
              JOIN pet_owner po ON o.userID = po.userID 
              WHERE o.orderID = ?";
$order_stmt = $conn->prepare($order_sql);
$order_stmt->bind_param("i", $order_id);
$order_stmt->execute();
$order_result = $order_stmt->get_result();

if ($order_result->num_rows === 0) {
    $_SESSION['error_message'] = "Order not found.";
    header("Location: order_management.php");
    exit();
}

$order = $order_result->fetch_assoc();
$order_stmt->close();

// Get cart details for this order
$cart_sql = "SELECT * FROM cart WHERE orderID = ?";
$cart_stmt = $conn->prepare($cart_sql);
$cart_stmt->bind_param("i", $order_id);
$cart_stmt->execute();
$cart_result = $cart_stmt->get_result();
$cart = $cart_result->num_rows > 0 ? $cart_result->fetch_assoc() : null;
$cart_stmt->close();

// Get payment details
$payment = null;
if ($cart) {
    $payment_sql = "SELECT * FROM payment WHERE cartID = ?";
    $payment_stmt = $conn->prepare($payment_sql);
    $payment_stmt->bind_param("i", $cart['cartID']);
    $payment_stmt->execute();
    $payment_result = $payment_stmt->get_result();
    $payment = $payment_result->num_rows > 0 ? $payment_result->fetch_assoc() : null;
    $payment_stmt->close();
}

// Get order items
$order_items = [];
$order_total = 0;
if ($cart) {
    $items_sql = "SELECT ci.*, p.name, p.brand, p.image, p.category 
                  FROM cart_items ci 
                  JOIN pet_food_and_accessories p ON ci.productID = p.productID 
                  WHERE ci.cartID = ?";
    $items_stmt = $conn->prepare($items_sql);
    $items_stmt->bind_param("i", $cart['cartID']);
    $items_stmt->execute();
    $items_result = $items_stmt->get_result();
    
    while ($row = $items_result->fetch_assoc()) {
        $row['subtotal'] = $row['quantity'] * $row['price'];
        $order_total += $row['subtotal'];
        $order_items[] = $row;
    }
    $items_stmt->close();
}

// If cart total is available, use it instead
if ($cart && $cart['total_amount']) {
    $order_total = $cart['total_amount'];
}

// Include header
include_once '../includes/header.php';
?>

<style>
/* Order Details Styles */
.order-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 60px 0;
    text-align: center;
}

.order-title {
    font-size: 2.5rem;
    font-weight: 700;
    margin-bottom: 15px;
}

.order-badge {
    background: rgba(255, 255, 255, 0.2);
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 0.9rem;
    font-weight: 600;
    display: inline-block;
    margin-bottom: 20px;
}

.order-content {
    padding: 60px 0;
    background: #f8f9fa;
}

/* Order Summary Cards */
.order-summary {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 40px;
}

.summary-card {
    background: white;
    padding: 25px;
    border-radius: 15px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    text-align: center;
}

.summary-icon {
    width: 60px;
    height: 60px;
    margin: 0 auto 15px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.5rem;
}

.icon-order { background: linear-gradient(135deg, #667eea, #764ba2); }
.icon-status { background: linear-gradient(135deg, #f59e0b, #d97706); }
.icon-items { background: linear-gradient(135deg, #10b981, #059669); }
.icon-total { background: linear-gradient(135deg, #3b82f6, #2563eb); }

.summary-value {
    font-size: 1.8rem;
    font-weight: 800;
    color: #1e293b;
    margin-bottom: 8px;
}

.summary-label {
    color: #64748b;
    font-weight: 600;
}

/* Status Badge */
.status-badge {
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 0.9rem;
    font-weight: 600;
    text-transform: uppercase;
}

.status-pending { background: #fef3c7; color: #92400e; }
.status-processing { background: #dbeafe; color: #1e40af; }
.status-shipped { background: #ede9fe; color: #7c3aed; }
.status-delivered { background: #d1fae5; color: #065f46; }
.status-cancelled { background: #fee2e2; color: #991b1b; }

/* Detail Sections */
.detail-section {
    background: white;
    margin-bottom: 30px;
    border-radius: 15px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    overflow: hidden;
}

.section-header {
    background: linear-gradient(135deg, #f8f9ff, #f1f5f9);
    padding: 20px 30px;
    border-bottom: 1px solid #e5e7eb;
}

.section-title {
    font-size: 1.2rem;
    font-weight: 700;
    color: #1e293b;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.section-content {
    padding: 30px;
}

/* Customer Details */
.customer-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
}

.detail-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 10px;
}

.detail-icon {
    width: 40px;
    height: 40px;
    background: #667eea;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 0.9rem;
}

.detail-content h6 {
    margin: 0;
    font-weight: 600;
    color: #374151;
    font-size: 0.9rem;
}

.detail-content p {
    margin: 0;
    color: #6b7280;
    font-size: 0.9rem;
}

/* Order Items */
.items-table {
    width: 100%;
    border-collapse: collapse;
}

.items-table th {
    background: #f8f9fa;
    padding: 15px;
    text-align: left;
    font-weight: 600;
    color: #374151;
    border-bottom: 2px solid #e5e7eb;
}

.items-table td {
    padding: 20px 15px;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
}

.item-info {
    display: flex;
    align-items: center;
    gap: 15px;
}

.item-image {
    width: 60px;
    height: 60px;
    border-radius: 10px;
    overflow: hidden;
    background: #f3f4f6;
    display: flex;
    align-items: center;
    justify-content: center;
}

.item-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.item-image i {
    color: #9ca3af;
    font-size: 1.5rem;
}

.item-details h6 {
    margin: 0;
    font-weight: 600;
    color: #1e293b;
    font-size: 0.95rem;
}

.item-details small {
    color: #6b7280;
    font-size: 0.8rem;
}

.item-category {
    background: #e0e7ff;
    color: #3730a3;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.7rem;
    font-weight: 600;
    display: inline-block;
    margin-top: 4px;
}

/* Order Total */
.order-total-section {
    background: linear-gradient(135deg, #f8f9ff, #f1f5f9);
    padding: 20px;
    border-radius: 10px;
    margin-top: 20px;
}

.total-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid #e5e7eb;
}

.total-row:last-child {
    border-bottom: none;
    font-size: 1.2rem;
    font-weight: 700;
    color: #1e293b;
}

.total-label {
    color: #64748b;
    font-weight: 600;
}

.total-value {
    font-weight: 700;
    color: #1e293b;
}

/* Payment Info */
.payment-info {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
}

.payment-item {
    text-align: center;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 10px;
}

.payment-icon {
    width: 50px;
    height: 50px;
    margin: 0 auto 10px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.2rem;
}

.icon-card { background: linear-gradient(135deg, #3b82f6, #2563eb); }
.icon-amount { background: linear-gradient(135deg, #10b981, #059669); }
.icon-date { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }

/* Actions */
.order-actions {
    text-align: center;
    margin-top: 40px;
}

.btn-action {
    background: #667eea;
    color: white;
    padding: 12px 24px;
    border: none;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin: 0 10px;
    transition: all 0.3s ease;
}

.btn-action:hover {
    background: #5a67d8;
    text-decoration: none;
    color: white;
    transform: translateY(-2px);
}

.btn-secondary {
    background: #f3f4f6;
    color: #374151;
}

.btn-secondary:hover {
    background: #e5e7eb;
    color: #374151;
}

/* Responsive */
@media (max-width: 768px) {
    .order-title {
        font-size: 2rem;
    }
    
    .order-summary {
        grid-template-columns: repeat(2, 1fr);
        gap: 15px;
    }
    
    .customer-grid {
        grid-template-columns: 1fr;
    }
    
    .items-table {
        font-size: 0.9rem;
    }
    
    .item-info {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .payment-info {
        grid-template-columns: 1fr;
    }
}
</style>

<!-- Order Header -->
<section class="order-header">
    <div class="container">
        <div class="order-badge">Order Details - Admin View</div>
        <h1 class="order-title">Order #<?php echo $order['orderID']; ?></h1>
        <p>Placed on <?php echo date('F d, Y', strtotime($order['date'])) . ' at ' . date('g:i A', strtotime($order['time'])); ?></p>
    </div>
</section>

<!-- Order Content -->
<section class="order-content">
    <div class="container">
        
        <!-- Order Summary Cards -->
        <div class="order-summary">
            <div class="summary-card">
                <div class="summary-icon icon-order">
                    <i class="fas fa-receipt"></i>
                </div>
                <div class="summary-value">#<?php echo $order['orderID']; ?></div>
                <div class="summary-label">Order Number</div>
            </div>
            
            <div class="summary-card">
                <div class="summary-icon icon-status">
                    <i class="fas fa-info-circle"></i>
                </div>
                <div class="summary-value">
                    <span class="status-badge status-<?php echo strtolower($order['status']); ?>">
                        <?php echo $order['status']; ?>
                    </span>
                </div>
                <div class="summary-label">Current Status</div>
            </div>
            
            <div class="summary-card">
                <div class="summary-icon icon-items">
                    <i class="fas fa-boxes"></i>
                </div>
                <div class="summary-value"><?php echo count($order_items); ?></div>
                <div class="summary-label">Items Ordered</div>
            </div>
            
            <div class="summary-card">
                <div class="summary-icon icon-total">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div class="summary-value">₱<?php echo number_format($order_total, 2); ?></div>
                <div class="summary-label">Order Total</div>
            </div>
        </div>

        <!-- Customer Details -->
        <div class="detail-section">
            <div class="section-header">
                <h3 class="section-title">
                    <i class="fas fa-user"></i>
                    Customer Information
                </h3>
            </div>
            <div class="section-content">
                <div class="customer-grid">
                    <div class="detail-item">
                        <div class="detail-icon">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="detail-content">
                            <h6>Customer Name</h6>
                            <p><?php echo htmlspecialchars($order['customerName']); ?></p>
                        </div>
                    </div>
                    
                    <div class="detail-item">
                        <div class="detail-icon">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <div class="detail-content">
                            <h6>Email Address</h6>
                            <p><?php echo htmlspecialchars($order['customerEmail']); ?></p>
                        </div>
                    </div>
                    
                    <div class="detail-item">
                        <div class="detail-icon">
                            <i class="fas fa-phone"></i>
                        </div>
                        <div class="detail-content">
                            <h6>Phone Number</h6>
                            <p><?php echo htmlspecialchars($order['customerPhone'] ?? 'Not provided'); ?></p>
                        </div>
                    </div>
                    
                    <div class="detail-item">
                        <div class="detail-icon">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <div class="detail-content">
                            <h6>Delivery Address</h6>
                            <p><?php echo htmlspecialchars($order['customerAddress'] ?? 'Not provided'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Order Items -->
        <div class="detail-section">
            <div class="section-header">
                <h3 class="section-title">
                    <i class="fas fa-shopping-cart"></i>
                    Order Items (<?php echo count($order_items); ?> items)
                </h3>
            </div>
            <div class="section-content">
                <?php if (empty($order_items)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-box-open" style="font-size: 3rem; color: #d1d5db; margin-bottom: 15px;"></i>
                        <p>No items found for this order.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="items-table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Price</th>
                                    <th>Quantity</th>
                                    <th>Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($order_items as $item): ?>
                                    <tr>
                                        <td>
                                            <div class="item-info">
                                                <div class="item-image">
                                                    <?php if (!empty($item['image']) && file_exists('../assets/images/products/' . $item['image'])): ?>
                                                        <img src="../assets/images/products/<?php echo htmlspecialchars($item['image']); ?>" 
                                                             alt="<?php echo htmlspecialchars($item['name']); ?>">
                                                    <?php else: ?>
                                                        <i class="fas fa-image"></i>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="item-details">
                                                    <h6><?php echo htmlspecialchars($item['name']); ?></h6>
                                                    <small><?php echo htmlspecialchars($item['brand']); ?></small>
                                                    <?php if (!empty($item['category'])): ?>
                                                        <div class="item-category"><?php echo htmlspecialchars($item['category']); ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td>₱<?php echo number_format($item['price'], 2); ?></td>
                                        <td><?php echo $item['quantity']; ?></td>
                                        <td>₱<?php echo number_format($item['subtotal'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Order Total -->
                    <div class="order-total-section">
                        <div class="total-row">
                            <span class="total-label">Subtotal:</span>
                            <span class="total-value">₱<?php echo number_format($order_total, 2); ?></span>
                        </div>
                        <div class="total-row">
                            <span class="total-label">Shipping:</span>
                            <span class="total-value">Free</span>
                        </div>
                        <div class="total-row">
                            <span class="total-label">Total:</span>
                            <span class="total-value">₱<?php echo number_format($order_total, 2); ?></span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Payment Information -->
        <?php if ($payment): ?>
            <div class="detail-section">
                <div class="section-header">
                    <h3 class="section-title">
                        <i class="fas fa-credit-card"></i>
                        Payment Information
                    </h3>
                </div>
                <div class="section-content">
                    <div class="payment-info">
                        <div class="payment-item">
                            <div class="payment-icon icon-card">
                                <i class="fas fa-credit-card"></i>
                            </div>
                            <h6>Payment Method</h6>
                            <p><?php echo htmlspecialchars($payment['payment_method'] ?? 'Credit Card'); ?></p>
                        </div>
                        
                        <div class="payment-item">
                            <div class="payment-icon icon-amount">
                                <i class="fas fa-money-bill-wave"></i>
                            </div>
                            <h6>Amount Paid</h6>
                            <p>₱<?php echo number_format($payment['amount'] ?? $order_total, 2); ?></p>
                        </div>
                        
                        <div class="payment-item">
                            <div class="payment-icon icon-date">
                                <i class="fas fa-calendar"></i>
                            </div>
                            <h6>Payment Date</h6>
                            <p><?php echo date('M d, Y', strtotime($payment['payment_date'] ?? $order['date'])); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Actions -->
        <div class="order-actions">
            <a href="order_management.php" class="btn-action btn-secondary">
                <i class="fas fa-arrow-left"></i>
                Back to Orders
            </a>
            <a href="order_management.php?status=<?php echo urlencode($order['status']); ?>" class="btn-action">
                <i class="fas fa-filter"></i>
                View <?php echo $order['status']; ?> Orders
            </a>
        </div>
    </div>
</section>

<?php
// Include footer
include_once '../includes/footer.php';

// Close database connection
$conn->close();
?>