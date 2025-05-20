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

// Process user deletion
if (isset($_POST['delete_user']) && isset($_POST['user_id']) && isset($_POST['user_type'])) {
    $user_id = $_POST['user_id'];
    $user_type = $_POST['user_type'];
    
    // Determine table based on user type
    $table = "";
    if ($user_type === "pet_owner") {
        $table = "pet_owner";
    } elseif ($user_type === "pet_sitter") {
        $table = "pet_sitter";
    }
    
    if (!empty($table)) {
        // Check if the user has active bookings or orders
        $has_active_items = false;
        
        if ($user_type === "pet_owner") {
            // Check for active bookings
            $booking_check_sql = "SELECT COUNT(*) as count FROM booking WHERE userID = ? AND status IN ('Pending', 'Confirmed')";
            $booking_check_stmt = $conn->prepare($booking_check_sql);
            $booking_check_stmt->bind_param("i", $user_id);
            $booking_check_stmt->execute();
            $booking_count = $booking_check_stmt->get_result()->fetch_assoc()['count'];
            $booking_check_stmt->close();
            
            // Check for active orders
            $order_check_sql = "SELECT COUNT(*) as count FROM `order` WHERE userID = ? AND status IN ('Pending', 'Processing', 'Shipped')";
            $order_check_stmt = $conn->prepare($order_check_sql);
            $order_check_stmt->bind_param("i", $user_id);
            $order_check_stmt->execute();
            $order_count = $order_check_stmt->get_result()->fetch_assoc()['count'];
            $order_check_stmt->close();
            
            $has_active_items = ($booking_count > 0 || $order_count > 0);
        } elseif ($user_type === "pet_sitter") {
            // Check for active bookings
            $booking_check_sql = "SELECT COUNT(*) as count FROM booking WHERE sitterID = ? AND status IN ('Pending', 'Confirmed')";
            $booking_check_stmt = $conn->prepare($booking_check_sql);
            $booking_check_stmt->bind_param("i", $user_id);
            $booking_check_stmt->execute();
            $booking_count = $booking_check_stmt->get_result()->fetch_assoc()['count'];
            $booking_check_stmt->close();
            
            $has_active_items = ($booking_count > 0);
        }
        
        if ($has_active_items) {
            $_SESSION['error_message'] = "Cannot delete user with active bookings or orders.";
        } else {
            // Delete user
            $delete_sql = "DELETE FROM $table WHERE userID = ?";
            $delete_stmt = $conn->prepare($delete_sql);
            $delete_stmt->bind_param("i", $user_id);
            
            if ($delete_stmt->execute()) {
                $_SESSION['success_message'] = "User deleted successfully.";
            } else {
                $_SESSION['error_message'] = "Failed to delete user: " . $conn->error;
            }
            
            $delete_stmt->close();
        }
    } else {
        $_SESSION['error_message'] = "Invalid user type.";
    }
    
    // Redirect to refresh page
    header("Location: users.php");
    exit();
}

// Process user status update
if (isset($_POST['update_status']) && isset($_POST['user_id']) && isset($_POST['user_type']) && isset($_POST['status'])) {
    $user_id = $_POST['user_id'];
    $user_type = $_POST['user_type'];
    $status = $_POST['status'];
    
    // Determine table based on user type
    $table = "";
    if ($user_type === "pet_owner") {
        $table = "pet_owner";
    } elseif ($user_type === "pet_sitter") {
        $table = "pet_sitter";
    }
    
    if (!empty($table)) {
        // Update user status
        $update_sql = "UPDATE $table SET status = ? WHERE userID = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("si", $status, $user_id);
        
        if ($update_stmt->execute()) {
            $_SESSION['success_message'] = "User status updated successfully.";
        } else {
            $_SESSION['error_message'] = "Failed to update user status: " . $conn->error;
        }
        
        $update_stmt->close();
    } else {
        $_SESSION['error_message'] = "Invalid user type.";
    }
    
    // Redirect to refresh page
    header("Location: users.php");
    exit();
}

// Get filter and search parameters
$user_type_filter = isset($_GET['user_type']) ? $_GET['user_type'] : '';
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

