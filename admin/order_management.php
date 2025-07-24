<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Check if user is logged in as admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    $_SESSION['error_message'] = "You must be logged in as an administrator to access this page.";
    header("Location: ../login.php");
    exit();
}

// Database configuration - UPDATE THESE VALUES
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "pet_care_db";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fix status column size if needed
$fix_column_sql = "ALTER TABLE `order` MODIFY COLUMN status VARCHAR(20) NOT NULL DEFAULT 'Pending'";
$conn->query($fix_column_sql);

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
                    $_SESSION['error_message'] = "No changes made to Order #$order_id or order not found.";
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
    header("Location: " . $_SERVER['PHP_SELF']);
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

// Get orders with customer details and item counts
$orders_sql = "SELECT o.*, 
                      po.fullName as customerName, 
                      po.email as customerEmail,
                      po.contact as customerPhone,
                      po.address as customerAddress,
                      COALESCE(COUNT(ci.id), 0) as item_count,
                      COALESCE(SUM(ci.quantity * ci.price), 0) as total_amount
               FROM `order` o 
               JOIN pet_owner po ON o.userID = po.userID
               LEFT JOIN cart c ON o.orderID = c.orderID
               LEFT JOIN cart_items ci ON c.cartID = ci.cartID
               WHERE $where_clause
               GROUP BY o.orderID
               ORDER BY o.orderID DESC";

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

// Get statistics
$stats_sql = "SELECT 
    COUNT(*) as total_orders,
    SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending_orders,
    SUM(CASE WHEN status = 'Processing' THEN 1 ELSE 0 END) as processing_orders,
    SUM(CASE WHEN status = 'Shipped' THEN 1 ELSE 0 END) as shipped_orders,
    SUM(CASE WHEN status = 'Delivered' THEN 1 ELSE 0 END) as delivered_orders,
    COALESCE(SUM(
        CASE WHEN status IN ('Delivered', 'Shipped') THEN 
            (SELECT SUM(ci.quantity * ci.price) 
             FROM cart c 
             JOIN cart_items ci ON c.cartID = ci.cartID 
             WHERE c.orderID = o.orderID)
        ELSE 0 END
    ), 0) as total_revenue
FROM `order` o";

