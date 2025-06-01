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

// Include database connection
require_once '../config/db_connect.php';

$user_id = $_SESSION['user_id'];

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'newest';

// Prepare SQL query
$sql = "SELECT o.*, 
        (SELECT COUNT(*) FROM cart_items ci JOIN cart c ON ci.cartID = c.cartID WHERE c.orderID = o.orderID) as item_count,
        (SELECT SUM(ci.quantity * ci.price) FROM cart_items ci JOIN cart c ON ci.cartID = c.cartID WHERE c.orderID = o.orderID) as total_amount
        FROM `order` o 
        WHERE o.userID = ?";

// Add status filter if specified
if (!empty($status_filter)) {
    $sql .= " AND o.status = ?";
}

// Add sorting
switch ($sort_by) {
    case 'oldest':
        $sql .= " ORDER BY o.date ASC, o.time ASC";
        break;
    case 'price_high':
        $sql .= " ORDER BY total_amount DESC";
        break;
    case 'price_low':
        $sql .= " ORDER BY total_amount ASC";
        break;
    case 'newest':
    default:
        $sql .= " ORDER BY o.date DESC, o.time DESC";
        break;
}

// Prepare and execute the query
$stmt = $conn->prepare($sql);

if (!empty($status_filter)) {
    $stmt->bind_param("is", $user_id, $status_filter);
} else {
    $stmt->bind_param("i", $user_id);
}

$stmt->execute();
$result = $stmt->get_result();
$orders = [];

while ($row = $result->fetch_assoc()) {
    $orders[] = $row;
}

$stmt->close();

// Include header
include_once '../includes/header.php';
?>

<!-- Modern Page Header -->
<section class="page-header-modern">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <div class="page-header-content">
                    <span class="page-badge">🛍️ My Orders</span>
                    <h1 class="page-title">Pet Product <span class="text-gradient">Orders</span></h1>
                    <p class="page-subtitle">Track and manage your pet product orders with real-time updates</p>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="page-actions">
                    <a href="../shop.php" class="btn btn-primary-gradient">
                        <i class="fas fa-shopping-cart me-2"></i> Continue Shopping
                    </a>
                    <a href="dashboard.php" class="btn btn-outline-light">
                        <i class="fas fa-arrow-left me-2"></i> Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Modern Filters Section -->
