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

<!-- Page Header -->
<div class="container-fluid bg-light py-4">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="display-5 mb-2">My Orders</h1>
                <p class="lead">Track and manage your pet products orders.</p>
            </div>
            <div class="col-md-4 text-md-end">
                <a href="../shop.php" class="btn btn-primary">
                    <i class="fas fa-shopping-cart me-1"></i> Continue Shopping
                </a>
                <a href="dashboard.php" class="btn btn-outline-secondary ms-2">
                    <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Orders Management -->
<div class="container py-5">
    <!-- Filtering and Sorting Options -->
    <div class="card mb-4">
        <div class="card-body">
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="get" class="row g-3">
                <div class="col-md-5">
                    <label for="status" class="form-label">Filter by Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="" <?php echo empty($status_filter) ? 'selected' : ''; ?>>All Orders</option>
                        <option value="Pending" <?php echo ($status_filter === 'Pending') ? 'selected' : ''; ?>>Pending</option>
                        <option value="Processing" <?php echo ($status_filter === 'Processing') ? 'selected' : ''; ?>>Processing</option>
                        <option value="Shipped" <?php echo ($status_filter === 'Shipped') ? 'selected' : ''; ?>>Shipped</option>
                        <option value="Delivered" <?php echo ($status_filter === 'Delivered') ? 'selected' : ''; ?>>Delivered</option>
                        <option value="Cancelled" <?php echo ($status_filter === 'Cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
                
                <div class="col-md-5">
                    <label for="sort_by" class="form-label">Sort By</label>
                    <select class="form-select" id="sort_by" name="sort_by">
                        <option value="newest" <?php echo ($sort_by === 'newest') ? 'selected' : ''; ?>>Newest First</option>
                        <option value="oldest" <?php echo ($sort_by === 'oldest') ? 'selected' : ''; ?>>Oldest First</option>
                        <option value="price_high" <?php echo ($sort_by === 'price_high') ? 'selected' : ''; ?>>Price: High to Low</option>
                        <option value="price_low" <?php echo ($sort_by === 'price_low') ? 'selected' : ''; ?>>Price: Low to High</option>
                    </select>
                </div>
                
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Apply</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Orders List -->
    <?php if (empty($orders)): ?>
        <div class="alert alert-info">
            <h4 class="alert-heading">No Orders Found</h4>
            <p>You don't have any orders<?php echo !empty($status_filter) ? " with status '$status_filter'" : ""; ?>.</p>
            <hr>
            <p class="mb-0">Click the "Continue Shopping" button to browse our pet products.</p>
        </div>
    <?php else: ?>
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Your Orders (<?php echo count($orders); ?>)</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Order #</th>
                                <th>Date</th>
                                <th>Items</th>
                                <th>Total Amount</th>
                                <th>Shipping Address</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td><?php echo $order['orderID']; ?></td>
                                    <td><?php echo date('M d, Y', strtotime($order['date'])) . ' at ' . date('h:i A', strtotime($order['time'])); ?></td>
                                    <td><?php echo $order['item_count']; ?> item(s)</td>
                                    <td>$<?php echo number_format($order['total_amount'], 2); ?></td>
                                    <td>
                                        <small><?php echo substr(htmlspecialchars($order['address']), 0, 30) . (strlen($order['address']) > 30 ? '...' : ''); ?></small>
                                    </td>
                                    <td>
                                        <?php
                                        $status_class = '';
                                        switch ($order['status']) {
                                            case 'Pending':
                                                $status_class = 'bg-warning';
                                                break;
                                            case 'Processing':
                                                $status_class = 'bg-primary';
                                                break;
                                            case 'Shipped':
                                                $status_class = 'bg-info';
                                                break;
                                            case 'Delivered':
                                                $status_class = 'bg-success';
                                                break;
                                            case 'Cancelled':
                                                $status_class = 'bg-danger';
                                                break;
                                        }
                                        ?>
                                        <span class="badge <?php echo $status_class; ?>"><?php echo $order['status']; ?></span>
                                    </td>
                                    <td>
                                        <a href="order_details.php?id=<?php echo $order['orderID']; ?>" class="btn btn-sm btn-outline-primary" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        
                                        <?php if ($order['status'] === 'Delivered'): ?>
                                            <a href="add_product_review.php?order_id=<?php echo $order['orderID']; ?>" class="btn btn-sm btn-outline-success" title="Leave Review">
                                                <i class="fas fa-star"></i>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if ($order['status'] === 'Shipped'): ?>
                                            <a href="#" class="btn btn-sm btn-outline-info" title="Track Order">
                                                <i class="fas fa-truck"></i>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if ($order['status'] === 'Pending'): ?>
                                            <form action="cancel_order.php" method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to cancel this order?');">
                                                <input type="hidden" name="order_id" value="<?php echo $order['orderID']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Cancel Order">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Order Details Modal -->
<div class="modal fade" id="orderDetailsModal" tabindex="-1" aria-labelledby="orderDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="orderDetailsModalLabel">Order Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="orderDetailsContent">
                    Loading...
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript for Order Details Modal -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Function to load order details via AJAX
    function loadOrderDetails(orderId) {
        fetch(`order_details_ajax.php?id=${orderId}`)
            .then(response => response.text())
            .then(data => {
                document.getElementById('orderDetailsContent').innerHTML = data;
            })
            .catch(error => {
                console.error('Error loading order details:', error);
                document.getElementById('orderDetailsContent').innerHTML = 'Error loading order details. Please try again.';
            });
    }
    
    // Event listeners for order detail buttons
    const orderDetailButtons = document.querySelectorAll('.view-order-details');
    orderDetailButtons.forEach(button => {
        button.addEventListener('click', function() {
            const orderId = this.getAttribute('data-order-id');
            loadOrderDetails(orderId);
            
            // Update modal title
            document.getElementById('orderDetailsModalLabel').innerText = `Order #${orderId} Details`;
            
            // Show the modal
            const orderDetailsModal = new bootstrap.Modal(document.getElementById('orderDetailsModal'));
            orderDetailsModal.show();
        });
    });
});
</script>

<?php
// Include footer
include_once '../includes/footer.php';

// Close database connection
$conn->close();
?>