$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();
$stats_stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Management - Pet Care System</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #f8f9ff;
            color: #1e293b;
            line-height: 1.6;
        }

        /* Header Section */
        .dashboard-header-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 80px 0 40px;
            position: relative;
            overflow: hidden;
        }

        .dashboard-header-section::before {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="20" cy="20" r="2" fill="rgba(255,255,255,0.1)"/><circle cx="80" cy="40" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="40" cy="80" r="1.5" fill="rgba(255,255,255,0.1)"/></svg>');
            animation: float 20s infinite linear;
        }

        @keyframes float {
            from { transform: translateX(-100px); }
            to { transform: translateX(100px); }
        }

        .welcome-badge {
            display: inline-block;
            background: rgba(255, 255, 255, 0.2);
            padding: 8px 16px;
            border-radius: 50px;
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 16px;
            backdrop-filter: blur(10px);
        }

        .dashboard-title {
            font-size: 3rem;
            font-weight: 800;
            margin-bottom: 16px;
            line-height: 1.2;
        }

        .dashboard-subtitle {
            font-size: 1.2rem;
            opacity: 0.9;
            margin-bottom: 0;
        }

        /* Statistics Cards */
        .dashboard-stats-section {
            padding: 0;
            margin-top: -40px;
            position: relative;
            z-index: 2;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 24px;
            margin-bottom: 60px;
        }

        .stat-card-modern {
            background: white;
            border-radius: 20px;
            padding: 32px 24px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(0, 0, 0, 0.05);
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .stat-card-modern:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }

        .stat-card-modern::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
        }

        .stat-card-modern.primary::before { background: linear-gradient(90deg, #667eea, #764ba2); }
        .stat-card-modern.success::before { background: linear-gradient(90deg, #10b981, #059669); }
        .stat-card-modern.warning::before { background: linear-gradient(90deg, #f59e0b, #d97706); }
        .stat-card-modern.info::before { background: linear-gradient(90deg, #06b6d4, #0891b2); }
        .stat-card-modern.purple::before { background: linear-gradient(90deg, #8b5cf6, #7c3aed); }
        .stat-card-modern.rose::before { background: linear-gradient(90deg, #f43f5e, #e11d48); }

        .stat-icon-modern {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 20px;
            background: linear-gradient(135deg, #f1f5f9, #e2e8f0);
            color: #667eea;
        }

        .stat-number-modern {
            font-size: 2.5rem;
            font-weight: 800;
            color: #1e293b;
            margin-bottom: 8px;
        }

        .stat-label-modern {
            color: #64748b;
            font-weight: 500;
            margin-bottom: 0;
        }

        /* Content Section */
        .dashboard-content-section {
            padding: 80px 0;
            background: white;
        }

        .dashboard-widget {
            background: white;
            border-radius: 20px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(0, 0, 0, 0.05);
            overflow: hidden;
            margin-bottom: 30px;
        }

        .widget-header {
            padding: 32px 32px 20px;
            background: #f8f9ff;
            border-bottom: 1px solid #f1f5f9;
        }

        .widget-title {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 0;
            font-size: 1.5rem;
            font-weight: 700;
            color: #1e293b;
        }

        .widget-icon {
            color: #667eea;
        }

        /* Filters */
        .filters-container {
            padding: 32px;
            background: #f8f9ff;
            margin-bottom: 30px;
            border-radius: 20px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        .filter-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            align-items: end;
        }

        .form-group {
            margin-bottom: 0;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #374151;
        }

        .form-control-modern {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            background: white;
        }

        .form-control-modern:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .btn-modern {
            padding: 12px 24px;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-primary-modern {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }

        .btn-primary-modern:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        .btn-secondary-modern {
            background: #f1f5f9;
            color: #64748b;
            border: 1px solid #e2e8f0;
        }

        .btn-secondary-modern:hover {
            background: #e2e8f0;
            color: #475569;
        }

        /* Orders Table */
        .orders-container {
            padding: 0;
        }

        .orders-table-modern {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }

        .orders-table-modern th {
            background: #f8f9ff;
            padding: 20px;
            text-align: left;
            font-weight: 700;
            color: #374151;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #f1f5f9;
        }

        .orders-table-modern td {
            padding: 24px 20px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }

        .orders-table-modern tbody tr {
            transition: all 0.3s ease;
        }

        .orders-table-modern tbody tr:hover {
            background: #f8f9ff;
            transform: scale(1.001);
        }

        /* Customer Info */
        .customer-info-modern {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .customer-avatar-modern {
            width: 50px;
            height: 50px;
            border-radius: 15px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.1rem;
        }

        .customer-details-modern h6 {
            margin: 0;
            font-weight: 600;
            color: #1e293b;
            font-size: 1rem;
        }

        .customer-details-modern small {
            color: #64748b;
            font-size: 0.85rem;
        }

        /* Status Forms */
        .status-form-modern {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .status-select-modern {
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            padding: 10px 14px;
            font-size: 0.9rem;
            min-width: 140px;
            background: white;
            transition: all 0.3s ease;
        }

        .status-select-modern:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .btn-update-modern {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            border: none;
            padding: 10px 14px;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: none;
            font-size: 0.9rem;
        }

        .btn-update-modern:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(16, 185, 129, 0.3);
        }

        .btn-update-modern.show {
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        /* Status Badges */
        .status-badge-modern {
            padding: 8px 16px;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-pending { background: #fef3c7; color: #92400e; }
        .status-processing { background: #dbeafe; color: #1e40af; }
        .status-shipped { background: #e0e7ff; color: #5b21b6; }
        .status-delivered { background: #d1fae5; color: #065f46; }
        .status-cancelled { background: #fee2e2; color: #991b1b; }

        /* Action Buttons */
        .btn-view-modern {
            background: #e0e7ff;
            color: #5b21b6;
            border: none;
            padding: 10px 16px;
            border-radius: 12px;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-view-modern:hover {
            background: #c7d2fe;
            color: #4c1d95;
            text-decoration: none;
            transform: translateY(-2px);
        }

        /* Alerts */
        .alert-modern {
            border-radius: 16px;
            padding: 20px 24px;
            margin-bottom: 24px;
            border: none;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .alert-success-modern {
            background: linear-gradient(135deg, #d1fae5, #a7f3d0);
            color: #065f46;
        }

        .alert-danger-modern {
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            color: #991b1b;
        }

        /* Empty State */
        .empty-state-modern {
            text-align: center;
            padding: 80px 40px;
            color: #64748b;
        }

        .empty-icon-modern {
            font-size: 4rem;
            margin-bottom: 24px;
            color: #cbd5e1;
        }

        .empty-state-modern h4 {
            color: #374151;
            margin-bottom: 12px;
        }

        /* Back Button */
        .back-section {
            text-align: center;
            margin-top: 60px;
            padding-bottom: 40px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .dashboard-title {
                font-size: 2.2rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .filter-row {
                grid-template-columns: 1fr;
                gap: 16px;
            }
            
            .orders-table-modern th,
            .orders-table-modern td {
                padding: 16px 12px;
                font-size: 0.9rem;
            }
            
            .customer-info-modern {
                gap: 12px;
            }
            
            .customer-avatar-modern {
                width: 40px;
                height: 40px;
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="dashboard-header-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <span class="welcome-badge">📦 Order Management System</span>
                    <h1 class="dashboard-title">
                        <i class="fas fa-shopping-cart me-3"></i>Order Management
                    </h1>
                    <p class="dashboard-subtitle">Manage and track all customer orders efficiently</p>
                </div>
                <div class="col-lg-4 text-lg-end">
                    <a href="dashboard.php" class="btn-modern btn-secondary-modern">
                        <i class="fas fa-arrow-left"></i>Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="dashboard-stats-section">
            <!-- Alert Messages -->
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert-modern alert-success-modern">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert-modern alert-danger-modern">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
                </div>
            <?php endif; ?>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card-modern primary">
                    <div class="stat-icon-modern">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="stat-number-modern"><?php echo $stats['total_orders']; ?></div>
                    <div class="stat-label-modern">Total Orders</div>
                </div>
                <div class="stat-card-modern warning">
                    <div class="stat-icon-modern">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-number-modern"><?php echo $stats['pending_orders']; ?></div>
                    <div class="stat-label-modern">Pending Orders</div>
                </div>
                <div class="stat-card-modern info">
                    <div class="stat-icon-modern">
                        <i class="fas fa-cog"></i>
                    </div>
                    <div class="stat-number-modern"><?php echo $stats['processing_orders']; ?></div>
                    <div class="stat-label-modern">Processing</div>
                </div>
                <div class="stat-card-modern purple">
                    <div class="stat-icon-modern">
                        <i class="fas fa-truck"></i>
                    </div>
                    <div class="stat-number-modern"><?php echo $stats['shipped_orders']; ?></div>
                    <div class="stat-label-modern">Shipped</div>
                </div>
                <div class="stat-card-modern success">
                    <div class="stat-icon-modern">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-number-modern"><?php echo $stats['delivered_orders']; ?></div>
                    <div class="stat-label-modern">Delivered</div>
                </div>
                <div class="stat-card-modern rose">
                    <div class="stat-icon-modern">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="stat-number-modern">Rs.<?php echo number_format($stats['total_revenue'], 2); ?></div>
                    <div class="stat-label-modern">Total Revenue</div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-container">
            <form method="GET" action="">
                <div class="filter-row">
                    <div class="form-group">
                        <label class="form-label">Search Orders</label>
                        <input type="text" name="search" class="form-control-modern" 
                               placeholder="Customer, email, or order ID..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-control-modern">
                            <option value="">All Statuses</option>
                            <option value="Pending" <?php echo $status_filter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="Processing" <?php echo $status_filter === 'Processing' ? 'selected' : ''; ?>>Processing</option>
                            <option value="Shipped" <?php echo $status_filter === 'Shipped' ? 'selected' : ''; ?>>Shipped</option>
                            <option value="Delivered" <?php echo $status_filter === 'Delivered' ? 'selected' : ''; ?>>Delivered</option>
                            <option value="Cancelled" <?php echo $status_filter === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">From Date</label>
                        <input type="date" name="date_from" class="form-control-modern" 
                               value="<?php echo htmlspecialchars($date_from); ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">To Date</label>
                        <input type="date" name="date_to" class="form-control-modern" 
                               value="<?php echo htmlspecialchars($date_to); ?>">
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn-modern btn-primary-modern">
                            <i class="fas fa-search"></i>Filter Orders
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Orders Table -->
        <div class="dashboard-widget">
            <div class="widget-header">
                <h3 class="widget-title">
                    <i class="fas fa-list widget-icon"></i>
                    Orders Management (<?php echo count($orders); ?> orders)
                </h3>
            </div>
            
            <?php if (empty($orders)): ?>
                <div class="empty-state-modern">
                    <div class="empty-icon-modern">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <h4>No Orders Found</h4>
                    <p>No orders match your current filters. Try adjusting your search criteria.</p>
                </div>
            <?php else: ?>
                <div class="orders-container">
                    <table class="orders-table-modern">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Customer</th>
                                <th>Status Update</th>
                                <th>Current Status</th>
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
                                        <div class="customer-info-modern">
                                            <div class="customer-avatar-modern">
                                                <?php echo strtoupper(substr($order['customerName'], 0, 1)); ?>
                                            </div>
                                            <div class="customer-details-modern">
                                                <h6><?php echo htmlspecialchars($order['customerName']); ?></h6>
                                                <small><?php echo htmlspecialchars($order['customerEmail']); ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <form method="POST" class="status-form-modern" data-original="<?php echo $order['status']; ?>">
                                            <input type="hidden" name="order_id" value="<?php echo $order['orderID']; ?>">
                                            <input type="hidden" name="update_status" value="1">
                                            <select name="status" class="status-select-modern">
                                                <option value="Pending" <?php echo $order['status'] === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                                <option value="Processing" <?php echo $order['status'] === 'Processing' ? 'selected' : ''; ?>>Processing</option>
                                                <option value="Shipped" <?php echo $order['status'] === 'Shipped' ? 'selected' : ''; ?>>Shipped</option>
                                                <option value="Delivered" <?php echo $order['status'] === 'Delivered' ? 'selected' : ''; ?>>Delivered</option>
                                                <option value="Cancelled" <?php echo $order['status'] === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                            </select>
                                            <button type="submit" class="btn-update-modern">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        </form>
                                    </td>
                                    <td>
                                        <span class="status-badge-modern status-<?php echo strtolower($order['status']); ?>">
                                            <?php echo $order['status']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong><?php echo $order['item_count'] ?? 0; ?></strong> items
                                    </td>
                                    <td>
                                        <strong>Rs.<?php echo number_format($order['total_amount'] ?? 0, 2); ?></strong>
                                    </td>
                                    <td>
                                        <?php echo date('M d, Y', strtotime($order['date'])); ?><br>
                                        <small style="color: #64748b;"><?php echo date('g:i A', strtotime($order['time'])); ?></small>
                                    </td>
                                    <td>
                                        <a href="view_order_details.php?id=<?php echo $order['orderID']; ?>" class="btn-view-modern">
                                            <i class="fas fa-eye"></i>View Details
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Back Button -->
        <div class="back-section">
            <a href="dashboard.php" class="btn-modern btn-secondary-modern">
                <i class="fas fa-arrow-left"></i>Return to Dashboard
            </a>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Handle status form changes
            document.querySelectorAll('.status-form-modern').forEach(form => {
                const select = form.querySelector('.status-select-modern');
                const button = form.querySelector('.btn-update-modern');
                const originalStatus = form.dataset.original;
                
                select.addEventListener('change', function() {
                    if (this.value !== originalStatus) {
                        button.classList.add('show');
                    } else {
                        button.classList.remove('show');
                    }
                });
                
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const orderId = form.querySelector('input[name="order_id"]').value;
                    const newStatus = select.value;
                    
                    if (newStatus === originalStatus) {
                        alert('Please select a different status to update.');
                        return;
                    }
                    
                    if (confirm(`Are you sure you want to change Order #${orderId} status to ${newStatus}?`)) {
                        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                        button.disabled = true;
                        form.submit();
                    }
                });
            });
        });
    </script>
</body>
</html>

<?php
$conn->close();
?>