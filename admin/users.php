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

// Process pet sitter approval
if (isset($_POST['update_approval_status']) && isset($_POST['user_id']) && isset($_POST['approval_status'])) {
    $user_id = $_POST['user_id'];
    $approval_status = $_POST['approval_status'];
    
    // Update pet sitter approval status
    $update_sql = "UPDATE pet_sitter SET approval_status = ? WHERE userID = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("si", $approval_status, $user_id);
    
    if ($update_stmt->execute()) {
        $_SESSION['success_message'] = "Pet sitter approval status updated successfully.";
        
        // Get pet sitter email for notification
        $email_sql = "SELECT email, fullName FROM pet_sitter WHERE userID = ?";
        $email_stmt = $conn->prepare($email_sql);
        $email_stmt->bind_param("i", $user_id);
        $email_stmt->execute();
        $sitter = $email_stmt->get_result()->fetch_assoc();
        
        // Send email notification (simulation - in a real system, you'd use PHPMailer or similar)
        $to = $sitter['email'];
        $subject = "Pet Care & Sitting System - Application Status Update";
        
        if ($approval_status === 'Approved') {
            $message = "Dear " . $sitter['fullName'] . ",\n\n";
            $message .= "Congratulations! Your application to become a pet sitter on our platform has been approved.\n";
            $message .= "You can now log in to your account and start offering your services to pet owners.\n\n";
            $message .= "Thank you for joining our community!\n\n";
            $message .= "Best regards,\nPet Care & Sitting System Team";
        } else if ($approval_status === 'Rejected') {
            $message = "Dear " . $sitter['fullName'] . ",\n\n";
            $message .= "We regret to inform you that your application to become a pet sitter on our platform has been rejected.\n";
            $message .= "If you have any questions or would like to provide additional information, please contact our support team.\n\n";
            $message .= "Best regards,\nPet Care & Sitting System Team";
        }
        
        // In a real system, you would send the email here
        // mail($to, $subject, $message, "From: noreply@petcare.com\r\n");
        
        $email_stmt->close();
    } else {
        $_SESSION['error_message'] = "Failed to update approval status: " . $conn->error;
    }
    
    $update_stmt->close();
    
    // Redirect to refresh page
    header("Location: users.php");
    exit();
}

// Get filter and search parameters
$user_type_filter = isset($_GET['user_type']) ? $_GET['user_type'] : '';
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$approval_filter = isset($_GET['approval_status']) ? $_GET['approval_status'] : '';

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

