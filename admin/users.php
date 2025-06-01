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

<!-- Modern Users Management Styles -->
<style>
.users-management-section {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    position: relative;
    overflow: hidden;
    padding: 80px 0 60px;
}

.users-particles {
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

.users-header-content {
    position: relative;
    z-index: 2;
}

.users-badge {
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

.users-title {
    font-size: 3rem;
    font-weight: 800;
    line-height: 1.2;
    margin-bottom: 16px;
}

.users-subtitle {
    font-size: 1.2rem;
    opacity: 0.9;
    margin-bottom: 0;
}

.users-actions {
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

.users-content {
    padding: 80px 0;
    background: #f8f9ff;
    margin-top: -40px;
    position: relative;
    z-index: 3;
}

.filter-card-modern {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    border-radius: 24px;
    padding: 32px;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.2);
    margin-bottom: 40px;
}

.filter-label {
    font-weight: 600;
    color: #374151;
    margin-bottom: 8px;
    display: block;
    font-size: 0.9rem;
}

.search-group {
    position: relative;
}

.search-icon {
    position: absolute;
    left: 16px;
    top: 50%;
    transform: translateY(-50%);
    color: #9ca3af;
    z-index: 2;
}

.search-input {
    padding: 12px 16px 12px 44px;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    font-size: 1rem;
    transition: all 0.3s ease;
    background: rgba(255, 255, 255, 0.8);
}

.search-input:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    background: white;
}

.modern-select {
    padding: 12px 16px;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    transition: all 0.3s ease;
    background: rgba(255, 255, 255, 0.8);
}

.modern-select:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    background: white;
}

.filter-btn {
    background: linear-gradient(135deg, #667eea, #764ba2);
    border: none;
    color: white;
    padding: 12px 24px;
    border-radius: 12px;
    font-weight: 600;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
}

.filter-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.6);
    color: white;
}

.users-table-card {
    background: white;
    border-radius: 20px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(0, 0, 0, 0.05);
    overflow: hidden;
}

