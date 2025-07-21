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

// Include database connection
require_once '../config/db_connect.php';

// Handle order status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $order_id = intval($_POST['order_id']);
    $new_status = trim($_POST['status']);
    
    $valid_statuses = ['Pending', 'Processing', 'Shipped', 'Delivered', 'Cancelled'];
    
    if (in_array($new_status, $valid_statuses) && $order_id > 0) {
        $update_sql = "UPDATE `order` SET status = ? WHERE orderID = ?";
        $update_stmt = $conn->prepare($update_sql);
        
        if ($update_stmt) {
            $update_stmt->bind_param("si", $new_status, $order_id);
            
            if ($update_stmt->execute()) {
                if ($update_stmt->affected_rows > 0) {
                    $_SESSION['success_message'] = "Order #$order_id status updated to $new_status successfully.";
                } else {
                    $_SESSION['error_message'] = "No changes made to Order #$order_id.";
                }
            } else {
                $_SESSION['error_message'] = "Failed to update order status: " . $update_stmt->error;
            }
            $update_stmt->close();
        } else {
            $_SESSION['error_message'] = "Database error: " . $conn->error;
        }
    } else {
        $_SESSION['error_message'] = "Invalid status or order ID.";
    }
    
    // Redirect to prevent form resubmission
    $redirect_url = $_SERVER['PHP_SELF'];
    if (!empty($_SERVER['QUERY_STRING'])) {
        $redirect_url .= '?' . $_SERVER['QUERY_STRING'];
    }
    header("Location: $redirect_url");
    exit();
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build query with filters
$where_conditions = ['1=1'];
$params = [];
$types = '';

if (!empty($status_filter)) {
    $where_conditions[] = "o.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if (!empty($date_from)) {
    $where_conditions[] = "o.date >= ?";
    $params[] = $date_from;
    $types .= 's';
}

if (!empty($date_to)) {
    $where_conditions[] = "o.date <= ?";
    $params[] = $date_to;
    $types .= 's';
}

if (!empty($search)) {
    $where_conditions[] = "(po.fullName LIKE ? OR po.email LIKE ? OR o.orderID LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sss';
}

$where_clause = implode(' AND ', $where_conditions);

// Get orders with customer details and totals
$orders_sql = "SELECT o.*, 
                      po.fullName as customerName, 
                      po.email as customerEmail,
                      po.contact as customerPhone,
                      c.total_amount,
                      (SELECT COUNT(*) FROM cart_items ci WHERE ci.cartID = c.cartID) as item_count
               FROM `order` o 
               JOIN pet_owner po ON o.userID = po.userID 
               LEFT JOIN cart c ON o.orderID = c.orderID
               WHERE $where_clause
               ORDER BY o.date DESC, o.time DESC";

$orders_stmt = $conn->prepare($orders_sql);
if (!empty($params)) {
    $orders_stmt->bind_param($types, ...$params);
}
$orders_stmt->execute();
$orders_result = $orders_stmt->get_result();

$orders = [];
while ($row = $orders_result->fetch_assoc()) {
    $orders[] = $row;
}
$orders_stmt->close();

// Get order statistics
$stats_sql = "SELECT 
                COUNT(*) as total_orders,
                SUM(CASE WHEN o.status = 'Pending' THEN 1 ELSE 0 END) as pending_orders,
                SUM(CASE WHEN o.status = 'Processing' THEN 1 ELSE 0 END) as processing_orders,
                SUM(CASE WHEN o.status = 'Shipped' THEN 1 ELSE 0 END) as shipped_orders,
                SUM(CASE WHEN o.status = 'Delivered' THEN 1 ELSE 0 END) as delivered_orders,
                SUM(CASE WHEN o.status = 'Cancelled' THEN 1 ELSE 0 END) as cancelled_orders,
                COALESCE(SUM(c.total_amount), 0) as total_revenue
              FROM `order` o 
              LEFT JOIN cart c ON o.orderID = c.orderID";
$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();

// Include header
include_once '../includes/header.php';
?>

<style>
/* Modern Admin Orders Styles */
.orders-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 60px 0;
    text-align: center;
}

.orders-title {
    font-size: 2.5rem;
    font-weight: 700;
    margin-bottom: 15px;
}

.orders-subtitle {
    font-size: 1.1rem;
    opacity: 0.9;
    margin-bottom: 0;
}

.orders-content {
    padding: 60px 0;
    background: #f8f9fa;
}