// Prepare SQL for pet owners
$pet_owners_sql = "SELECT *, 'pet_owner' as user_type FROM pet_owner";
$pet_owners_where = [];
$pet_owners_params = [];
$pet_owners_types = "";

if (!empty($search_query)) {
    $pet_owners_where[] = "(fullName LIKE ? OR email LIKE ? OR username LIKE ?)";
    $search_param = "%{$search_query}%";
    $pet_owners_params[] = $search_param;
    $pet_owners_params[] = $search_param;
    $pet_owners_params[] = $search_param;
    $pet_owners_types .= "sss";
}

// Add WHERE clause to pet owners SQL if needed
if (!empty($pet_owners_where)) {
    $pet_owners_sql .= " WHERE " . implode(" AND ", $pet_owners_where);
}

// Prepare SQL for pet sitters
$pet_sitters_sql = "SELECT *, 'pet_sitter' as user_type FROM pet_sitter";
$pet_sitters_where = [];
$pet_sitters_params = [];
$pet_sitters_types = "";

if (!empty($search_query)) {
    $pet_sitters_where[] = "(fullName LIKE ? OR email LIKE ? OR username LIKE ?)";
    $search_param = "%{$search_query}%";
    $pet_sitters_params[] = $search_param;
    $pet_sitters_params[] = $search_param;
    $pet_sitters_params[] = $search_param;
    $pet_sitters_types .= "sss";
}

// Add WHERE clause to pet sitters SQL if needed
if (!empty($pet_sitters_where)) {
    $pet_sitters_sql .= " WHERE " . implode(" AND ", $pet_sitters_where);
}

// Combine queries based on user type filter
$users = [];