.users-table-header {
    background: linear-gradient(135deg, #f8f9ff, #f1f5f9);
    padding: 24px 32px;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.users-table-header h5 {
    margin: 0;
    font-size: 1.3rem;
    font-weight: 700;
    color: #1e293b;
}

.user-count {
    color: #64748b;
    font-weight: 500;
    font-size: 1rem;
}

.pending-badge {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: white;
    padding: 6px 12px;
    border-radius: 50px;
    text-decoration: none;
    font-size: 0.8rem;
    font-weight: 600;
    margin-left: 12px;
    transition: all 0.3s ease;
}

.pending-badge:hover {
    transform: translateY(-1px);
    color: white;
    box-shadow: 0 4px 12px rgba(245, 158, 11, 0.4);
}

.export-btn {
    background: rgba(102, 126, 234, 0.1);
    border: 1px solid #667eea;
    color: #667eea;
    padding: 8px 16px;
    border-radius: 50px;
    text-decoration: none;
    font-weight: 600;
    font-size: 0.85rem;
    transition: all 0.3s ease;
}

.export-btn:hover {
    background: #667eea;
    color: white;
}

.users-table {
    margin: 0;
}

.users-table th {
    background: #f8f9ff;
    border: none;
    color: #64748b;
    font-weight: 600;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 16px 24px;
}

.users-table td {
    border: none;
    padding: 20px 24px;
    vertical-align: middle;
    border-bottom: 1px solid #f1f5f9;
}

.users-table tr:hover {
    background: #f8f9ff;
}

.user-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
}

.user-avatar-placeholder {
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, #f1f5f9, #e2e8f0);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #667eea;
}

.user-info h6 {
    margin: 0;
    font-weight: 600;
    color: #1e293b;
    font-size: 0.95rem;
}

.user-info small {
    color: #9ca3af;
    font-size: 0.8rem;
}

.status-badge {
    padding: 6px 12px;
    border-radius: 50px;
    font-size: 0.75rem;
    font-weight: 600;
    border: none;
    white-space: nowrap;
    display: inline-flex;
    align-items: center;
}

.status-active {
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
}

.status-suspended {
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: white;
}

.type-badge {
    padding: 6px 12px;
    border-radius: 50px;
    font-size: 0.75rem;
    font-weight: 600;
    white-space: nowrap;
    display: inline-flex;
    align-items: center;
}

.type-pet-owner {
    background: linear-gradient(135deg, #3b82f6, #2563eb);
    color: white;
}

.type-pet-sitter {
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
}

.approval-badge {
    padding: 6px 12px;
    border-radius: 50px;
    font-size: 0.75rem;
    font-weight: 600;
    white-space: nowrap;
    display: inline-flex;
    align-items: center;
}

.approval-approved {
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
}

.approval-pending {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: white;
}

.approval-rejected {
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: white;
}

.action-btn-group {
    display: flex;
    gap: 8px;
}

.action-btn {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    border: none;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
    font-size: 0.8rem;
}

.action-btn:hover {
    transform: translateY(-1px);
}

.btn-view {
    background: linear-gradient(135deg, #3b82f6, #2563eb);
    color: white;
}

.btn-view:hover {
    color: white;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
}

.btn-suspend {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: white;
}

.btn-suspend:hover {
    color: white;
    box-shadow: 0 4px 12px rgba(245, 158, 11, 0.4);
}

.btn-activate {
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
}

.btn-activate:hover {
    color: white;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
}

.btn-approve {
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
}

.btn-approve:hover {
    color: white;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
}

.btn-reject {
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: white;
}

.btn-reject:hover {
    color: white;
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
}

.btn-delete {
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: white;
}

.btn-delete:hover {
    color: white;
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
}

.approval-guide-card {
    background: white;
    border-radius: 20px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(0, 0, 0, 0.05);
    padding: 32px;
    margin-top: 40px;
}

.approval-guide-header {
    margin-bottom: 24px;
}

.approval-guide-header h5 {
    color: #1e293b;
    font-weight: 700;
    margin: 0;
}

.guide-item {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 24px;
}

.guide-item:last-child {
    margin-bottom: 0;
}

.guide-badge {
    padding: 8px 16px;
    border-radius: 50px;
    font-weight: 600;
    font-size: 0.8rem;
    flex-shrink: 0;
}

.guide-content p {
    margin: 0;
    color: #64748b;
    line-height: 1.5;
}

.no-users-card {
    background: white;
    border-radius: 20px;
    padding: 60px 40px;
    text-align: center;
    border: 2px dashed #d1d5db;
    color: #9ca3af;
}

.no-users-icon {
    font-size: 4rem;
    margin-bottom: 24px;
    color: #d1d5db;
}

.no-users-card h4 {
    color: #374151;
    margin-bottom: 16px;
}

.no-users-card p {
    color: #6b7280;
    margin-bottom: 32px;
}

@media (max-width: 991px) {
    .users-title {
        font-size: 2.5rem;
    }
    
    .users-actions {
        justify-content: center;
        margin-top: 20px;
    }
    
    .users-table-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 16px;
    }
    
    .filter-card-modern {
        padding: 24px;
    }
}

@media (max-width: 768px) {
    .users-table th,
    .users-table td {
        padding: 12px 16px;
    }
    
    .action-btn-group {
        flex-direction: column;
        gap: 4px;
    }
    
    .guide-item {
        flex-direction: column;
        align-items: flex-start;
        text-align: left;
    }
}
</style>

<!-- Modern Users Management Header -->
<section class="users-management-section">
    <div class="users-particles"></div>
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <div class="users-header-content">
                    <div class="users-badge">
                        <i class="fas fa-users me-2"></i>
                        User Management System
                    </div>
                    <h1 class="users-title">Manage Users</h1>
                    <p class="users-subtitle">Oversee and manage all users of the Pet Care & Sitting System with advanced controls and insights.</p>
                </div>
            </div>
            <div class="col-lg-4 text-lg-end">
                <div class="users-actions">
                    <a href="dashboard.php" class="btn btn-glass">
                        <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Modern Users Management Content -->
<section class="users-content">
    <div class="container">
        <!-- Search and Filter -->
        <div class="filter-card-modern">
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="get" class="row g-3">
                <div class="col-md-3">
                    <label class="filter-label">Search Users</label>
                    <div class="search-group">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" class="form-control search-input" name="search" placeholder="Search users..." value="<?php echo htmlspecialchars($search_query); ?>">
                    </div>
                </div>
                
                <div class="col-md-3">
                    <label class="filter-label">User Type</label>
                    <select class="form-select modern-select" name="user_type">
                        <option value="" <?php echo empty($user_type_filter) ? 'selected' : ''; ?>>All Users</option>
                        <option value="pet_owner" <?php echo ($user_type_filter === 'pet_owner') ? 'selected' : ''; ?>>Pet Owners</option>
                        <option value="pet_sitter" <?php echo ($user_type_filter === 'pet_sitter') ? 'selected' : ''; ?>>Pet Sitters</option>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="filter-label">Approval Status</label>
                    <select class="form-select modern-select" name="approval_status" <?php echo ($user_type_filter !== 'pet_sitter' && !empty($user_type_filter)) ? 'disabled' : ''; ?>>
                        <option value="" <?php echo empty($approval_filter) ? 'selected' : ''; ?>>All Status</option>
                        <option value="Pending" <?php echo ($approval_filter === 'Pending') ? 'selected' : ''; ?>>Pending</option>
                        <option value="Approved" <?php echo ($approval_filter === 'Approved') ? 'selected' : ''; ?>>Approved</option>
                        <option value="Rejected" <?php echo ($approval_filter === 'Rejected') ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="filter-label">Sort By</label>
                    <select class="form-select modern-select" name="sort">
                        <option value="newest" <?php echo ($sort_by === 'newest') ? 'selected' : ''; ?>>Newest First</option>
                        <option value="oldest" <?php echo ($sort_by === 'oldest') ? 'selected' : ''; ?>>Oldest First</option>
                        <option value="name_asc" <?php echo ($sort_by === 'name_asc') ? 'selected' : ''; ?>>Name (A-Z)</option>
                        <option value="name_desc" <?php echo ($sort_by === 'name_desc') ? 'selected' : ''; ?>>Name (Z-A)</option>
                    </select>
                </div>
                
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn filter-btn w-100">
                        <i class="fas fa-filter me-2"></i>Apply
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Users Table -->
        <div class="users-table-card">
            <div class="users-table-header">
                <div>
                    <h5 class="d-inline-block">Users <span class="user-count">(<?php echo count($users); ?>)</span></h5>
                    <?php if ($pending_sitters_count > 0): ?>
                        <a href="?user_type=pet_sitter&approval_status=Pending" class="pending-badge">
                            <?php echo $pending_sitters_count; ?> pending application<?php echo $pending_sitters_count !== 1 ? 's' : ''; ?>
                        </a>
                    <?php endif; ?>
                </div>
                <a href="export_users.php" class="export-btn">
                    <i class="fas fa-download me-2"></i>Export
                </a>
            </div>
            <div class="table-responsive">
                <?php if (empty($users)): ?>
                    <div class="no-users-card">
                        <div class="no-users-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <h4>No Users Found</h4>
                        <p>No users match your search criteria. Try adjusting your filters.</p>
                    </div>
                <?php else: ?>
                    <table class="table users-table mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>User</th>
                                <th>Email</th>
                                <th>Type</th>
                                <th>Joined</th>
                                <th>Status</th>
                                <?php if ($user_type_filter === 'pet_sitter' || empty($user_type_filter)): ?>
                                    <th>Approval</th>
                                <?php endif; ?>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><strong><?php echo $user['userID']; ?></strong></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="me-3">
                                                <?php if (!empty($user['image'])): ?>
                                                    <img src="../assets/images/<?php echo $user['user_type'] === 'pet_sitter' ? 'pet_sitters' : 'users'; ?>/<?php echo htmlspecialchars($user['image']); ?>" class="user-avatar" alt="<?php echo htmlspecialchars($user['fullName']); ?>">
                                                <?php else: ?>
                                                    <div class="user-avatar-placeholder">
                                                        <i class="fas fa-user"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="user-info">
                                                <h6><?php echo htmlspecialchars($user['fullName']); ?></h6>
                                                <small><?php echo htmlspecialchars($user['username']); ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td>
                                        <?php if ($user['user_type'] === 'pet_owner'): ?>
                                            <span class="type-badge type-pet-owner">
                                                <i class="fas fa-heart me-1"></i>Owner
                                            </span>
                                        <?php else: ?>
                                            <span class="type-badge type-pet-sitter">
                                                <i class="fas fa-hands-helping me-1"></i>Sitter
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                    <td>
                                        <?php
                                        $status = isset($user['status']) ? $user['status'] : 'Active';
                                        $status_class = ($status === 'Active') ? 'status-active' : 'status-suspended';
                                        ?>
                                        <span class="status-badge <?php echo $status_class; ?>"><?php echo $status; ?></span>
                                    </td>
                                    
                                    <?php if ($user['user_type'] === 'pet_sitter' || empty($user_type_filter)): ?>
                                        <td>
                                            <?php if ($user['user_type'] === 'pet_sitter'): ?>
                                                <?php
                                                $approval_status = isset($user['approval_status']) ? $user['approval_status'] : 'Pending';
                                                $approval_class = '';
                                                
                                                switch ($approval_status) {
                                                    case 'Approved':
                                                        $approval_class = 'approval-approved';
                                                        break;
                                                    case 'Pending':
                                                        $approval_class = 'approval-pending';
                                                        break;
                                                    case 'Rejected':
                                                        $approval_class = 'approval-rejected';
                                                        break;
                                                }
                                                ?>
                                                <span class="approval-badge <?php echo $approval_class; ?>"><?php echo $approval_status; ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endif; ?>
                                    
                                    <td>
                                        <div class="action-btn-group">
                                            <a href="view_user.php?id=<?php echo $user['userID']; ?>&type=<?php echo $user['user_type']; ?>" class="action-btn btn-view" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            
                                            <?php if ($status === 'Active'): ?>
                                                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to suspend this user?');">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['userID']; ?>">
                                                    <input type="hidden" name="user_type" value="<?php echo $user['user_type']; ?>">
                                                    <input type="hidden" name="status" value="Suspended">
                                                    <button type="submit" name="update_status" class="action-btn btn-suspend" title="Suspend User">
                                                        <i class="fas fa-ban"></i>
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to activate this user?');">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['userID']; ?>">
                                                    <input type="hidden" name="user_type" value="<?php echo $user['user_type']; ?>">
                                                    <input type="hidden" name="status" value="Active">
                                                    <button type="submit" name="update_status" class="action-btn btn-activate" title="Activate User">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <?php if ($user['user_type'] === 'pet_sitter' && isset($user['approval_status'])): ?>
                                                <?php if ($user['approval_status'] === 'Pending'): ?>
                                                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="d-inline">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['userID']; ?>">
                                                        <input type="hidden" name="approval_status" value="Approved">
                                                        <button type="submit" name="update_approval_status" class="action-btn btn-approve" title="Approve Pet Sitter">
                                                            <i class="fas fa-check-circle"></i>
                                                        </button>
                                                    </form>
                                                    
                                                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="d-inline">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['userID']; ?>">
                                                        <input type="hidden" name="approval_status" value="Rejected">
                                                        <button type="submit" name="update_approval_status" class="action-btn btn-reject" title="Reject Pet Sitter">
                                                            <i class="fas fa-times-circle"></i>
                                                        </button>
                                                    </form>
                                                <?php elseif ($user['approval_status'] === 'Rejected'): ?>
                                                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="d-inline">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['userID']; ?>">
                                                        <input type="hidden" name="approval_status" value="Approved">
                                                        <button type="submit" name="update_approval_status" class="action-btn btn-approve" title="Approve Pet Sitter">
                                                            <i class="fas fa-check-circle"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                            
                                            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this user? This action cannot be undone.');">
                                                <input type="hidden" name="user_id" value="<?php echo $user['userID']; ?>">
                                                <input type="hidden" name="user_type" value="<?php echo $user['user_type']; ?>">
                                                <button type="submit" name="delete_user" class="action-btn btn-delete" title="Delete User">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Pet Sitter Approval Guide -->
        <?php if ($user_type_filter === 'pet_sitter' || empty($user_type_filter)): ?>
            <div class="approval-guide-card">
                <div class="approval-guide-header">
                    <h5>Pet Sitter Approval Guide</h5>
                </div>
                
                <div class="row">
                    <div class="col-md-4">
                        <div class="guide-item">
                            <span class="guide-badge approval-pending">Pending</span>
                            <div class="guide-content">
                                <p><strong>New Applications:</strong> Pet sitter applications awaiting admin review and verification.</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="guide-item">
                            <span class="guide-badge approval-approved">Approved</span>
                            <div class="guide-content">
                                <p><strong>Active Sitters:</strong> Verified pet sitters who can offer services to pet owners.</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="guide-item">
                            <span class="guide-badge approval-rejected">Rejected</span>
                            <div class="guide-content">
                                <p><strong>Rejected Applications:</strong> Applications that didn't meet our requirements.</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="alert" style="background: linear-gradient(135deg, #dbeafe, #bfdbfe); border: none; border-radius: 12px; padding: 16px; margin-top: 24px; margin-bottom: 0;">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-info-circle me-2" style="color: #1e40af;"></i>
                        <span style="color: #1e40af;"><strong>Note:</strong> Only approved pet sitters are visible to pet owners and can receive bookings. Review applications carefully before approval.</span>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<script>
// Enable/disable approval status filter based on user type selection
document.addEventListener('DOMContentLoaded', function() {
    const userTypeSelect = document.querySelector('select[name="user_type"]');
    const approvalSelect = document.querySelector('select[name="approval_status"]');
    
    if (userTypeSelect && approvalSelect) {
        userTypeSelect.addEventListener('change', function() {
            if (this.value === 'pet_sitter' || this.value === '') {
                approvalSelect.disabled = false;
                approvalSelect.style.opacity = '1';
            } else {
                approvalSelect.disabled = true;
                approvalSelect.value = '';
                approvalSelect.style.opacity = '0.5';
            }
        });
        
        // Initialize on page load
        if (userTypeSelect.value === 'pet_owner') {
            approvalSelect.disabled = true;
            approvalSelect.style.opacity = '0.5';
        }
    }
});
</script>

<?php
// Include footer
include_once '../includes/footer.php';

// Close database connection
$conn->close();
?>