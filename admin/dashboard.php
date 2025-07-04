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
                (SELECT COUNT(*) FROM pet_sitter WHERE approval_status = 'Approved') as pet_sitters_count,
                (SELECT COUNT(*) FROM pet_sitter WHERE approval_status = 'Pending') as pending_sitters_count";
$users_result = $conn->query($users_sql);
$users_stats = $users_result->fetch_assoc();
$total_users = $users_stats['pet_owners_count'] + $users_stats['pet_sitters_count'];
$pending_sitters_count = $users_stats['pending_sitters_count'];

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

// Total products and inventory stats
$products_sql = "SELECT 
                  COUNT(*) as total,
                  COUNT(CASE WHEN stock <= 10 THEN 1 END) as low_stock,
                  COUNT(CASE WHEN stock = 0 THEN 1 END) as out_of_stock,
                  SUM(stock) as total_stock,
                  AVG(price) as avg_price
                FROM pet_food_and_accessories";
$products_result = $conn->query($products_sql);
$products_stats = $products_result->fetch_assoc();

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
                        LEFT JOIN pet_owner po ON b.userID = po.userID 
                        LEFT JOIN pet_sitter ps ON b.sitterID = ps.userID 
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

// Low stock products
$low_stock_sql = "SELECT name, brand, stock FROM pet_food_and_accessories 
                  WHERE stock <= 10 ORDER BY stock ASC LIMIT 5";
$low_stock_result = $conn->query($low_stock_sql);
$low_stock_products = [];
while ($row = $low_stock_result->fetch_assoc()) {
    $low_stock_products[] = $row;
}

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
    line-height: 1.6;
}

.dashboard-actions {
    position: relative;
    z-index: 2;
}

.btn-glass {
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    color: white;
    padding: 12px 24px;
    border-radius: 50px;
    backdrop-filter: blur(10px);
    transition: all 0.3s ease;
    margin: 0 8px;
    text-decoration: none;
}

.btn-glass:hover {
    background: rgba(255, 255, 255, 0.2);
    color: white;
    transform: translateY(-2px);
}

.dashboard-content {
    padding: 80px 0;
    background: #f8f9ff;
}

/* Modern Statistics Cards */
.stat-card-modern {
    background: white;
    border-radius: 20px;
    padding: 32px 24px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(0, 0, 0, 0.05);
    transition: all 0.3s ease;
    text-align: center;
    position: relative;
    overflow: hidden;
}

.stat-card-modern::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #667eea, #764ba2);
}

.stat-card-modern:hover {
    transform: translateY(-8px);
    box-shadow: 0 30px 80px rgba(0, 0, 0, 0.12);
}

.stat-icon {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
    font-size: 2rem;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
}

.stat-number {
    font-size: 2.5rem;
    font-weight: 800;
    color: #1e293b;
    display: block;
    margin-bottom: 8px;
    line-height: 1;
}

.stat-label {
    color: #64748b;
    font-weight: 600;
    font-size: 1rem;
    margin-bottom: 12px;
}

.stat-details {
    font-size: 0.85rem;
    color: #9ca3af;
    line-height: 1.4;
}

.stat-trend {
    display: inline-flex;
    align-items: center;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
    margin-top: 8px;
}

.trend-up {
    background: #d1fae5;
    color: #065f46;
}

.trend-down {
    background: #fee2e2;
    color: #991b1b;
}

/* Quick Actions */
.quick-actions-section {
    margin-top: 60px;
}

.section-title {
    font-size: 2.5rem;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 16px;
}

.quick-action-modern {
    display: block;
    background: white;
    border-radius: 20px;
    padding: 32px 24px;
    text-decoration: none;
    color: inherit;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(0, 0, 0, 0.05);
    transition: all 0.3s ease;
    text-align: center;
    position: relative;
    overflow: hidden;
    height: 100%;
}

.quick-action-modern::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #667eea, #764ba2);
    transform: scaleX(0);
    transition: transform 0.3s ease;
}

.quick-action-modern:hover {
    transform: translateY(-8px);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.12);
    color: inherit;
    text-decoration: none;
}

.quick-action-modern:hover::before {
    transform: scaleX(1);
}

.action-icon-modern {
    width: 70px;
    height: 70px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
    font-size: 1.8rem;
    transition: all 0.3s ease;
}

