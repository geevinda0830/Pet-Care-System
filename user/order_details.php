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

<!-- Modern Page Header -->
<section class="page-header-modern">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <div class="page-header-content">
                    <span class="page-badge">📦 Order Details</span>
                    <h1 class="page-title">Order <span class="text-gradient">#<?php echo $order_id; ?></span></h1>
                    <p class="page-subtitle">
                        Placed on <?php echo date('F d, Y', strtotime($order['date'])) . ' at ' . date('h:i A', strtotime($order['time'])); ?>
                    </p>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="page-actions">
                    <div class="order-status-badge status-<?php echo strtolower($order['status']); ?>">
                        <i class="fas fa-circle"></i>
                        <?php echo $order['status']; ?>
                    </div>
                    <a href="orders.php" class="btn btn-outline-light">
                        <i class="fas fa-arrow-left me-2"></i> Back to Orders
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Order Progress -->
<section class="progress-section">
    <div class="container">
        <div class="progress-card">
            <div class="progress-header">
                <h5>Order Progress</h5>
                <span class="progress-percentage">
                    <?php 
                    switch($order['status']) {
                        case 'Pending': echo '25%'; break;
                        case 'Processing': echo '50%'; break;
                        case 'Shipped': echo '75%'; break;
                        case 'Delivered': echo '100%'; break;
                        case 'Cancelled': echo '0%'; break;
                        default: echo '25%';
                    }
                    ?>
                </span>
            </div>
            
            <div class="progress-timeline">
                <div class="timeline-item <?php echo in_array($order['status'], ['Processing', 'Shipped', 'Delivered']) ? 'completed' : ($order['status'] === 'Pending' ? 'active' : ''); ?>">
                    <div class="timeline-icon">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="timeline-content">
                        <h6>Order Placed</h6>
                        <p><?php echo date('M d, Y H:i', strtotime($order['date'] . ' ' . $order['time'])); ?></p>
                    </div>
                </div>
                
                <div class="timeline-item <?php echo in_array($order['status'], ['Shipped', 'Delivered']) ? 'completed' : ($order['status'] === 'Processing' ? 'active' : ''); ?>">
                    <div class="timeline-icon">
                        <i class="fas fa-cog"></i>
                    </div>
                    <div class="timeline-content">
                        <h6>Processing</h6>
                        <p>Preparing your order</p>
                    </div>
                </div>
                
                <div class="timeline-item <?php echo $order['status'] === 'Delivered' ? 'completed' : ($order['status'] === 'Shipped' ? 'active' : ''); ?>">
                    <div class="timeline-icon">
                        <i class="fas fa-truck"></i>
                    </div>
                    <div class="timeline-content">
                        <h6>Shipped</h6>
                        <p>On the way to you</p>
                    </div>
                </div>
                
                <div class="timeline-item <?php echo $order['status'] === 'Delivered' ? 'completed active' : ''; ?>">
                    <div class="timeline-icon">
                        <i class="fas fa-check"></i>
                    </div>
                    <div class="timeline-content">
                        <h6>Delivered</h6>
                        <p>Order complete</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Order Details Content -->
