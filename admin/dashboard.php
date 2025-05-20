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

// Get statistics for dashboard

// Total users
$users_sql = "SELECT 
                (SELECT COUNT(*) FROM pet_owner) as pet_owners_count,
                (SELECT COUNT(*) FROM pet_sitter) as pet_sitters_count";
$users_result = $conn->query($users_sql);
$users_stats = $users_result->fetch_assoc();
$total_users = $users_stats['pet_owners_count'] + $users_stats['pet_sitters_count'];

// Total pets
$pets_sql = "SELECT COUNT(*) as total FROM pet_profile";
$pets_result = $conn->query($pets_sql);
$pets_count = $pets_result->fetch_assoc()['total'];

// Total bookings
$bookings_sql = "SELECT 
                  COUNT(*) as total,
                  COUNT(CASE WHEN status = 'Pending' THEN 1 END) as pending,
                  COUNT(CASE WHEN status = 'Confirmed' THEN 1 END) as confirmed,
                  COUNT(CASE WHEN status = 'Completed' THEN 1 END) as completed,
                  COUNT(CASE WHEN status = 'Cancelled' THEN 1 END) as cancelled
                FROM booking";
$bookings_result = $conn->query($bookings_sql);
$bookings_stats = $bookings_result->fetch_assoc();

// Total orders
$orders_sql = "SELECT 
                COUNT(*) as total,
                COUNT(CASE WHEN status = 'Pending' THEN 1 END) as pending,
                COUNT(CASE WHEN status = 'Processing' THEN 1 END) as processing,
                COUNT(CASE WHEN status = 'Shipped' THEN 1 END) as shipped,
                COUNT(CASE WHEN status = 'Delivered' THEN 1 END) as delivered,
                COUNT(CASE WHEN status = 'Cancelled' THEN 1 END) as cancelled
              FROM `order`";
$orders_result = $conn->query($orders_sql);
$orders_stats = $orders_result->fetch_assoc();

// Total products
$products_sql = "SELECT COUNT(*) as total FROM pet_food_and_accessories";
$products_result = $conn->query($products_sql);
$products_count = $products_result->fetch_assoc()['total'];

// Total services
$services_sql = "SELECT COUNT(*) as total FROM pet_service";
$services_result = $conn->query($services_sql);
$services_count = $services_result->fetch_assoc()['total'];

// Total revenue
$revenue_sql = "SELECT SUM(amount) as total FROM payment WHERE status = 'Completed'";
$revenue_result = $conn->query($revenue_sql);
$total_revenue = $revenue_result->fetch_assoc()['total'] ?: 0;

// Recent bookings
$recent_bookings_sql = "SELECT b.*, po.fullName as ownerName, ps.fullName as sitterName 
                        FROM booking b 
                        JOIN pet_owner po ON b.userID = po.userID 
                        JOIN pet_sitter ps ON b.sitterID = ps.userID 
                        ORDER BY b.created_at DESC LIMIT 5";
$recent_bookings_result = $conn->query($recent_bookings_sql);
$recent_bookings = [];
while ($row = $recent_bookings_result->fetch_assoc()) {
    $recent_bookings[] = $row;
}

// Recent orders
$recent_orders_sql = "SELECT o.*, po.fullName as customerName 
                     FROM `order` o 
                     JOIN pet_owner po ON o.userID = po.userID 
                     ORDER BY o.date DESC, o.time DESC LIMIT 5";
$recent_orders_result = $conn->query($recent_orders_sql);
$recent_orders = [];
while ($row = $recent_orders_result->fetch_assoc()) {
    $recent_orders[] = $row;
}

// Recent users
$recent_users_sql = "SELECT * FROM (
                      SELECT userID, 'pet_owner' as user_type, fullName, email, created_at 
                      FROM pet_owner 
                      UNION ALL 
                      SELECT userID, 'pet_sitter' as user_type, fullName, email, created_at 
                      FROM pet_sitter
                    ) as users 
                    ORDER BY created_at DESC LIMIT 5";
$recent_users_result = $conn->query($recent_users_sql);
$recent_users = [];
while ($row = $recent_users_result->fetch_assoc()) {
    $recent_users[] = $row;
}

// Include header
include_once '../includes/header.php';
?>