.quick-action-modern:hover .action-icon-modern {
    transform: scale(1.1);
    box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
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

/* Activity Cards */
.activity-card-modern {
    background: white;
    border-radius: 20px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(0, 0, 0, 0.05);
    overflow: hidden;
    margin-bottom: 32px;
}

.activity-card-header {
    background: linear-gradient(135deg, #f8f9ff, #f1f5f9);
    padding: 24px 32px;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    display: flex;
    justify-content: space-between;
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
    justify-content: space-between;
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
    justify-content: space-between;
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
    justify-content: space-between;
    align-items: center;
}

.status-indicator {
    display: flex;
    align-items: center;
    font-weight: 600;
    color: #059669;
}

.status-dot {
    width: 12px;
    height: 12px;
    background: #10b981;
    border-radius: 50%;
    margin-right: 8px;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
    70% { box-shadow: 0 0 0 10px rgba(16, 185, 129, 0); }
    100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
}

.alert-modern {
    background: linear-gradient(135deg, #fef3c7, #fed7aa);
    border: none;
    border-radius: 16px;
    padding: 24px;
    margin-top: 40px;
    border-left: 4px solid #f59e0b;
}

.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: #6b7280;
}

.empty-state i {
    font-size: 3rem;
    margin-bottom: 16px;
    color: #d1d5db;
}

.low-stock-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid #f1f5f9;
}

.low-stock-item:last-child {
    border-bottom: none;
}

.stock-badge {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
}

.stock-critical { background: #fee2e2; color: #991b1b; }
.stock-low { background: #fef3c7; color: #92400e; }
.stock-out { background: #f3f4f6; color: #374151; }

@media (max-width: 768px) {
    .dashboard-title {
        font-size: 2rem;
    }
    
    .stat-card-modern {
        margin-bottom: 24px;
    }
    
    .activity-table th,
    .activity-table td {
        padding: 12px 16px;
    }
    
    .quick-action-modern {
        margin-bottom: 16px;
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
                    <a href="users.php" class="btn-glass">
                        <i class="fas fa-users me-2"></i>Manage Users
                    </a>
                    <a href="settings.php" class="btn-glass">
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
                    <?php if ($pending_sitters_count > 0): ?>
                        <div class="stat-trend trend-down">
                            <i class="fas fa-clock me-1"></i>
                            <?php echo $pending_sitters_count; ?> Pending Approval
                        </div>
                    <?php endif; ?>
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
                    <div class="stat-trend trend-up">
                        <i class="fas fa-arrow-up me-1"></i>
                        Active Bookings
                    </div>
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
                        <?php echo $orders_stats['processing']; ?> Processing<br>
                        <?php echo $orders_stats['delivered']; ?> Delivered
                    </div>
                    <div class="stat-trend trend-up">
                        <i class="fas fa-truck me-1"></i>
                        E-commerce Active
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6">
                <div class="stat-card-modern">
                    <div class="stat-icon">
                        <i class="fas fa-boxes"></i>
                    </div>
                    <span class="stat-number"><?php echo $products_stats['total']; ?></span>
                    <div class="stat-label">Products</div>
                    <div class="stat-details">
                        <?php echo number_format($products_stats['total_stock']); ?> Total Stock<br>
                        Rs. <?php echo number_format($products_stats['avg_price'], 0); ?> Avg. Price
                    </div>
                    <?php if ($products_stats['low_stock'] > 0): ?>
                        <div class="stat-trend trend-down">
                            <i class="fas fa-exclamation-triangle me-1"></i>
                            <?php echo $products_stats['low_stock']; ?> Low Stock
                        </div>
                    <?php else: ?>
                        <div class="stat-trend trend-up">
                            <i class="fas fa-check me-1"></i>
                            All Stocked
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Revenue and Pets Row -->
        <div class="row g-4 mb-5">
            <div class="col-xl-3 col-md-6">
                <div class="stat-card-modern">
                    <div class="stat-icon">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <span class="stat-number">Rs. <?php echo number_format($total_revenue, 0); ?></span>
                    <div class="stat-label">Total Revenue</div>
                    <div class="stat-details">
                        From completed transactions<br>
                        Bookings + Orders
                    </div>
                    <div class="stat-trend trend-up">
                        <i class="fas fa-chart-line me-1"></i>
                        Growing
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6">
                <div class="stat-card-modern">
                    <div class="stat-icon">
                        <i class="fas fa-paw"></i>
                    </div>
                    <span class="stat-number"><?php echo $pets_count; ?></span>
                    <div class="stat-label">Registered Pets</div>
                    <div class="stat-details">
                        Pet profiles in system<br>
                        Across all pet owners
                    </div>
                    <div class="stat-trend trend-up">
                        <i class="fas fa-heart me-1"></i>
                        Pet Community
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6">
                <div class="stat-card-modern">
                    <div class="stat-icon">
                        <i class="fas fa-concierge-bell"></i>
                    </div>
                    <span class="stat-number"><?php echo $services_count; ?></span>
                    <div class="stat-label">Pet Services</div>
                    <div class="stat-details">
                        Available services<br>
                        From approved sitters
                    </div>
                    <div class="stat-trend trend-up">
                        <i class="fas fa-star me-1"></i>
                        Service Network
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6">
                <div class="stat-card-modern">
                    <div class="stat-icon">
                        <i class="fas fa-chart-pie"></i>
                    </div>
                    <span class="stat-number"><?php echo round(($orders_stats['delivered'] / max($orders_stats['total'], 1)) * 100); ?>%</span>
                    <div class="stat-label">Order Success Rate</div>
                    <div class="stat-details">
                        Successful deliveries<br>
                        Customer satisfaction
                    </div>
                    <div class="stat-trend trend-up">
                        <i class="fas fa-thumbs-up me-1"></i>
                        Excellent
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="row g-4 mb-5">
            <!-- Recent Bookings -->
            <div class="col-lg-6">
                <div class="activity-card-modern">
                    <div class="activity-card-header">
                        <h5><i class="fas fa-calendar-alt me-2"></i>Recent Bookings</h5>
                        <a href="bookings.php" class="btn btn-outline-primary btn-sm">View All</a>
                    </div>
                    <div class="activity-card-body">
                        <?php if (empty($recent_bookings)): ?>
                            <div class="empty-state">
                                <i class="fas fa-calendar-times"></i>
                                <p>No recent bookings</p>
                            </div>
                        <?php else: ?>
                            <table class="table activity-table">
                                <thead>
                                    <tr>
                                        <th>Pet Owner</th>
                                        <th>Pet Sitter</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_bookings as $booking): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($booking['ownerName'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($booking['sitterName'] ?? 'N/A'); ?></td>
                                            <td><span class="status-badge status-<?php echo strtolower($booking['status']); ?>"><?php echo $booking['status']; ?></span></td>
                                            <td><?php echo date('M d, Y', strtotime($booking['checkInDate'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Recent Orders -->
            <div class="col-lg-6">
                <div class="activity-card-modern">
                    <div class="activity-card-header">
                        <h5><i class="fas fa-shopping-bag me-2"></i>Recent Orders</h5>
                        <a href="order_management.php" class="btn btn-outline-primary btn-sm">View All</a>
                    </div>
                    <div class="activity-card-body">
                        <?php if (empty($recent_orders)): ?>
                            <div class="empty-state">
                                <i class="fas fa-shopping-cart"></i>
                                <p>No recent orders</p>
                            </div>
                        <?php else: ?>
                            <table class="table activity-table">
                                <thead>
                                    <tr>
                                        <th>Customer</th>
                                        <th>Status</th>
                                        <th>Amount</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_orders as $order): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($order['customerName']); ?></td>
                                            <td><span class="status-badge status-<?php echo strtolower($order['status']); ?>"><?php echo $order['status']; ?></span></td>
                                            <td>Rs. <?php echo number_format($order['total'] ?? 0, 2); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($order['date'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Status and Low Stock -->
        <div class="row g-4 mb-5">
            <!-- System Status -->
            <div class="col-lg-8">
                <div class="system-status-card">
                    <div class="system-status-header">
                        <h5><i class="fas fa-server me-2"></i>System Status</h5>
                    </div>
                    <div class="system-status-body">
                        <div class="status-item">
                            <div class="status-label">
                                <span>Database Performance</span>
                                <span class="text-success">Excellent</span>
                            </div>
                            <div class="status-bar">
                                <div class="status-progress healthy" style="width: 95%;"></div>
                            </div>
                            <div class="status-details">
                                <span class="text-muted">Query Response Time</span>
                                <span class="text-success">< 50ms</span>
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
                                <span>Storage Usage</span>
                                <span class="text-success">Healthy</span>
                            </div>
                            <div class="status-bar">
                                <div class="status-progress healthy" style="width: 32%;"></div>
                            </div>
                            <div class="status-details">
                                <span class="text-muted">Disk Space</span>
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

            <!-- Low Stock Alerts -->
            <div class="col-lg-4">
                <div class="activity-card-modern">
                    <div class="activity-card-header">
                        <h5><i class="fas fa-exclamation-triangle me-2 text-warning"></i>Low Stock Alerts</h5>
                        <a href="manage_products.php?stock_status=low_stock" class="btn btn-outline-warning btn-sm">View All</a>
                    </div>
                    <div class="activity-card-body" style="padding: 24px;">
                        <?php if (empty($low_stock_products)): ?>
                            <div class="empty-state">
                                <i class="fas fa-check-circle text-success"></i>
                                <p>All products well stocked!</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($low_stock_products as $product): ?>
                                <div class="low-stock-item">
                                    <div>
                                        <strong><?php echo htmlspecialchars($product['name']); ?></strong><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($product['brand']); ?></small>
                                    </div>
                                    <div>
                                        <span class="stock-badge <?php echo $product['stock'] == 0 ? 'stock-out' : ($product['stock'] <= 5 ? 'stock-critical' : 'stock-low'); ?>">
                                            <?php echo $product['stock']; ?> left
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
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
                <!-- Product Management -->
                <div class="col-lg-3 col-md-6">
                    <a href="manage_products.php" class="quick-action-modern">
                        <div class="action-icon-modern">
                            <i class="fas fa-boxes"></i>
                        </div>
                        <div class="action-title">Manage Products</div>
                        <div class="action-description">View, add, edit and manage inventory</div>
                    </a>
                </div>

                <!-- Add Product -->
                <div class="col-lg-3 col-md-6">
                    <a href="add_product.php" class="quick-action-modern">
                        <div class="action-icon-modern">
                            <i class="fas fa-plus"></i>
                        </div>
                        <div class="action-title">Add Product</div>
                        <div class="action-description">Quickly add new products to inventory</div>
                    </a>
                </div>
                
                <!-- Order Management -->
                <div class="col-lg-3 col-md-6">
                    <a href="order_management.php" class="quick-action-modern">
                        <div class="action-icon-modern">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <div class="action-title">Manage Orders</div>
                        <div class="action-description">Process and track customer orders</div>
                    </a>
                </div>

                <!-- User Management -->
                <div class="col-lg-3 col-md-6">
                    <a href="users.php" class="quick-action-modern">
                        <div class="action-icon-modern">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="action-title">Manage Users</div>
                        <div class="action-description">Manage pet owners and sitters</div>
                    </a>
                </div>

                <!-- Booking Management -->
                <div class="col-lg-3 col-md-6">
                    <a href="bookings.php" class="quick-action-modern">
                        <div class="action-icon-modern">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div class="action-title">Manage Bookings</div>
                        <div class="action-description">Oversee pet sitting bookings</div>
                    </a>
                </div>

                <!-- Pet Sitter Approval -->
                <div class="col-lg-3 col-md-6">
                    <a href="pet_sitter_approval.php" class="quick-action-modern">
                        <div class="action-icon-modern">
                            <i class="fas fa-user-check"></i>
                        </div>
                        <div class="action-title">Approve Sitters</div>
                        <div class="action-description">Review and approve pet sitters</div>
                    </a>
                </div>
                
                <!-- Review Management -->
                <div class="col-lg-3 col-md-6">
                    <a href="pending_reviews.php" class="quick-action-modern">
                        <div class="action-icon-modern">
                            <i class="fas fa-star"></i>
                        </div>
                        <div class="action-title">Review Management</div>
                        <div class="action-description">Moderate user reviews and ratings</div>
                    </a>
                </div>
                
                <!-- System Backup -->
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
        
        <!-- Pet Sitter Approval Alert -->
        <?php if ($pending_sitters_count > 0): ?>
            <div class="alert-modern">
                <div class="d-flex align-items-center">
                    <div class="me-3">
                        <i class="fas fa-exclamation-triangle" style="font-size: 2rem; color: #f59e0b;"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h5 style="color: #92400e; margin-bottom: 8px;">Pending Pet Sitter Applications</h5>
                        <p style="color: #92400e; margin-bottom: 16px;">
                            You have <strong><?php echo $pending_sitters_count; ?></strong> pet sitter application<?php echo $pending_sitters_count !== 1 ? 's' : ''; ?> waiting for approval.
                            Review and approve qualified pet sitters to expand your service network.
                        </p>
                        <a href="pet_sitter_approval.php" class="btn btn-warning">
                            <i class="fas fa-user-check me-2"></i>Review Applications
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php include_once '../includes/footer.php'; ?>

<script>
// Auto-hide alerts after 8 seconds
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert-modern');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            alert.style.opacity = '0.8';
        }, 8000);
    });
    
    // Add smooth scroll to quick actions
    const quickActions = document.querySelectorAll('.quick-action-modern');
    quickActions.forEach(action => {
        action.addEventListener('click', function(e) {
            // Add a subtle click effect
            this.style.transform = 'translateY(-4px) scale(0.98)';
            setTimeout(() => {
                this.style.transform = '';
            }, 150);
        });
    });
    
    // Dynamic stats animation on page load
    const statNumbers = document.querySelectorAll('.stat-number');
    statNumbers.forEach(stat => {
        const finalValue = parseInt(stat.textContent.replace(/[^\d]/g, ''));
        if (finalValue > 0) {
            let currentValue = 0;
            const increment = Math.ceil(finalValue / 30);
            const timer = setInterval(() => {
                currentValue += increment;
                if (currentValue >= finalValue) {
                    currentValue = finalValue;
                    clearInterval(timer);
                }
                
                // Preserve currency and formatting
                if (stat.textContent.includes('Rs.')) {
                    stat.textContent = 'Rs. ' + currentValue.toLocaleString();
                } else if (stat.textContent.includes('%')) {
                    stat.textContent = currentValue + '%';
                } else {
                    stat.textContent = currentValue.toLocaleString();
                }
            }, 50);
        }
    });
});
</script>