// Add approval status filter for pet sitters
if (!empty($approval_filter)) {
    $pet_sitters_where[] = "approval_status = ?";
    $pet_sitters_params[] = $approval_filter;
    $pet_sitters_types .= "s";
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

// Get pending pet sitter count for notification badge
$pending_sitters_sql = "SELECT COUNT(*) as count FROM pet_sitter WHERE approval_status = 'Pending'";
$pending_sitters_result = $conn->query($pending_sitters_sql);
$pending_sitters_count = $pending_sitters_result->fetch_assoc()['count'];

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
                <div class="col-md-3">
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
                
                <div class="col-md-2">
                    <select class="form-select" name="approval_status" <?php echo ($user_type_filter !== 'pet_sitter' && !empty($user_type_filter)) ? 'disabled' : ''; ?>>
                        <option value="" <?php echo empty($approval_filter) ? 'selected' : ''; ?>>All Approval Status</option>
                        <option value="Pending" <?php echo ($approval_filter === 'Pending') ? 'selected' : ''; ?>>Pending Approval</option>
                        <option value="Approved" <?php echo ($approval_filter === 'Approved') ? 'selected' : ''; ?>>Approved</option>
                        <option value="Rejected" <?php echo ($approval_filter === 'Rejected') ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                </div>
                
                <div class="col-md-2">
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
            <div>
                <h5 class="mb-0 d-inline-block">Users (<?php echo count($users); ?>)</h5>
                <?php if ($pending_sitters_count > 0): ?>
                    <a href="?user_type=pet_sitter&approval_status=Pending" class="ms-2 badge bg-warning text-decoration-none">
                        <?php echo $pending_sitters_count; ?> pending sitter application<?php echo $pending_sitters_count !== 1 ? 's' : ''; ?>
                    </a>
                <?php endif; ?>
            </div>
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
                                <?php if ($user_type_filter === 'pet_sitter' || empty($user_type_filter)): ?>
                                    <th>Approval Status</th>
                                <?php endif; ?>
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
                                    
                                    <?php if ($user['user_type'] === 'pet_sitter' || empty($user_type_filter)): ?>
                                        <td>
                                            <?php if ($user['user_type'] === 'pet_sitter'): ?>
                                                <?php
                                                $approval_status = isset($user['approval_status']) ? $user['approval_status'] : 'Pending';
                                                $approval_class = '';
                                                
                                                switch ($approval_status) {
                                                    case 'Approved':
                                                        $approval_class = 'bg-success';
                                                        break;
                                                    case 'Pending':
                                                        $approval_class = 'bg-warning';
                                                        break;
                                                    case 'Rejected':
                                                        $approval_class = 'bg-danger';
                                                        break;
                                                }
                                                ?>
                                                <span class="badge <?php echo $approval_class; ?>"><?php echo $approval_status; ?></span>
                                            <?php else: ?>
                                                <span>-</span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endif; ?>
                                    
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
                                            
                                            <?php if ($user['user_type'] === 'pet_sitter' && isset($user['approval_status'])): ?>
                                                <?php if ($user['approval_status'] === 'Pending'): ?>
                                                    <div class="btn-group">
                                                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="d-inline">
                                                            <input type="hidden" name="user_id" value="<?php echo $user['userID']; ?>">
                                                            <input type="hidden" name="approval_status" value="Approved">
                                                            <button type="submit" name="update_approval_status" class="btn btn-sm btn-outline-success" title="Approve Pet Sitter">
                                                                <i class="fas fa-check-circle"></i>
                                                            </button>
                                                        </form>
                                                        
                                                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="d-inline">
                                                            <input type="hidden" name="user_id" value="<?php echo $user['userID']; ?>">
                                                            <input type="hidden" name="approval_status" value="Rejected">
                                                            <button type="submit" name="update_approval_status" class="btn btn-sm btn-outline-danger" title="Reject Pet Sitter">
                                                                <i class="fas fa-times-circle"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                <?php elseif ($user['approval_status'] === 'Rejected'): ?>
                                                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="d-inline">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['userID']; ?>">
                                                        <input type="hidden" name="approval_status" value="Approved">
                                                        <button type="submit" name="update_approval_status" class="btn btn-sm btn-outline-success" title="Approve Pet Sitter">
                                                            <i class="fas fa-check-circle"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
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
    
    <!-- Pet Sitter Approval Guide -->
    <?php if ($user_type_filter === 'pet_sitter' || empty($user_type_filter)): ?>
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0">Pet Sitter Approval Guide</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <div class="d-flex align-items-center">
                            <span class="badge bg-warning me-2 p-2">Pending</span>
                            <div>
                                <p class="mb-0">New pet sitter applications awaiting review.</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <div class="d-flex align-items-center">
                            <span class="badge bg-success me-2 p-2">Approved</span>
                            <div>
                                <p class="mb-0">Pet sitters who can offer services to pet owners.</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <div class="d-flex align-items-center">
                            <span class="badge bg-danger me-2 p-2">Rejected</span>
                            <div>
                                <p class="mb-0">Pet sitters who have been rejected and cannot offer services.</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="alert alert-info mt-3 mb-0">
                    <i class="fas fa-info-circle me-2"></i> 
                    <strong>Note:</strong> Only approved pet sitters are visible to pet owners and can receive bookings. Review applications carefully before approval.
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
    // Enable/disable approval status filter based on user type selection
    document.addEventListener('DOMContentLoaded', function() {
        const userTypeSelect = document.querySelector('select[name="user_type"]');
        const approvalSelect = document.querySelector('select[name="approval_status"]');
        
        if (userTypeSelect && approvalSelect) {
            userTypeSelect.addEventListener('change', function() {
                if (this.value === 'pet_sitter' || this.value === '') {
                    approvalSelect.disabled = false;
                } else {
                    approvalSelect.disabled = true;
                    approvalSelect.value = '';
                }
            });
        }
    });
</script>

<?php
// Include footer
include_once '../includes/footer.php';

// Close database connection
$conn->close();
?>