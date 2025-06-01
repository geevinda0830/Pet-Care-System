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

// Get pending pet sitter count for notification badge
$pending_sitters_sql = "SELECT COUNT(*) as count FROM pet_sitter WHERE approval_status = 'Pending'";
$pending_sitters_result = $conn->query($pending_sitters_sql);
$pending_sitters_count = $pending_sitters_result->fetch_assoc()['count'];

// Include header
include_once '../includes/header.php';
?>

<!-- Modern Admin Dashboard Styles -->
<style>
.admin-dashboard-section {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    position: relative;
    overflow: hidden;
    padding: 80px 0 60px;
}

.dashboard-particles {
    position: absolute;
    width: 100%;
    height: 100%;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="20" cy="20" r="2" fill="rgba(255,255,255,0.1)"/><circle cx="80" cy="40" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="40" cy="80" r="1.5" fill="rgba(255,255,255,0.1)"/></svg>');
    animation: float 20s infinite linear;
}

@keyframes float {
    0% { transform: translateY(0px) rotate(0deg); }
    100% { transform: translateY(-100px) rotate(360deg); }
}

.dashboard-header-content {
    position: relative;
    z-index: 2;
}

.dashboard-badge {
    display: inline-flex;
    align-items: center;
    background: rgba(255, 255, 255, 0.2);
    padding: 8px 16px;
    border-radius: 50px;
    margin-bottom: 16px;
    backdrop-filter: blur(10px);
    font-size: 0.9rem;
    font-weight: 600;
}

.dashboard-title {
    font-size: 3rem;
    font-weight: 800;
    line-height: 1.2;
    margin-bottom: 16px;
}

.dashboard-subtitle {
    font-size: 1.2rem;
    opacity: 0.9;
    margin-bottom: 0;
}

.dashboard-actions {
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
}

.btn-glass {
    background: rgba(255, 255, 255, 0.2);
    border: 1px solid rgba(255, 255, 255, 0.3);
    color: white;
    padding: 12px 24px;
    border-radius: 50px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
    backdrop-filter: blur(10px);
}

.btn-glass:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: translateY(-2px);
    color: white;
}

.dashboard-content {
    padding: 80px 0;
    background: #f8f9ff;
    margin-top: -40px;
    position: relative;
    z-index: 3;
}

.stat-card-modern {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    border-radius: 24px;
    padding: 32px;
    text-align: center;
    height: 100%;
    transition: all 0.3s ease;
    border: 1px solid rgba(255, 255, 255, 0.2);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
    position: relative;
    overflow: hidden;
}

.stat-card-modern::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 4px;
    background: linear-gradient(90deg, #667eea, #764ba2);
}

.stat-card-modern:hover {
    transform: translateY(-8px);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
}