<section class="filters-section-modern">
    <div class="container">
        <div class="filter-card-modern">
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="get" class="filter-form-modern">
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="filter-label-modern">Filter by Status</label>
                        <select class="form-select modern-select" name="status">
                            <option value="" <?php echo empty($status_filter) ? 'selected' : ''; ?>>All Orders</option>
                            <option value="Pending" <?php echo ($status_filter === 'Pending') ? 'selected' : ''; ?>>Pending</option>
                            <option value="Processing" <?php echo ($status_filter === 'Processing') ? 'selected' : ''; ?>>Processing</option>
                            <option value="Shipped" <?php echo ($status_filter === 'Shipped') ? 'selected' : ''; ?>>Shipped</option>
                            <option value="Delivered" <?php echo ($status_filter === 'Delivered') ? 'selected' : ''; ?>>Delivered</option>
                            <option value="Cancelled" <?php echo ($status_filter === 'Cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    
                    <div class="col-md-4">
                        <label class="filter-label-modern">Sort By</label>
                        <select class="form-select modern-select" name="sort_by">
                            <option value="newest" <?php echo ($sort_by === 'newest') ? 'selected' : ''; ?>>Newest First</option>
                            <option value="oldest" <?php echo ($sort_by === 'oldest') ? 'selected' : ''; ?>>Oldest First</option>
                            <option value="price_high" <?php echo ($sort_by === 'price_high') ? 'selected' : ''; ?>>Price: High to Low</option>
                            <option value="price_low" <?php echo ($sort_by === 'price_low') ? 'selected' : ''; ?>>Price: Low to High</option>
                        </select>
                    </div>
                    
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-primary-gradient w-100">
                            <i class="fas fa-filter me-1"></i> Apply Filters
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</section>

<!-- Modern Orders Content -->
<section class="content-section-modern">
    <div class="container">
        <?php if (empty($orders)): ?>
            <div class="empty-state-modern">
                <div class="empty-icon">
                    <i class="fas fa-shopping-bag"></i>
                </div>
                <h4>No Orders Found</h4>
                <p>You don't have any orders<?php echo !empty($status_filter) ? " with status '$status_filter'" : ""; ?>.</p>
                <a href="../shop.php" class="btn btn-primary-gradient">
                    <i class="fas fa-store me-2"></i> Browse Products
                </a>
            </div>
        <?php else: ?>
            <div class="content-header">
                <h3>Your Orders <span class="count-badge"><?php echo count($orders); ?> total</span></h3>
            </div>
            
            <div class="orders-grid">
                <?php foreach ($orders as $order): ?>
                    <div class="order-card-modern">
                        <div class="order-status-indicator status-<?php echo strtolower($order['status']); ?>"></div>
                        
                        <div class="order-header">
                            <div class="order-info">
                                <h5 class="order-title">Order #<?php echo $order['orderID']; ?></h5>
                                <span class="order-status-badge status-<?php echo strtolower($order['status']); ?>">
                                    <?php echo $order['status']; ?>
                                </span>
                            </div>
                            <div class="order-amount">
                                <span class="amount-value">Rs. <?php echo number_format($order['total_amount'], 2); ?></span>
                            </div>
                        </div>
                        
                        <div class="order-details">
                            <div class="detail-item">
                                <div class="detail-icon">
                                    <i class="fas fa-calendar-alt"></i>
                                </div>
                                <div class="detail-content">
                                    <span class="detail-label">Order Date</span>
                                    <span class="detail-value"><?php echo date('M d, Y', strtotime($order['date'])) . ' at ' . date('h:i A', strtotime($order['time'])); ?></span>
                                </div>
                            </div>
                            
                            <div class="detail-item">
                                <div class="detail-icon">
                                    <i class="fas fa-box"></i>
                                </div>
                                <div class="detail-content">
                                    <span class="detail-label">Items</span>
                                    <span class="detail-value"><?php echo $order['item_count']; ?> product(s)</span>
                                </div>
                            </div>
                            
                            <div class="detail-item">
                                <div class="detail-icon">
                                    <i class="fas fa-map-marker-alt"></i>
                                </div>
                                <div class="detail-content">
                                    <span class="detail-label">Delivery Address</span>
                                    <span class="detail-value"><?php echo substr(htmlspecialchars($order['address']), 0, 50) . (strlen($order['address']) > 50 ? '...' : ''); ?></span>
                                </div>
                            </div>
                            
                            <?php if ($order['status'] === 'Shipped'): ?>
                                <div class="detail-item">
                                    <div class="detail-icon">
                                        <i class="fas fa-truck"></i>
                                    </div>
                                    <div class="detail-content">
                                        <span class="detail-label">Tracking</span>
                                        <span class="detail-value">In Transit</span>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="order-progress">
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php 
                                    switch($order['status']) {
                                        case 'Pending': echo '25%'; break;
                                        case 'Processing': echo '50%'; break;
                                        case 'Shipped': echo '75%'; break;
                                        case 'Delivered': echo '100%'; break;
                                        case 'Cancelled': echo '0%'; break;
                                        default: echo '25%';
                                    }
                                ?>"></div>
                            </div>
                            <div class="progress-labels">
                                <span class="progress-label <?php echo in_array($order['status'], ['Processing', 'Shipped', 'Delivered']) ? 'active' : ''; ?>">Processing</span>
                                <span class="progress-label <?php echo in_array($order['status'], ['Shipped', 'Delivered']) ? 'active' : ''; ?>">Shipped</span>
                                <span class="progress-label <?php echo $order['status'] === 'Delivered' ? 'active' : ''; ?>">Delivered</span>
                            </div>
                        </div>
                        
                        <div class="order-actions">
                            <a href="order_details.php?id=<?php echo $order['orderID']; ?>" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-eye me-1"></i> View Details
                            </a>
                            
                            <?php if ($order['status'] === 'Delivered'): ?>
                                <a href="add_product_review.php?order_id=<?php echo $order['orderID']; ?>" class="btn btn-primary-gradient btn-sm">
                                    <i class="fas fa-star me-1"></i> Review
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($order['status'] === 'Shipped'): ?>
                                <button class="btn btn-outline-info btn-sm" onclick="trackOrder(<?php echo $order['orderID']; ?>)">
                                    <i class="fas fa-map-marker-alt me-1"></i> Track
                                </button>
                            <?php endif; ?>
                            
                            <?php if ($order['status'] === 'Pending'): ?>
                                <form action="cancel_order.php" method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to cancel this order?');">
                                    <input type="hidden" name="order_id" value="<?php echo $order['orderID']; ?>">
                                    <button type="submit" class="btn btn-outline-danger btn-sm">
                                        <i class="fas fa-times me-1"></i> Cancel
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
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

.page-actions .btn {
    margin-left: 12px;
}

.filters-section-modern {
    padding: 40px 0;
    background: #f8f9ff;
}

.filter-card-modern {
    background: white;
    border-radius: 20px;
    padding: 32px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(0, 0, 0, 0.05);
}

.filter-label-modern {
    font-weight: 600;
    color: #374151;
    margin-bottom: 8px;
    display: block;
    font-size: 0.9rem;
}

.modern-select {
    padding: 12px 16px;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    transition: all 0.3s ease;
    background: white;
}

.modern-select:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.content-section-modern {
    padding: 80px 0;
    background: white;
}

.empty-state-modern {
    text-align: center;
    padding: 80px 40px;
    background: #f8f9ff;
    border-radius: 20px;
    border: 2px dashed #d1d5db;
    max-width: 500px;
    margin: 0 auto;
}

.empty-icon {
    font-size: 4rem;
    color: #9ca3af;
    margin-bottom: 24px;
}

.empty-state-modern h4 {
    color: #374151;
    margin-bottom: 16px;
    font-weight: 600;
}

.empty-state-modern p {
    color: #6b7280;
    margin-bottom: 32px;
}

.content-header {
    margin-bottom: 40px;
}

.content-header h3 {
    font-size: 2rem;
    font-weight: 700;
    color: #1e293b;
    margin: 0;
}

.count-badge {
    background: linear-gradient(135deg, #f1f5f9, #e2e8f0);
    color: #64748b;
    padding: 4px 12px;
    border-radius: 50px;
    font-size: 0.8rem;
    font-weight: 500;
    margin-left: 12px;
}

.orders-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(450px, 1fr));
    gap: 24px;
}