<section class="order-details-section">
    <div class="container">
        <div class="row g-4">
            <!-- Order Items -->
            <div class="col-lg-8">
                <div class="items-card">
                    <div class="card-header">
                        <h5><i class="fas fa-box me-2"></i> Order Items</h5>
                        <span class="item-count"><?php echo count($order_items); ?> items</span>
                    </div>
                    
                    <div class="items-list">
                        <?php if (empty($order_items)): ?>
                            <div class="empty-items">
                                <i class="fas fa-box-open"></i>
                                <p>No items found for this order.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($order_items as $item): ?>
                                <div class="item-card">
                                    <div class="item-image">
                                        <?php if (!empty($item['image'])): ?>
                                            <img src="../assets/images/products/<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                                        <?php else: ?>
                                            <div class="image-placeholder">
                                                <i class="fas fa-image"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="item-details">
                                        <h6 class="item-name"><?php echo htmlspecialchars($item['name']); ?></h6>
                                        <p class="item-brand"><?php echo htmlspecialchars($item['brand']); ?></p>
                                        <div class="item-specs">
                                            <span class="spec">Qty: <?php echo $item['quantity']; ?></span>
                                            <span class="spec">Unit: Rs. <?php echo number_format($item['price'], 2); ?></span>
                                        </div>
                                    </div>
                                    
                                    <div class="item-total">
                                        <span class="total-amount">Rs. <?php echo number_format($item['subtotal'], 2); ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Order Actions -->
                <div class="actions-card">
                    <div class="card-header">
                        <h5><i class="fas fa-tools me-2"></i> Order Actions</h5>
                    </div>
                    
                    <div class="actions-grid">
                        <?php if ($order['status'] === 'Delivered'): ?>
                            <a href="add_product_review.php?order_id=<?php echo $order_id; ?>" class="action-btn primary">
                                <div class="action-icon">
                                    <i class="fas fa-star"></i>
                                </div>
                                <div class="action-content">
                                    <h6>Leave a Review</h6>
                                    <p>Share your experience</p>
                                </div>
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($order['status'] === 'Pending'): ?>
                            <form action="cancel_order.php" method="post" onsubmit="return confirm('Are you sure you want to cancel this order?');" class="action-form">
                                <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
                                <button type="submit" class="action-btn danger">
                                    <div class="action-icon">
                                        <i class="fas fa-times"></i>
                                    </div>
                                    <div class="action-content">
                                        <h6>Cancel Order</h6>
                                        <p>Cancel this order</p>
                                    </div>
                                </button>
                            </form>
                        <?php endif; ?>
                        
                        <?php if ($order['status'] === 'Shipped'): ?>
                            <a href="#" class="action-btn info" onclick="trackOrder()">
                                <div class="action-icon">
                                    <i class="fas fa-map-marker-alt"></i>
                                </div>
                                <div class="action-content">
                                    <h6>Track Shipment</h6>
                                    <p>Real-time tracking</p>
                                </div>
                            </a>
                        <?php endif; ?>
                        
                        <a href="#" class="action-btn secondary" onclick="window.print();">
                            <div class="action-icon">
                                <i class="fas fa-print"></i>
                            </div>
                            <div class="action-content">
                                <h6>Print Order</h6>
                                <p>Download invoice</p>
                            </div>
                        </a>
                        
                        <?php if ($order['status'] === 'Delivered'): ?>
                            <a href="../shop.php" class="action-btn success">
                                <div class="action-icon">
                                    <i class="fas fa-redo"></i>
                                </div>
                                <div class="action-content">
                                    <h6>Buy Again</h6>
                                    <p>Reorder these items</p>
                                </div>
                            </a>
                        <?php endif; ?>
                        
                        <a href="contact_support.php?order_id=<?php echo $order_id; ?>" class="action-btn secondary">
                            <div class="action-icon">
                                <i class="fas fa-question-circle"></i>
                            </div>
                            <div class="action-content">
                                <h6>Need Help?</h6>
                                <p>Contact support</p>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Order Summary Sidebar -->
            <div class="col-lg-4">
                <!-- Order Summary -->
                <div class="summary-card">
                    <div class="card-header">
                        <h5><i class="fas fa-receipt me-2"></i> Order Summary</h5>
                    </div>
                    
                    <div class="summary-details">
                        <div class="summary-line">
                            <span>Subtotal</span>
                            <span>Rs. <?php echo number_format($order_total, 2); ?></span>
                        </div>
                        
                        <div class="summary-line">
                            <span>Shipping</span>
                            <span class="free-tag">Free</span>
                        </div>
                        
                        <div class="summary-line">
                            <span>Tax</span>
                            <span>Rs. 0.00</span>
                        </div>
                        
                        <hr class="summary-divider">
                        
                        <div class="summary-total">
                            <span>Total</span>
                            <span>Rs. <?php echo number_format($order_total, 2); ?></span>
                        </div>
                    </div>
                </div>
                
                <!-- Shipping Information -->
                <div class="info-card">
                    <div class="card-header">
                        <h5><i class="fas fa-truck me-2"></i> Shipping Details</h5>
                    </div>
                    
                    <div class="info-content">
                        <div class="info-item">
                            <label>Delivery Address</label>
                            <p><?php echo nl2br(htmlspecialchars($order['address'])); ?></p>
                        </div>
                        
                        <div class="info-item">
                            <label>Contact Number</label>
                            <p><?php echo htmlspecialchars($order['contact']); ?></p>
                        </div>
                        
                        <div class="info-item">
                            <label>Delivery Instructions</label>
                            <p>Please handle with care. Ring doorbell upon delivery.</p>
                        </div>
                    </div>
                </div>
                
                <!-- Payment Information -->
                <?php if ($payment): ?>
                    <div class="info-card">
                        <div class="card-header">
                            <h5><i class="fas fa-credit-card me-2"></i> Payment Details</h5>
                        </div>
                        
                        <div class="info-content">
                            <div class="info-item">
                                <label>Payment Method</label>
                                <p><?php echo ucfirst(htmlspecialchars($payment['payment_method'])); ?></p>
                            </div>
                            
                            <div class="info-item">
                                <label>Payment Date</label>
                                <p><?php echo date('F d, Y', strtotime($payment['paymentDate'])); ?></p>
                            </div>
                            
                            <div class="info-item">
                                <label>Payment Status</label>
                                <span class="payment-status <?php echo strtolower($payment['status']); ?>">
                                    <?php echo $payment['status']; ?>
                                </span>
                            </div>
                            
                            <div class="info-item">
                                <label>Amount Paid</label>
                                <p class="amount-paid">Rs. <?php echo number_format($payment['amount'], 2); ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
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
    display: flex;
    align-items: center;
    gap: 16px;
    justify-content: flex-end;
    position: relative;
    z-index: 2;
}