.stat-icon {
    width: 80px;
    height: 80px;
    margin: 0 auto 24px;
    background: linear-gradient(135deg, #f1f5f9, #e2e8f0);
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    color: #667eea;
}

.stat-number {
    font-size: 2.5rem;
    font-weight: 800;
    color: #1e293b;
    margin-bottom: 8px;
    display: block;
}

.stat-label {
    font-size: 1.1rem;
    font-weight: 600;
    color: #64748b;
    margin-bottom: 12px;
}

.stat-details {
    font-size: 0.9rem;
    color: #9ca3af;
    line-height: 1.4;
}

.stat-card-modern .btn {
    margin-top: 16px;
    border-radius: 50px;
    padding: 8px 20px;
    font-size: 0.85rem;
    font-weight: 600;
}

.activity-card {
    background: white;
    border-radius: 20px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(0, 0, 0, 0.05);
    overflow: hidden;
    height: 100%;
}

.activity-card-header {
    background: linear-gradient(135deg, #f8f9ff, #f1f5f9);
    padding: 24px 32px;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    display: flex;
    justify-content: between;
    align-items: center;
}

.activity-card-header h5 {
    margin: 0;
    font-size: 1.3rem;
    font-weight: 700;
    color: #1e293b;
}

.activity-card-header .btn {
    border-radius: 50px;
    padding: 6px 16px;
    font-size: 0.8rem;
    font-weight: 600;
}

.activity-card-body {
    padding: 0;
}

.activity-table {
    margin: 0;
}

.activity-table th {
    background: #f8f9ff;
    border: none;
    color: #64748b;
    font-weight: 600;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 16px 24px;
}

.activity-table td {
    border: none;
    padding: 16px 24px;
    vertical-align: middle;
    border-bottom: 1px solid #f1f5f9;
}

.activity-table tr:hover {
    background: #f8f9ff;
}

.status-badge {
    padding: 6px 12px;
    border-radius: 50px;
    font-size: 0.75rem;
    font-weight: 600;
}

.status-pending { background: #fef3c7; color: #92400e; }
.status-confirmed { background: #d1fae5; color: #065f46; }
.status-completed { background: #dbeafe; color: #1e40af; }
.status-cancelled { background: #fee2e2; color: #991b1b; }
.status-processing { background: #e0e7ff; color: #3730a3; }
.status-shipped { background: #f0f9ff; color: #0c4a6e; }
.status-delivered { background: #d1fae5; color: #065f46; }

.system-status-card {
    background: white;
    border-radius: 20px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(0, 0, 0, 0.05);
    overflow: hidden;
}

.system-status-header {
    background: linear-gradient(135deg, #f8f9ff, #f1f5f9);
    padding: 24px 32px;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
}

.system-status-body {
    padding: 32px;
}

.status-item {
    margin-bottom: 32px;
}

.status-item:last-child {
    margin-bottom: 0;
}

.status-label {
    font-weight: 600;
    color: #374151;
    margin-bottom: 8px;
    display: flex;
    justify-content: between;
    align-items: center;
}

.status-bar {
    height: 8px;
    background: #f1f5f9;
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 8px;
}

.status-progress {
    height: 100%;
    border-radius: 4px;
    transition: width 0.3s ease;
}

.status-progress.healthy { background: linear-gradient(90deg, #10b981, #059669); }
.status-progress.warning { background: linear-gradient(90deg, #f59e0b, #d97706); }
.status-progress.normal { background: linear-gradient(90deg, #3b82f6, #2563eb); }

.status-details {
    display: flex;
    justify-content: between;
    align-items: center;
    font-size: 0.85rem;
}

.status-details .text-muted {
    color: #9ca3af;
}

.system-status-footer {
    background: linear-gradient(135deg, #f8f9ff, #f1f5f9);
    padding: 20px 32px;
    border-top: 1px solid rgba(0, 0, 0, 0.05);
}

.system-online {
    display: flex;
    justify-content: between;
    align-items: center;
}

.system-online .status-indicator {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #10b981;
    font-weight: 600;
}

.status-dot {
    width: 8px;
    height: 8px;
    background: #10b981;
    border-radius: 50%;
    animation: pulse 2s infinite;
}

.quick-actions-section {
    margin-top: 60px;
}

.quick-action-modern {
    background: white;
    border-radius: 20px;
    padding: 32px 24px;
    text-align: center;
    height: 100%;
    transition: all 0.3s ease;
    border: 1px solid rgba(0, 0, 0, 0.05);
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    text-decoration: none;
    color: inherit;
    position: relative;
    overflow: hidden;
}

.quick-action-modern::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 4px;
    background: linear-gradient(90deg, #667eea, #764ba2);
}

.quick-action-modern:hover {
    transform: translateY(-8px);
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
    text-decoration: none;
    color: inherit;
}

.action-icon-modern {
    width: 80px;
    height: 80px;
    margin: 0 auto 20px;
    background: linear-gradient(135f, #f1f5f9, #e2e8f0);
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    color: #667eea;
}

.action-title {
    font-size: 1.2rem;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 8px;
}

.action-description {
    color: #64748b;
    font-size: 0.9rem;
    line-height: 1.4;
}

.empty-state {
    text-align: center;
    padding: 40px;
    color: #9ca3af;
}

.empty-state i {
    font-size: 3rem;
    margin-bottom: 16px;
}

@media (max-width: 991px) {
    .dashboard-title {
        font-size: 2.5rem;
    }
    
    .dashboard-actions {
        justify-content: center;
        margin-top: 20px;
    }
    
    .activity-card-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 16px;
    }
    
    .activity-table th,
    .activity-table td {
        padding: 12px 16px;
    }
}

@media (max-width: 768px) {
    .activity-table {
        font-size: 0.85rem;
    }
    
    .quick-action-modern {
        padding: 24px 16px;
    }
    
    .system-status-body {
        padding: 24px;
    }
}
</style>

<!-- Modern Admin Dashboard Header -->
<section class="admin-dashboard-section">
    <div class="dashboard-particles"></div>
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <div class="dashboard-header-content">
                    <div class="dashboard-badge">
                        <i class="fas fa-shield-alt me-2"></i>
                        Administrator Dashboard
                    </div>
                    <h1 class="dashboard-title">Welcome Back, Admin!</h1>
                    <p class="dashboard-subtitle">Monitor and manage the Pet Care & Sitting System with comprehensive insights and controls.</p>
                </div>
            </div>
            <div class="col-lg-4 text-lg-end">
                <div class="dashboard-actions">
                    <a href="users.php" class="btn btn-glass">
                        <i class="fas fa-users me-2"></i>Manage Users
                    </a>
                    <a href="settings.php" class="btn btn-glass">
                        <i class="fas fa-cog me-2"></i>Settings
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Modern Dashboard Content -->
<section class="dashboard-content">
    <div class="container">
        <!-- Statistics Cards -->
        <div class="row g-4 mb-5">
            <div class="col-xl-3 col-md-6">
                <div class="stat-card-modern">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <span class="stat-number"><?php echo $total_users; ?></span>
                    <div class="stat-label">Total Users</div>
                    <div class="stat-details">
                        <?php echo $users_stats['pet_owners_count']; ?> Pet Owners<br>
                        <?php echo $users_stats['pet_sitters_count']; ?> Pet Sitters
                    </div>
                    <a href="users.php" class="btn btn-outline-primary btn-sm">Manage Users</a>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6">
                <div class="stat-card-modern">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <span class="stat-number"><?php echo $bookings_stats['total']; ?></span>
                    <div class="stat-label">Total Bookings</div>
                    <div class="stat-details">
                        <?php echo $bookings_stats['pending']; ?> Pending<br>
                        <?php echo $bookings_stats['confirmed']; ?> Confirmed<br>
                        <?php echo $bookings_stats['completed']; ?> Completed
                    </div>
                    <a href="bookings.php" class="btn btn-outline-success btn-sm">View Bookings</a>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6">
                <div class="stat-card-modern">
                    <div class="stat-icon">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <span class="stat-number"><?php echo $orders_stats['total']; ?></span>
                    <div class="stat-label">Total Orders</div>
                    <div class="stat-details">
                        <?php echo $orders_stats['pending']; ?> Pending<br>
                        <?php echo $orders_stats['processing'] + $orders_stats['shipped']; ?> Processing<br>
                        <?php echo $orders_stats['delivered']; ?> Delivered
                    </div>
                    <a href="orders.php" class="btn btn-outline-info btn-sm">View Orders</a>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6">
                <div class="stat-card-modern">
                    <div class="stat-icon">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <span class="stat-number">$<?php echo number_format($total_revenue, 0); ?></span>
                    <div class="stat-label">Total Revenue</div>
                    <div class="stat-details">
                        From <?php echo $bookings_stats['completed']; ?> bookings<br>
                        and <?php echo $orders_stats['delivered']; ?> orders
                    </div>
                    <a href="revenue.php" class="btn btn-outline-danger btn-sm">Revenue Report</a>
                </div>
            </div>
        </div>
        
        <!-- Additional Statistics Row -->
        <div class="row g-4 mb-5">
            <div class="col-lg-4">
                <div class="stat-card-modern">
                    <div class="stat-icon">
                        <i class="fas fa-paw"></i>
                    </div>
                    <span class="stat-number"><?php echo $pets_count; ?></span>
                    <div class="stat-label">Registered Pets</div>
                    <a href="pets.php" class="btn btn-outline-warning btn-sm">View Pets</a>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="stat-card-modern">
                    <div class="stat-icon">
                        <i class="fas fa-box"></i>
                    </div>
                    <span class="stat-number"><?php echo $products_count; ?></span>
                    <div class="stat-label">Products</div>
                    <a href="products.php" class="btn btn-outline-primary btn-sm">Manage Products</a>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="stat-card-modern">
                    <div class="stat-icon">
                        <i class="fas fa-concierge-bell"></i>
                    </div>
                    <span class="stat-number"><?php echo $services_count; ?></span>
                    <div class="stat-label">Services</div>
                    <a href="services.php" class="btn btn-outline-success btn-sm">View Services</a>
                </div>
            </div>
        </div>
        
        <div class="row g-4">
            <!-- Recent Bookings -->
            <div class="col-lg-6 mb-4">
                <div class="activity-card">
                    <div class="activity-card-header">
                        <h5>Recent Bookings</h5>
                        <a href="bookings.php" class="btn btn-primary btn-sm">View All</a>
                    </div>
                    <div class="activity-card-body">
                        <?php if (empty($recent_bookings)): ?>
                            <div class="empty-state">
                                <i class="fas fa-calendar-times"></i>
                                <p>No recent bookings found.</p>
                            </div>
                        <?php else: ?>
                            <table class="table activity-table">
                                <thead>
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
                                            <td><strong>#<?php echo $booking['bookingID']; ?></strong></td>
                                            <td><?php echo htmlspecialchars($booking['ownerName']); ?></td>
                                            <td><?php echo htmlspecialchars($booking['sitterName']); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($booking['created_at'])); ?></td>
                                            <td>
                                                <span class="status-badge status-<?php echo strtolower($booking['status']); ?>">
                                                    <?php echo $booking['status']; ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Recent Orders -->
            <div class="col-lg-6 mb-4">
                <div class="activity-card">
                    <div class="activity-card-header">
                        <h5>Recent Orders</h5>
                        <a href="orders.php" class="btn btn-primary btn-sm">View All</a>
                    </div>
                    <div class="activity-card-body">
                        <?php if (empty($recent_orders)): ?>
                            <div class="empty-state">
                                <i class="fas fa-shopping-cart"></i>
                                <p>No recent orders found.</p>
                            </div>
                        <?php else: ?>
                            <table class="table activity-table">
                                <thead>
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
                                            <td><strong>#<?php echo $order['orderID']; ?></strong></td>
                                            <td><?php echo htmlspecialchars($order['customerName']); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($order['date'])); ?></td>
                                            <td>
                                                <span class="status-badge status-<?php echo strtolower($order['status']); ?>">
                                                    <?php echo $order['status']; ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row g-4 mb-5">
            <!-- Recent Users -->
            <div class="col-lg-6">
                <div class="activity-card">
                    <div class="activity-card-header">
                        <h5>Recent Users</h5>
                        <a href="users.php" class="btn btn-primary btn-sm">View All</a>
                    </div>
                    <div class="activity-card-body">
                        <?php if (empty($recent_users)): ?>
                            <div class="empty-state">
                                <i class="fas fa-users"></i>
                                <p>No recent users found.</p>
                            </div>
                        <?php else: ?>
                            <table class="table activity-table">
                                <thead>
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
                                                    <span class="status-badge" style="background: #dbeafe; color: #1e40af;">Pet Owner</span>
                                                <?php else: ?>
                                                    <span class="status-badge" style="background: #d1fae5; color: #065f46;">Pet Sitter</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- System Status -->
            <div class="col-lg-6">
                <div class="system-status-card">
                    <div class="system-status-header">
                        <h5>System Status</h5>
                    </div>
                    <div class="system-status-body">
                        <div class="status-item">
                            <div class="status-label">
                                <span>Database Health</span>
                                <span class="text-success">Healthy</span>
                            </div>
                            <div class="status-bar">
                                <div class="status-progress healthy" style="width: 92%;"></div>
                            </div>
                            <div class="status-details">
                                <span class="text-muted">Connections: 8/100</span>
                                <span class="text-success">92%</span>
                            </div>
                        </div>
                        
                        <div class="status-item">
                            <div class="status-label">
                                <span>Server Load</span>
                                <span style="color: #3b82f6;">Normal</span>
                            </div>
                            <div class="status-bar">
                                <div class="status-progress normal" style="width: 45%;"></div>
                            </div>
                            <div class="status-details">
                                <span class="text-muted">CPU Usage</span>
                                <span style="color: #3b82f6;">45%</span>
                            </div>
                        </div>
                        
                        <div class="status-item">
                            <div class="status-label">
                                <span>Memory Usage</span>
                                <span style="color: #f59e0b;">Moderate</span>
                            </div>
                            <div class="status-bar">
                                <div class="status-progress warning" style="width: 78%;"></div>
                            </div>
                            <div class="status-details">
                                <span class="text-muted">RAM Usage</span>
                                <span style="color: #f59e0b;">78%</span>
                            </div>
                        </div>
                        
                        <div class="status-item">
                            <div class="status-label">
                                <span>Disk Usage</span>
                                <span class="text-success">Healthy</span>
                            </div>
                            <div class="status-bar">
                                <div class="status-progress healthy" style="width: 32%;"></div>
                            </div>
                            <div class="status-details">
                                <span class="text-muted">Storage Space</span>
                                <span class="text-success">32%</span>
                            </div>
                        </div>
                    </div>
                    <div class="system-status-footer">
                        <div class="system-online">
                            <div class="status-indicator">
                                <div class="status-dot"></div>
                                System Online
                            </div>
                            <a href="system_logs.php" class="btn btn-outline-secondary btn-sm">View Logs</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="quick-actions-section">
            <div class="text-center mb-5">
                <h3 class="section-title">Quick Actions</h3>
                <p class="text-muted">Manage your system efficiently with these shortcuts</p>
            </div>
            
            <div class="row g-4">
                <div class="col-lg-3 col-md-6">
                    <a href="add_product.php" class="quick-action-modern">
                        <div class="action-icon-modern">
                            <i class="fas fa-box"></i>
                        </div>
                        <div class="action-title">Add New Product</div>
                        <div class="action-description">Add products to the pet shop inventory</div>
                    </a>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <a href="order_management.php" class="quick-action-modern">
                        <div class="action-icon-modern">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <div class="action-title">Manage Orders</div>
                        <div class="action-description">Process and track customer orders</div>
                    </a>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <a href="pending_reviews.php" class="quick-action-modern">
                        <div class="action-icon-modern">
                            <i class="fas fa-star"></i>
                        </div>
                        <div class="action-title">Review Management</div>
                        <div class="action-description">Moderate user reviews and ratings</div>
                    </a>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <a href="backup.php" class="quick-action-modern">
                        <div class="action-icon-modern">
                            <i class="fas fa-database"></i>
                        </div>
                        <div class="action-title">Database Backup</div>
                        <div class="action-description">Create and manage system backups</div>
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Pet Sitter Approval Guide -->
        <?php if ($pending_sitters_count > 0): ?>
            <div class="alert" style="background: linear-gradient(135deg, #fef3c7, #fed7aa); border: none; border-radius: 16px; padding: 24px; margin-top: 40px;">
                <div class="d-flex align-items-center">
                    <div class="me-3">
                        <i class="fas fa-exclamation-triangle" style="font-size: 2rem; color: #f59e0b;"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h5 style="color: #92400e; margin-bottom: 8px;">Pending Pet Sitter Applications</h5>
                        <p style="color: #92400e; margin-bottom: 16px;">
                            You have <strong><?php echo $pending_sitters_count; ?></strong> pet sitter application<?php echo $pending_sitters_count !== 1 ? 's' : ''; ?> waiting for approval.
                        </p>
                        <a href="users.php?user_type=pet_sitter&approval_status=Pending" class="btn" style="background: #f59e0b; color: white; border: none; border-radius: 50px; padding: 8px 20px; font-weight: 600;">
                            Review Applications
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php
// Include footer
include_once '../includes/footer.php';

// Close database connection
$conn->close();
?>