if ($user_type_filter === 'pet_owner' || empty($user_type_filter)) {
    $stmt = $conn->prepare($pet_owners_sql);
    
    if (!empty($pet_owners_params)) {
        $stmt->bind_param($pet_owners_types, ...$pet_owners_params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    
    $stmt->close();
}

if ($user_type_filter === 'pet_sitter' || empty($user_type_filter)) {
    $stmt = $conn->prepare($pet_sitters_sql);
    
    if (!empty($pet_sitters_params)) {
        $stmt->bind_param($pet_sitters_types, ...$pet_sitters_params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    
    $stmt->close();
}

// Sort users
switch ($sort_by) {
    case 'name_asc':
        usort($users, function($a, $b) {
            return strcmp($a['fullName'], $b['fullName']);
        });
        break;
    case 'name_desc':
        usort($users, function($a, $b) {
            return strcmp($b['fullName'], $a['fullName']);
        });
        break;
    case 'oldest':
        usort($users, function($a, $b) {
            return strtotime($a['created_at']) - strtotime($b['created_at']);
        });
        break;
    case 'newest':
    default:
        usort($users, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
        break;
}

// Include header
include_once '../includes/header.php';
?>

<!-- Page Header -->
<div class="container-fluid bg-primary text-white py-4">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="display-4 mb-2">User Management</h1>
                <p class="lead">Manage users of the Pet Care & Sitting System.</p>
            </div>
            <div class="col-md-4 text-md-end">
                <a href="dashboard.php" class="btn btn-light">
                    <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </div>
</div>

<!-- User Management -->
<div class="container py-5">
    <!-- Search and Filter -->
    <div class="card mb-4">
        <div class="card-body">
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="get" class="row g-3">
                <div class="col-md-4">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" class="form-control" name="search" placeholder="Search users..." value="<?php echo htmlspecialchars($search_query); ?>">
                    </div>
                </div>
                
                <div class="col-md-3">
                    <select class="form-select" name="user_type">
                        <option value="" <?php echo empty($user_type_filter) ? 'selected' : ''; ?>>All Users</option>
                        <option value="pet_owner" <?php echo ($user_type_filter === 'pet_owner') ? 'selected' : ''; ?>>Pet Owners</option>
                        <option value="pet_sitter" <?php echo ($user_type_filter === 'pet_sitter') ? 'selected' : ''; ?>>Pet Sitters</option>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <select class="form-select" name="sort">
                        <option value="newest" <?php echo ($sort_by === 'newest') ? 'selected' : ''; ?>>Newest First</option>
                        <option value="oldest" <?php echo ($sort_by === 'oldest') ? 'selected' : ''; ?>>Oldest First</option>
                        <option value="name_asc" <?php echo ($sort_by === 'name_asc') ? 'selected' : ''; ?>>Name (A-Z)</option>
                        <option value="name_desc" <?php echo ($sort_by === 'name_desc') ? 'selected' : ''; ?>>Name (Z-A)</option>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Apply</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Users Table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Users (<?php echo count($users); ?>)</h5>
            <a href="export_users.php" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-download me-1"></i> Export
            </a>
        </div>
        <div class="card-body p-0">
            <?php if (empty($users)): ?>
                <div class="p-4 text-center">
                    <p class="text-muted">No users found matching your criteria.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>User Type</th>
                                <th>Joined</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo $user['userID']; ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="me-3">
                                                <?php if (!empty($user['image'])): ?>
                                                    <img src="../assets/images/<?php echo $user['user_type'] === 'pet_sitter' ? 'pet_sitters' : 'users'; ?>/<?php echo htmlspecialchars($user['image']); ?>" class="rounded-circle" width="40" height="40" alt="<?php echo htmlspecialchars($user['fullName']); ?>">
                                                <?php else: ?>
                                                    <div class="rounded-circle bg-light text-primary d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                                        <i class="fas fa-user"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <?php echo htmlspecialchars($user['fullName']); ?>
                                                <div class="text-muted small"><?php echo htmlspecialchars($user['username']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td>
                                        <?php if ($user['user_type'] === 'pet_owner'): ?>
                                            <span class="badge bg-primary">Pet Owner</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Pet Sitter</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                    <td>
                                        <?php
                                        $status = isset($user['status']) ? $user['status'] : 'Active';
                                        $status_class = ($status === 'Active') ? 'bg-success' : 'bg-danger';
                                        ?>
                                        <span class="badge <?php echo $status_class; ?>"><?php echo $status; ?></span>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="view_user.php?id=<?php echo $user['userID']; ?>&type=<?php echo $user['user_type']; ?>" class="btn btn-sm btn-outline-primary" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            
                                            <?php if ($status === 'Active'): ?>
                                                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to suspend this user?');">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['userID']; ?>">
                                                    <input type="hidden" name="user_type" value="<?php echo $user['user_type']; ?>">
                                                    <input type="hidden" name="status" value="Suspended">
                                                    <button type="submit" name="update_status" class="btn btn-sm btn-outline-warning" title="Suspend User">
                                                        <i class="fas fa-ban"></i>
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to activate this user?');">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['userID']; ?>">
                                                    <input type="hidden" name="user_type" value="<?php echo $user['user_type']; ?>">
                                                    <input type="hidden" name="status" value="Active">
                                                    <button type="submit" name="update_status" class="btn btn-sm btn-outline-success" title="Activate User">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this user? This action cannot be undone.');">
                                                <input type="hidden" name="user_id" value="<?php echo $user['userID']; ?>">
                                                <input type="hidden" name="user_type" value="<?php echo $user['user_type']; ?>">
                                                <button type="submit" name="delete_user" class="btn btn-sm btn-outline-danger" title="Delete User">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- User Statistics -->
    <div class="row mt-4">
        <!-- Pet Owners -->
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0">Pet Owners Statistics</h5>
                </div>
                <div class="card-body">
                    <?php
                    // Get pet owners statistics
                    $pet_owners_stats_sql = "SELECT 
                                             COUNT(*) as total,
                                             COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as today,
                                             COUNT(CASE WHEN DATE(created_at) BETWEEN DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND CURDATE() THEN 1 END) as last_week,
                                             COUNT(CASE WHEN DATE(created_at) BETWEEN DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND CURDATE() THEN 1 END) as last_month
                                           FROM pet_owner";
                    $pet_owners_stats_result = $conn->query($pet_owners_stats_sql);
                    $pet_owners_stats = $pet_owners_stats_result->fetch_assoc();
                    ?>
                    
                    <div class="row text-center">
                        <div class="col-6 col-md-3 mb-3">
                            <h3 class="text-primary"><?php echo $pet_owners_stats['total']; ?></h3>
                            <p class="text-muted small mb-0">Total</p>
                        </div>
                        <div class="col-6 col-md-3 mb-3">
                            <h3 class="text-success"><?php echo $pet_owners_stats['today']; ?></h3>
                            <p class="text-muted small mb-0">Today</p>
                        </div>
                        <div class="col-6 col-md-3 mb-3">
                            <h3 class="text-info"><?php echo $pet_owners_stats['last_week']; ?></h3>
                            <p class="text-muted small mb-0">Last 7 Days</p>
                        </div>
                        <div class="col-6 col-md-3 mb-3">
                            <h3 class="text-warning"><?php echo $pet_owners_stats['last_month']; ?></h3>
                            <p class="text-muted small mb-0">Last 30 Days</p>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <h6>Pet Owners Growth</h6>
                    <div class="progress mb-3">
                        <div class="progress-bar bg-primary" role="progressbar" style="width: <?php echo ($pet_owners_stats['last_month'] / max($pet_owners_stats['total'], 1)) * 100; ?>%" aria-valuenow="<?php echo $pet_owners_stats['last_month']; ?>" aria-valuemin="0" aria-valuemax="<?php echo $pet_owners_stats['total']; ?>"></div>
                    </div>
                    
                    <div class="text-end">
                        <small class="text-muted"><?php echo round(($pet_owners_stats['last_month'] / max($pet_owners_stats['total'], 1)) * 100); ?>% of total in the last month</small>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Pet Sitters -->
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0">Pet Sitters Statistics</h5>
                </div>
                <div class="card-body">
                    <?php
                    // Get pet sitters statistics
                    $pet_sitters_stats_sql = "SELECT 
                                             COUNT(*) as total,
                                             COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as today,
                                             COUNT(CASE WHEN DATE(created_at) BETWEEN DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND CURDATE() THEN 1 END) as last_week,
                                             COUNT(CASE WHEN DATE(created_at) BETWEEN DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND CURDATE() THEN 1 END) as last_month
                                           FROM pet_sitter";
                    $pet_sitters_stats_result = $conn->query($pet_sitters_stats_sql);
                    $pet_sitters_stats = $pet_sitters_stats_result->fetch_assoc();
                    ?>
                    
                    <div class="row text-center">
                        <div class="col-6 col-md-3 mb-3">
                            <h3 class="text-primary"><?php echo $pet_sitters_stats['total']; ?></h3>
                            <p class="text-muted small mb-0">Total</p>
                        </div>
                        <div class="col-6 col-md-3 mb-3">
                            <h3 class="text-success"><?php echo $pet_sitters_stats['today']; ?></h3>
                            <p class="text-muted small mb-0">Today</p>
                        </div>
                        <div class="col-6 col-md-3 mb-3">
                            <h3 class="text-info"><?php echo $pet_sitters_stats['last_week']; ?></h3>
                            <p class="text-muted small mb-0">Last 7 Days</p>
                        </div>
                        <div class="col-6 col-md-3 mb-3">
                            <h3 class="text-warning"><?php echo $pet_sitters_stats['last_month']; ?></h3>
                            <p class="text-muted small mb-0">Last 30 Days</p>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <h6>Pet Sitters Growth</h6>
                    <div class="progress mb-3">
                        <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo ($pet_sitters_stats['last_month'] / max($pet_sitters_stats['total'], 1)) * 100; ?>%" aria-valuenow="<?php echo $pet_sitters_stats['last_month']; ?>" aria-valuemin="0" aria-valuemax="<?php echo $pet_sitters_stats['total']; ?>"></div>
                    </div>
                    
                    <div class="text-end">
                        <small class="text-muted"><?php echo round(($pet_sitters_stats['last_month'] / max($pet_sitters_stats['total'], 1)) * 100); ?>% of total in the last month</small>
                    </div>
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