.order-status-badge {
    padding: 8px 16px;
    border-radius: 50px;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.8rem;
    display: flex;
    align-items: center;
    gap: 8px;
    backdrop-filter: blur(10px);
}

.order-status-badge.status-pending { background: rgba(251, 191, 36, 0.2); color: #fbbf24; }
.order-status-badge.status-processing { background: rgba(59, 130, 246, 0.2); color: #3b82f6; }
.order-status-badge.status-shipped { background: rgba(6, 182, 212, 0.2); color: #06b6d4; }
.order-status-badge.status-delivered { background: rgba(16, 185, 129, 0.2); color: #10b981; }
.order-status-badge.status-cancelled { background: rgba(239, 68, 68, 0.2); color: #ef4444; }

.progress-section {
    padding: 40px 0;
    background: #f8f9ff;
}

.progress-card {
    background: white;
    border-radius: 20px;
    padding: 32px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(0, 0, 0, 0.05);
}

.progress-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 32px;
}

.progress-header h5 {
    color: #1e293b;
    font-weight: 600;
    margin: 0;
}

.progress-percentage {
    font-size: 1.2rem;
    font-weight: 700;
    color: #667eea;
}

.progress-timeline {
    display: flex;
    justify-content: space-between;
    position: relative;
}

.progress-timeline::before {
    content: '';
    position: absolute;
    top: 30px;
    left: 60px;
    right: 60px;
    height: 2px;
    background: #e2e8f0;
}

.timeline-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    position: relative;
    flex: 1;
    max-width: 200px;
}

.timeline-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: #e2e8f0;
    color: #9ca3af;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    margin-bottom: 12px;
    position: relative;
    z-index: 2;
    transition: all 0.3s ease;
}

.timeline-item.completed .timeline-icon {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
}

.timeline-item.active .timeline-icon {
    background: #667eea;
    color: white;
    animation: pulse 2s infinite;
}

.timeline-content {
    text-align: center;
}

.timeline-content h6 {
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 4px;
}

.timeline-content p {
    color: #64748b;
    font-size: 0.9rem;
    margin: 0;
}

.order-details-section {
    padding: 80px 0;
    background: white;
}

.items-card, .actions-card, .summary-card, .info-card {
    background: white;
    border-radius: 20px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(0, 0, 0, 0.05);
    margin-bottom: 24px;
}

.card-header {
    padding: 24px 24px 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.card-header h5 {
    color: #1e293b;
    font-weight: 600;
    margin: 0;
    display: flex;
    align-items: center;
}

.item-count {
    background: #f1f5f9;
    color: #64748b;
    padding: 4px 12px;
    border-radius: 50px;
    font-size: 0.8rem;
    font-weight: 500;
}

.items-list {
    padding: 0 24px 24px;
}

.empty-items {
    text-align: center;
    padding: 40px;
    color: #9ca3af;
}

.empty-items i {
    font-size: 3rem;
    margin-bottom: 16px;
}

.item-card {
    display: flex;
    align-items: center;
    gap: 20px;
    padding: 20px;
    border: 1px solid #f1f5f9;
    border-radius: 12px;
    margin-bottom: 16px;
    transition: all 0.3s ease;
}

.item-card:hover {
    border-color: #667eea;
    background: #f8f9ff;
}

.item-image {
    width: 80px;
    height: 80px;
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
    font-size: 1.5rem;
}

.item-details {
    flex: 1;
}

.item-name {
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 4px;
}

.item-brand {
    color: #64748b;
    font-size: 0.9rem;
    margin-bottom: 8px;
}

.item-specs {
    display: flex;
    gap: 16px;
}

.spec {
    background: #f1f5f9;
    color: #64748b;
    padding: 2px 8px;
    border-radius: 6px;
    font-size: 0.8rem;
}

.item-total {
    text-align: right;
}

.total-amount {
    font-size: 1.2rem;
    font-weight: 700;
    color: #667eea;
}

.actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    padding: 0 24px 24px;
}

.action-btn {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 20px;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    text-decoration: none;
    color: #374151;
    transition: all 0.3s ease;
    background: white;
}

.action-btn:hover {
    border-color: #667eea;
    color: #667eea;
    background: #f8f9ff;
}

.action-btn.primary { border-color: #667eea; color: #667eea; }
.action-btn.danger { border-color: #ef4444; color: #ef4444; }
.action-btn.success { border-color: #10b981; color: #10b981; }
.action-btn.info { border-color: #06b6d4; color: #06b6d4; }

.action-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f1f5f9;
    flex-shrink: 0;
}

.action-content h6 {
    font-weight: 600;
    margin-bottom: 2px;
}

.action-content p {
    font-size: 0.8rem;
    opacity: 0.7;
    margin: 0;
}

.action-form {
    width: 100%;
}

.summary-details {
    padding: 0 24px 24px;
}

.summary-line {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}

.free-tag {
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
    font-size: 1.2rem;
    font-weight: 700;
    color: #1e293b;
}

.info-content {
    padding: 0 24px 24px;
}

.info-item {
    margin-bottom: 20px;
}

.info-item:last-child {
    margin-bottom: 0;
}

.info-item label {
    display: block;
    font-weight: 600;
    color: #374151;
    margin-bottom: 6px;
    font-size: 0.9rem;
}

.info-item p {
    color: #64748b;
    margin: 0;
    line-height: 1.5;
}

.payment-status {
    padding: 4px 12px;
    border-radius: 6px;
    font-size: 0.8rem;
    font-weight: 600;
}

.payment-status.completed {
    background: #dcfce7;
    color: #166534;
}

.amount-paid {
    font-size: 1.1rem;
    font-weight: 700;
    color: #667eea !important;
}

@media (max-width: 768px) {
    .page-title {
        font-size: 2.5rem;
    }
    
    .page-actions {
        flex-direction: column;
        align-items: flex-start;
        margin-top: 24px;
    }
    
    .progress-timeline {
        flex-direction: column;
        gap: 24px;
    }
    
    .progress-timeline::before {
        display: none;
    }
    
    .timeline-item {
        flex-direction: row;
        max-width: none;
        text-align: left;
    }
    
    .timeline-icon {
        margin-right: 16px;
        margin-bottom: 0;
    }
    
    .item-card {
        flex-direction: column;
        text-align: center;
    }
    
    .actions-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
function trackOrder() {
    alert('Tracking information: Your order is currently in transit and will be delivered within 1-2 business days.');
}
</script>

<?php
// Include footer
include_once '../includes/footer.php';

// Close database connection
$conn->close();
?>