/* Alert Messages */
.alert {
    border: none;
    border-radius: 10px;
    padding: 15px 20px;
    margin-bottom: 25px;
    display: flex;
    align-items: center;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    transition: opacity 0.3s ease;
}

.alert-success {
    background: linear-gradient(135deg, #d1fae5, #a7f3d0);
    color: #065f46;
    border-left: 4px solid #10b981;
}

.alert-danger {
    background: linear-gradient(135deg, #fee2e2, #fecaca);
    color: #991b1b;
    border-left: 4px solid #ef4444;
}

.btn-close {
    background: none;
    border: none;
    font-size: 1.2rem;
    opacity: 0.6;
    margin-left: auto;
    cursor: pointer;
}

.btn-close:hover {
    opacity: 1;
}

/* Statistics Cards */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 40px;
}

.stat-card {
    background: white;
    padding: 25px;
    border-radius: 15px;
    text-align: center;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
}

.stat-number {
    font-size: 2rem;
    font-weight: 800;
    margin-bottom: 8px;
    display: block;
}

.stat-number.total { color: #667eea; }
.stat-number.pending { color: #f59e0b; }
.stat-number.processing { color: #3b82f6; }
.stat-number.shipped { color: #8b5cf6; }
.stat-number.delivered { color: #10b981; }
.stat-number.cancelled { color: #ef4444; }
.stat-number.revenue { color: #059669; }

.stat-label {
    color: #64748b;
    font-weight: 600;
    font-size: 0.9rem;
}

/* Filters */
.filters-section {
    background: white;
    padding: 30px;
    border-radius: 15px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    margin-bottom: 30px;
}

.filters-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    align-items: end;
}

.filter-group label {
    display: block;
    font-weight: 600;
    color: #374151;
    margin-bottom: 8px;
}

.filter-control {
    width: 100%;
    padding: 10px 12px;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    font-size: 0.95rem;
    transition: all 0.3s ease;
}

.filter-control:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.btn-filter {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    border: none;
    padding: 12px 20px;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-filter:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
}

/* Orders Table */
.orders-table-section {
    background: white;
    border-radius: 15px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    overflow: hidden;
}

.table-header {
    background: linear-gradient(135deg, #f8f9ff, #f1f5f9);
    padding: 25px 30px;
    border-bottom: 1px solid #e5e7eb;
}

.table-title {
    font-size: 1.3rem;
    font-weight: 700;
    color: #1e293b;
    margin: 0;
}

.orders-table {
    width: 100%;
    margin: 0;
}

.orders-table th {
    background: #f8f9fa;
    padding: 15px 20px;
    font-weight: 600;
    color: #374151;
    border-bottom: 2px solid #e5e7eb;
    text-align: left;
}

.orders-table td {
    padding: 20px;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
}

.orders-table tr:hover {
    background: #f8f9fa;
}

/* Customer Info */
.customer-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.customer-avatar {
    width: 40px;
    height: 40px;
    background: #667eea;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 0.9rem;
}

.customer-details h6 {
    margin: 0;
    font-weight: 600;
    color: #1e293b;
    font-size: 0.9rem;
}

.customer-details small {
    color: #64748b;
    font-size: 0.8rem;
}

/* Status Update Form */
.status-form {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0;
}

.status-select {
    padding: 6px 10px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 0.85rem;
    background: white;
    min-width: 120px;
}

.status-select:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.1);
}

.btn-update {
    background: #10b981;
    color: white;
    border: none;
    padding: 6px 10px;
    border-radius: 6px;
    font-size: 0.85rem;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
}

.btn-update:hover {
    background: #059669;
    transform: scale(1.05);
}

.btn-update:disabled {
    background: #9ca3af;
    cursor: not-allowed;
    transform: none;
}

/* Order Actions */
.order-actions {
    display: flex;
    gap: 8px;
    align-items: center;
}

.btn-action {
    padding: 6px 12px;
    border: none;
    border-radius: 6px;
    font-size: 0.8rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.btn-view {
    background: #e0e7ff;
    color: #3730a3;
}

.btn-view:hover {
    background: #c7d2fe;
    color: #312e81;
    text-decoration: none;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #9ca3af;
}

.empty-icon {
    font-size: 4rem;
    margin-bottom: 20px;
    color: #d1d5db;
}

/* Responsive */
@media (max-width: 768px) {
    .orders-title {
        font-size: 2rem;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 15px;
    }
    
    .filters-grid {
        grid-template-columns: 1fr;
    }
    
    .orders-table {
        font-size: 0.9rem;
    }
    
    .orders-table th,
    .orders-table td {
        padding: 12px 15px;
    }
    
    .customer-info {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
    }
    
    .order-actions {
        flex-direction: column;
        gap: 4px;
    }
}
</style>

<!-- Orders Header -->
<section class="orders-header">
    <div class="container">
        <h1 class="orders-title">
            <i class="fas fa-shopping-cart me-3"></i>
            Order Management
        </h1>
        <p class="orders-subtitle">Monitor and manage all customer orders</p>
    </div>
</section>

<!-- Orders Content -->
<section class="orders-content">
    <div class="container">
        
        <!-- Success/Error Messages -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?php 
                echo htmlspecialchars($_SESSION['success_message']); 
                unset($_SESSION['success_message']);
                ?>
                <button type="button" class="btn-close" onclick="this.parentElement.remove()"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php 
                echo htmlspecialchars($_SESSION['error_message']); 
                unset($_SESSION['error_message']);
                ?>
                <button type="button" class="btn-close" onclick="this.parentElement.remove()"></button>
            </div>
        <?php endif; ?>
        
        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <span class="stat-number total"><?php echo $stats['total_orders']; ?></span>
                <div class="stat-label">Total Orders</div>
            </div>
            <div class="stat-card">
                <span class="stat-number pending"><?php echo $stats['pending_orders']; ?></span>
                <div class="stat-label">Pending</div>
            </div>
            <div class="stat-card">
                <span class="stat-number processing"><?php echo $stats['processing_orders']; ?></span>
                <div class="stat-label">Processing</div>
            </div>
            <div class="stat-card">
                <span class="stat-number shipped"><?php echo $stats['shipped_orders']; ?></span>
                <div class="stat-label">Shipped</div>
            </div>
            <div class="stat-card">
                <span class="stat-number delivered"><?php echo $stats['delivered_orders']; ?></span>
                <div class="stat-label">Delivered</div>
            </div>
            <div class="stat-card">
                <span class="stat-number revenue">Rs.<?php echo number_format($stats['total_revenue'], 2); ?></span>
                <div class="stat-label">Total Revenue</div>
            </div>
        </div>

        <!-- Filters Section -->
        <div class="filters-section">
            <form method="GET" action="">
                <div class="filters-grid">
                    <div class="filter-group">
                        <label for="search">Search Orders</label>
                        <input type="text" id="search" name="search" class="filter-control" 
                               placeholder="Search by customer, email, or order ID..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label for="status">Status</label>
                        <select id="status" name="status" class="filter-control">
                            <option value="">All Statuses</option>
                            <option value="Pending" <?php echo $status_filter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="Processing" <?php echo $status_filter === 'Processing' ? 'selected' : ''; ?>>Processing</option>
                            <option value="Shipped" <?php echo $status_filter === 'Shipped' ? 'selected' : ''; ?>>Shipped</option>
                            <option value="Delivered" <?php echo $status_filter === 'Delivered' ? 'selected' : ''; ?>>Delivered</option>
                            <option value="Cancelled" <?php echo $status_filter === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="date_from">From Date</label>
                        <input type="date" id="date_from" name="date_from" class="filter-control" 
                               value="<?php echo htmlspecialchars($date_from); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label for="date_to">To Date</label>
                        <input type="date" id="date_to" name="date_to" class="filter-control" 
                               value="<?php echo htmlspecialchars($date_to); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <button type="submit" class="btn-filter">
                            <i class="fas fa-search me-2"></i>Filter
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Orders Table -->
        <div class="orders-table-section">
            <div class="table-header">
                <h3 class="table-title">
                    <i class="fas fa-list me-2"></i>
                    Orders List (<?php echo count($orders); ?> orders)
                </h3>
            </div>
            
            <?php if (empty($orders)): ?>
                <div class="empty-state">
                    <div class="empty-icon"><i class="fas fa-shopping-cart"></i></div>
                    <h4>No Orders Found</h4>
                    <p>No orders match your current filters.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="orders-table">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Customer</th>
                                <th>Status</th>
                                <th>Items</th>
                                <th>Total</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td>
                                        <strong>#<?php echo $order['orderID']; ?></strong>
                                    </td>
                                    <td>
                                        <div class="customer-info">
                                            <div class="customer-avatar">
                                                <?php echo strtoupper(substr($order['customerName'], 0, 1)); ?>
                                            </div>
                                            <div class="customer-details">
                                                <h6><?php echo htmlspecialchars($order['customerName']); ?></h6>
                                                <small><?php echo htmlspecialchars($order['customerEmail']); ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <form method="POST" class="status-form">
                                            <input type="hidden" name="order_id" value="<?php echo $order['orderID']; ?>">
                                            <select name="status" class="status-select" data-original="<?php echo $order['status']; ?>">
                                                <option value="Pending" <?php echo $order['status'] === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                                <option value="Processing" <?php echo $order['status'] === 'Processing' ? 'selected' : ''; ?>>Processing</option>
                                                <option value="Shipped" <?php echo $order['status'] === 'Shipped' ? 'selected' : ''; ?>>Shipped</option>
                                                <option value="Delivered" <?php echo $order['status'] === 'Delivered' ? 'selected' : ''; ?>>Delivered</option>
                                                <option value="Cancelled" <?php echo $order['status'] === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                            </select>
                                            <button type="submit" name="update_status" class="btn-update" title="Update Status">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        </form>
                                    </td>
                                    <td>
                                        <strong><?php echo $order['item_count'] ?? 0; ?></strong> items
                                    </td>
                                    <td>
                                        <strong>Rs.<?php echo number_format($order['total_amount'] ?? 0, 2); ?></strong>
                                    </td>
                                    <td>
                                        <?php echo date('M d, Y', strtotime($order['date'])); ?><br>
                                        <small><?php echo date('g:i A', strtotime($order['time'])); ?></small>
                                    </td>
                                    <td>
                                        <div class="order-actions">
                                            <a href="view_order_details.php?id=<?php echo $order['orderID']; ?>" class="btn-action btn-view">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Back to Dashboard -->
        <div class="text-center mt-4">
            <a href="dashboard.php" class="btn btn-outline-primary">
                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
            </a>
        </div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const statusForms = document.querySelectorAll('.status-form');
    
    statusForms.forEach(form => {
        const statusSelect = form.querySelector('select[name="status"]');
        const updateBtn = form.querySelector('.btn-update');
        
        // Store original status when page loads
        if (statusSelect) {
            const originalStatus = statusSelect.value;
            statusSelect.setAttribute('data-original-status', originalStatus);
        }
        
        // Handle status change
        if (statusSelect) {
            statusSelect.addEventListener('change', function() {
                const updateBtn = form.querySelector('.btn-update');
                if (updateBtn) {
                    updateBtn.style.display = 'inline-block';
                    updateBtn.style.opacity = '1';
                }
            });
        }
        
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const orderIdInput = this.querySelector('input[name="order_id"]');
            const statusSelect = this.querySelector('select[name="status"]');
            const updateBtn = this.querySelector('.btn-update');
            
            if (!orderIdInput || !statusSelect || !updateBtn) {
                console.error('Required form elements not found');
                return;
            }
            
            const orderId = orderIdInput.value;
            const newStatus = statusSelect.value;
            const originalStatus = statusSelect.getAttribute('data-original-status') || statusSelect.getAttribute('data-original');
            
            console.log('Form submission:', {
                orderId: orderId,
                newStatus: newStatus,
                originalStatus: originalStatus
            });
            
            // Validate required fields
            if (!orderId || !newStatus) {
                alert('Please ensure order ID and status are provided.');
                return;
            }
            
            // Check if status actually changed (but allow submission if we can't determine original)
            if (originalStatus && newStatus === originalStatus) {
                alert('Please select a different status to update.');
                return;
            }
            
            // Confirm the change
            if (confirm(`Are you sure you want to change Order #${orderId} status to ${newStatus}?`)) {
                // Show loading state
                const originalBtnHTML = updateBtn.innerHTML;
                updateBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                updateBtn.disabled = true;
                
                // Submit the form
                this.submit();
            } else {
                // Reset to original value if cancelled
                if (originalStatus) {
                    statusSelect.value = originalStatus;
                }
            }
        });
    });
});

// Auto-hide alert messages
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            if (alert.parentNode) {
                alert.style.opacity = '0';
                setTimeout(() => {
                    if (alert.parentNode) {
                        alert.remove();
                    }
                }, 300);
            }
        }, 5000);
    });
});

// Clear filters function
function clearFilters() {
    window.location.href = 'order_management.php';
}
</script>

<?php
// Include footer
include_once '../includes/footer.php';

// Close database connection
$conn->close();
?>