.order-card-modern {
    background: white;
    border-radius: 20px;
    padding: 24px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(0, 0, 0, 0.05);
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.order-card-modern:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
}

.order-status-indicator {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
}

.order-status-indicator.status-pending { background: #f59e0b; }
.order-status-indicator.status-processing { background: #3b82f6; }
.order-status-indicator.status-shipped { background: #06b6d4; }
.order-status-indicator.status-delivered { background: #10b981; }
.order-status-indicator.status-cancelled { background: #ef4444; }

.order-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 20px;
}

.order-info {
    flex: 1;
}

.order-title {
    font-size: 1.2rem;
    font-weight: 600;
    color: #1e293b;
    margin: 0 0 8px 0;
}

.order-status-badge {
    padding: 6px 12px;
    border-radius: 50px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.order-status-badge.status-pending { background: #fef3c7; color: #92400e; }
.order-status-badge.status-processing { background: #dbeafe; color: #1e40af; }
.order-status-badge.status-shipped { background: #cffafe; color: #0f766e; }
.order-status-badge.status-delivered { background: #dcfce7; color: #166534; }
.order-status-badge.status-cancelled { background: #fee2e2; color: #991b1b; }

.order-amount {
    text-align: right;
}

.amount-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: #667eea;
}

.order-details {
    margin-bottom: 20px;
}

.detail-item {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 12px;
}

.detail-item:last-child {
    margin-bottom: 0;
}

.detail-icon {
    width: 36px;
    height: 36px;
    background: linear-gradient(135deg, #f1f5f9, #e2e8f0);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #667eea;
    flex-shrink: 0;
}

.detail-content {
    display: flex;
    flex-direction: column;
    gap: 2px;
    flex: 1;
}

.detail-label {
    font-size: 0.8rem;
    color: #9ca3af;
    font-weight: 500;
}

.detail-value {
    color: #374151;
    font-weight: 500;
}

.order-progress {
    margin-bottom: 20px;
    padding: 16px;
    background: #f8fafc;
    border-radius: 12px;
}

.progress-bar {
    height: 4px;
    background: #e2e8f0;
    border-radius: 2px;
    overflow: hidden;
    margin-bottom: 8px;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #667eea, #764ba2);
    border-radius: 2px;
    transition: width 0.3s ease;
}

.progress-labels {
    display: flex;
    justify-content: space-between;
}

.progress-label {
    font-size: 0.75rem;
    color: #9ca3af;
    font-weight: 500;
}

.progress-label.active {
    color: #667eea;
    font-weight: 600;
}

.order-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.order-actions .btn {
    flex: 1;
    min-width: 110px;
}

@media (max-width: 768px) {
    .page-title {
        font-size: 2.5rem;
    }
    
    .page-actions {
        text-align: left;
        margin-top: 24px;
    }
    
    .page-actions .btn {
        margin-left: 0;
        margin-right: 12px;
        margin-bottom: 8px;
    }
    
    .orders-grid {
        grid-template-columns: 1fr;
    }
    
    .filter-card-modern {
        padding: 24px;
    }
    
    .order-header {
        flex-direction: column;
        gap: 12px;
    }
    
    .order-amount {
        text-align: left;
    }
    
    .order-actions {
        flex-direction: column;
    }
    
    .order-actions .btn {
        min-width: auto;
    }
}
</style>

<script>
function trackOrder(orderId) {
    // Mock tracking functionality
    alert('Tracking information for Order #' + orderId + ' will be available soon!');
}
</script>

<?php
// Include footer
include_once '../includes/footer.php';

// Close database connection
$conn->close();
?>