<!-- Page Header -->
<div class="container-fluid bg-primary text-white py-4">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="display-4 mb-2">Admin Dashboard</h1>
                <p class="lead">Manage and monitor the Pet Care & Sitting System.</p>
            </div>
            <div class="col-md-4 text-md-end">
                <div class="btn-group">
                    <a href="users.php" class="btn btn-light">
                        <i class="fas fa-users me-1"></i> Manage Users
                    </a>
                    <a href="settings.php" class="btn btn-outline-light">
                        <i class="fas fa-cog me-1"></i> Settings
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Dashboard Content -->
<div class="container py-5">
    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3 mb-4">
            <div class="card text-center h-100">
                <div class="card-body">
                    <h1 class="display-4 text-primary mb-2"><?php echo $total_users; ?></h1>
                    <h5 class="card-title">Total Users</h5>
                    <p class="text-muted small mb-0">
                        <?php echo $users_stats['pet_owners_count']; ?> Pet Owners | 
                        <?php echo $users_stats['pet_sitters_count']; ?> Pet Sitters
                    </p>
                </div>
                <div class="card-footer bg-white border-0">
                    <a href="users.php" class="btn btn-sm btn-outline-primary">Manage Users</a>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-4">
            <div class="card text-center h-100">
                <div class="card-body">
                    <h1 class="display-4 text-success mb-2"><?php echo $bookings_stats['total']; ?></h1>
                    <h5 class="card-title">Total Bookings</h5>
                    <p class="text-muted small mb-0">
                        <?php echo $bookings_stats['pending']; ?> Pending | 
                        <?php echo $bookings_stats['confirmed']; ?> Confirmed | 
                        <?php echo $bookings_stats['completed']; ?> Completed
                    </p>
                </div>
                <div class="card-footer bg-white border-0">
                    <a href="bookings.php" class="btn btn-sm btn-outline-success">View Bookings</a>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-4">
            <div class="card text-center h-100">
                <div class="card-body">
                    <h1 class="display-4 text-info mb-2"><?php echo $orders_stats['total']; ?></h1>
                    <h5 class="card-title">Total Orders</h5>
                    <p class="text-muted small mb-0">
                        <?php echo $orders_stats['pending']; ?> Pending | 
                        <?php echo $orders_stats['processing'] + $orders_stats['shipped']; ?> Processing | 
                        <?php echo $orders_stats['delivered']; ?> Delivered
                    </p>
                </div>
                <div class="card-footer bg-white border-0">
                    <a href="orders.php" class="btn btn-sm btn-outline-info">View Orders</a>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-4">
            <div class="card text-center h-100">
                <div class="card-body">
                    <h1 class="display-4 text-danger mb-2">$<?php echo number_format($total_revenue, 0); ?></h1>
                    <h5 class="card-title">Total Revenue</h5>
                    <p class="text-muted small mb-0">
                        From <?php echo $bookings_stats['completed']; ?> bookings and 
                        <?php echo $orders_stats['delivered']; ?> orders
                    </p>
                </div>
                <div class="card-footer bg-white border-0">
                    <a href="revenue.php" class="btn btn-sm btn-outline-danger">Revenue Report</a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- More Statistics -->
    <div class="row mb-5">
        <div class="col-md-4 mb-4">
            <div class="card text-center h-100">
                <div class="card-body">
                    <h1 class="display-4 text-warning mb-2"><?php echo $pets_count; ?></h1>
                    <h5 class="card-title">Registered Pets</h5>
                </div>
                <div class="card-footer bg-white border-0">
                    <a href="pets.php" class="btn btn-sm btn-outline-warning">View Pets</a>
                </div>
            </div>
        </div>
        
        <div class="col-md-4 mb-4">
            <div class="card text-center h-100">
                <div class="card-body">
                    <h1 class="display-4 text-primary mb-2"><?php echo $products_count; ?></h1>
                    <h5 class="card-title">Products</h5>
                </div>
                <div class="card-footer bg-white border-0">
                    <a href="products.php" class="btn btn-sm btn-outline-primary">Manage Products</a>
                </div>
            </div>
        </div>
        
        <div class="col-md-4 mb-4">
            <div class="card text-center h-100">
                <div class="card-body">
                    <h1 class="display-4 text-success mb-2"><?php echo $services_count; ?></h1>
                    <h5 class="card-title">Services</h5>
                </div>
                <div class="card-footer bg-white border-0">
                    <a href="services.php" class="btn btn-sm btn-outline-success">View Services</a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Recent Bookings -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Recent Bookings</h5>
                    <a href="bookings.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($recent_bookings)): ?>
                        <div class="p-4">
                            <p class="text-muted text-center">No recent bookings found.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Pet Owner</th>
                                        <th>Pet Sitter</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_bookings as $booking): ?>
                                        <tr>
                                            <td>#<?php echo $booking['bookingID']; ?></td>
                                            <td><?php echo htmlspecialchars($booking['ownerName']); ?></td>
                                            <td><?php echo htmlspecialchars($booking['sitterName']); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($booking['created_at'])); ?></td>
                                            <td>
                                                <?php
                                                $status_class = '';
                                                switch ($booking['status']) {
                                                    case 'Pending':
                                                        $status_class = 'bg-warning';
                                                        break;
                                                    case 'Confirmed':
                                                        $status_class = 'bg-success';
                                                        break;
                                                    case 'Cancelled':
                                                        $status_class = 'bg-danger';
                                                        break;
                                                    case 'Completed':
                                                        $status_class = 'bg-info';
                                                        break;
                                                }
                                                ?>
                                                <span class="badge <?php echo $status_class; ?>"><?php echo $booking['status']; ?></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Recent Orders -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Recent Orders</h5>
                    <a href="orders.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($recent_orders)): ?>
                        <div class="p-4">
                            <p class="text-muted text-center">No recent orders found.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Customer</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_orders as $order): ?>
                                        <tr>
                                            <td>#<?php echo $order['orderID']; ?></td>
                                            <td><?php echo htmlspecialchars($order['customerName']); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($order['date'])); ?></td>
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
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row mb-4">
        <!-- Recent Users -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Recent Users</h5>
                    <a href="users.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($recent_users)): ?>
                        <div class="p-4">
                            <p class="text-muted text-center">No recent users found.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Type</th>
                                        <th>Joined</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_users as $user): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($user['fullName']); ?></td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td>
                                                <?php if ($user['user_type'] === 'pet_owner'): ?>
                                                    <span class="badge bg-primary">Pet Owner</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">Pet Sitter</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- System Status -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0">System Status</h5>
                </div>
                <div class="card-body">
                    <div class="mb-4">
                        <h6>Database Status</h6>
                        <div class="progress mb-2">
                            <div class="progress-bar bg-success" role="progressbar" style="width: 92%" aria-valuenow="92" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                        <div class="d-flex justify-content-between">
                            <small class="text-muted">Connections: 8/100</small>
                            <small class="text-success">Healthy</small>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <h6>Server Load</h6>
                        <div class="progress mb-2">
                            <div class="progress-bar bg-info" role="progressbar" style="width: 45%" aria-valuenow="45" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                        <div class="d-flex justify-content-between">
                            <small class="text-muted">CPU: 45%</small>
                            <small class="text-info">Normal</small>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <h6>Memory Usage</h6>
                        <div class="progress mb-2">
                            <div class="progress-bar bg-warning" role="progressbar" style="width: 78%" aria-valuenow="78" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                        <div class="d-flex justify-content-between">
                            <small class="text-muted">RAM: 78%</small>
                            <small class="text-warning">Moderate</small>
                        </div>
                    </div>
                    
                    <div>
                        <h6>Disk Usage</h6>
                        <div class="progress mb-2">
                            <div class="progress-bar bg-success" role="progressbar" style="width: 32%" aria-valuenow="32" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                        <div class="d-flex justify-content-between">
                            <small class="text-muted">Space: 32%</small>
                            <small class="text-success">Healthy</small>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-success"><i class="fas fa-circle me-1"></i> System Online</span>
                        <a href="system_logs.php" class="btn btn-sm btn-outline-secondary">View Logs</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Quick Actions</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3 mb-3 mb-md-0">
                    <a href="add_product.php" class="btn btn-outline-primary w-100 py-3">
                        <i class="fas fa-box me-2"></i> Add New Product
                    </a>
                </div>
                <div class="col-md-3 mb-3 mb-md-0">
                    <a href="order_management.php" class="btn btn-outline-success w-100 py-3">
                        <i class="fas fa-shopping-cart me-2"></i> Manage Orders
                    </a>
                </div>
                <div class="col-md-3 mb-3 mb-md-0">
                    <a href="pending_reviews.php" class="btn btn-outline-warning w-100 py-3">
                        <i class="fas fa-star me-2"></i> Review Management
                    </a>
                </div>
                <div class="col-md-3">
                    <a href="backup.php" class="btn btn-outline-danger w-100 py-3">
                        <i class="fas fa-database me-2"></i> Database Backup
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include_once '../includes/footer.php';

// Close database connection
$conn->close();
?>