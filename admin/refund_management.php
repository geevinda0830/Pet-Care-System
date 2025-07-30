<?php
// refund_management.php
session_start();

// Check if user is logged in as admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    $_SESSION['error_message'] = "You must be logged in as an administrator to access this page.";
    header("Location: ../login.php");
    exit();
}

require_once '../config/db_connect.php';

// Handle refund status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_refund_status'])) {
        $refund_id = intval($_POST['refund_id']);
        $new_status = trim($_POST['refund_status']);
        $admin_notes = trim($_POST['admin_notes']);
        
        $valid_statuses = ['Pending', 'Approved', 'Rejected', 'Processed', 'Completed'];
        
        if (in_array($new_status, $valid_statuses) && $refund_id > 0) {
            $update_sql = "UPDATE refunds SET status = ?, admin_notes = ?, processed_date = CURRENT_TIMESTAMP WHERE refund_id = ?";
            $update_stmt = $conn->prepare($update_sql);
            
            if ($update_stmt) {
                $update_stmt->bind_param("ssi", $new_status, $admin_notes, $refund_id);
                
                if ($update_stmt->execute()) {
                    $_SESSION['success_message'] = "Refund #$refund_id status updated successfully.";
                    
                    // If approved, update the original order/booking status
                    if ($new_status === 'Approved') {
                        $get_refund_info = "SELECT refund_type, reference_id FROM refunds WHERE refund_id = ?";
                        $info_stmt = $conn->prepare($get_refund_info);
                        $info_stmt->bind_param("i", $refund_id);
                        $info_stmt->execute();
                        $info_result = $info_stmt->get_result();
                        
                        if ($refund_row = $info_result->fetch_assoc()) {
                            if ($refund_row['refund_type'] === 'order') {
                                $update_order = "UPDATE `order` SET status = 'Refunded' WHERE orderID = ?";
                                $order_stmt = $conn->prepare($update_order);
                                $order_stmt->bind_param("i", $refund_row['reference_id']);
                                $order_stmt->execute();
                            } elseif ($refund_row['refund_type'] === 'booking') {
                                $update_booking = "UPDATE bookings SET status = 'Refunded' WHERE booking_id = ?";
                                $booking_stmt = $conn->prepare($update_booking);
                                $booking_stmt->bind_param("i", $refund_row['reference_id']);
                                $booking_stmt->execute();
                            }
                        }
                    }
                } else {
                    $_SESSION['error_message'] = "Failed to update refund status.";
                }
                $update_stmt->close();
            }
        }
    }
    
    // Process new refund request
    if (isset($_POST['create_refund'])) {
        $refund_type = trim($_POST['refund_type']);
        $reference_id = intval($_POST['reference_id']);
        $customer_id = intval($_POST['customer_id']);
        $amount = floatval($_POST['amount']);
        $reason = trim($_POST['reason']);
        $customer_notes = trim($_POST['customer_notes']);
        
        $insert_sql = "INSERT INTO refunds (refund_type, reference_id, customer_id, amount, reason, customer_notes, status, request_date) VALUES (?, ?, ?, ?, ?, ?, 'Pending', CURRENT_TIMESTAMP)";
        $insert_stmt = $conn->prepare($insert_sql);
        
        if ($insert_stmt) {
            $insert_stmt->bind_param("siidss", $refund_type, $reference_id, $customer_id, $amount, $reason, $customer_notes);
            
            if ($insert_stmt->execute()) {
                $_SESSION['success_message'] = "Refund request created successfully.";
            } else {
                $_SESSION['error_message'] = "Failed to create refund request.";
            }
            $insert_stmt->close();
        }
    }
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$type_filter = isset($_GET['type']) ? trim($_GET['type']) : '';
$date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';

// Build query with filters
$where_conditions = ['1=1'];
$params = [];
$types = '';

if (!empty($status_filter)) {
    $where_conditions[] = "r.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if (!empty($type_filter)) {
    $where_conditions[] = "r.refund_type = ?";
    $params[] = $type_filter;
    $types .= 's';
}

if (!empty($date_from)) {
    $where_conditions[] = "DATE(r.request_date) >= ?";
    $params[] = $date_from;
    $types .= 's';
}

if (!empty($date_to)) {
    $where_conditions[] = "DATE(r.request_date) <= ?";
    $params[] = $date_to;
    $types .= 's';
}

$where_clause = implode(' AND ', $where_conditions);

// Fetch refunds with customer information
$sql = "SELECT r.*, 
        CASE 
            WHEN r.refund_type = 'order' THEN CONCAT('Order #', r.reference_id)
            WHEN r.refund_type = 'booking' THEN CONCAT('Booking #', r.reference_id)
            ELSE CONCAT(UPPER(r.refund_type), ' #', r.reference_id)
        END as reference_display,
        COALESCE(po.fullName, ps.name, 'Unknown Customer') as customer_name,
        COALESCE(po.email, ps.email, 'No email') as customer_email
        FROM refunds r
        LEFT JOIN petowner po ON r.customer_id = po.petOwnerID AND r.refund_type IN ('order', 'booking')
        LEFT JOIN petsitter ps ON r.customer_id = ps.petSitterID AND r.refund_type = 'service'
        WHERE $where_clause
        ORDER BY r.request_date DESC";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$refunds = $stmt->get_result();

// Get statistics
$stats_sql = "SELECT 
    COUNT(*) as total_refunds,
    SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending_count,
    SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) as approved_count,
    SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) as rejected_count,
    SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed_count,
    SUM(CASE WHEN status = 'Completed' THEN amount ELSE 0 END) as total_refunded_amount
    FROM refunds";

$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Refund Management - Pet Care & Sitting System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .status-pending { color: #ffc107; }
        .status-approved { color: #28a745; }
        .status-rejected { color: #dc3545; }
        .status-processed { color: #17a2b8; }
        .status-completed { color: #6f42c1; }
        
        .refund-card { 
            border-left: 4px solid #007bff; 
            transition: transform 0.2s;
        }
        .refund-card:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-action {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center py-3 mb-4 border-bottom">
            <h1 class="h3 mb-0"><i class="fas fa-undo-alt me-2"></i>Refund Management System</h1>
            <div>
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createRefundModal">
                    <i class="fas fa-plus"></i> Create Refund Request
                </button>
                <a href="dashboard.php" class="btn btn-outline-secondary btn-sm ms-2">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo $_SESSION['success_message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i><?php echo $_SESSION['error_message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-2">
                <div class="card stats-card text-center">
                    <div class="card-body">
                        <i class="fas fa-list-alt fa-2x mb-2"></i>
                        <h4 class="mb-0"><?php echo $stats['total_refunds']; ?></h4>
                        <small>Total Refunds</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card bg-warning text-dark text-center">
                    <div class="card-body">
                        <i class="fas fa-clock fa-2x mb-2"></i>
                        <h4 class="mb-0"><?php echo $stats['pending_count']; ?></h4>
                        <small>Pending</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card bg-success text-white text-center">
                    <div class="card-body">
                        <i class="fas fa-check fa-2x mb-2"></i>
                        <h4 class="mb-0"><?php echo $stats['approved_count']; ?></h4>
                        <small>Approved</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card bg-danger text-white text-center">
                    <div class="card-body">
                        <i class="fas fa-times fa-2x mb-2"></i>
                        <h4 class="mb-0"><?php echo $stats['rejected_count']; ?></h4>
                        <small>Rejected</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card bg-info text-white text-center">
                    <div class="card-body">
                        <i class="fas fa-money-bill fa-2x mb-2"></i>
                        <h4 class="mb-0"><?php echo $stats['completed_count']; ?></h4>
                        <small>Completed</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card bg-purple text-white text-center" style="background-color: #6f42c1 !important;">
                    <div class="card-body">
                        <i class="fas fa-dollar-sign fa-2x mb-2"></i>
                        <h4 class="mb-0">$<?php echo number_format($stats['total_refunded_amount'], 2); ?></h4>
                        <small>Total Refunded</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="">All Statuses</option>
                            <option value="Pending" <?php echo $status_filter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="Approved" <?php echo $status_filter === 'Approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="Rejected" <?php echo $status_filter === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                            <option value="Processed" <?php echo $status_filter === 'Processed' ? 'selected' : ''; ?>>Processed</option>
                            <option value="Completed" <?php echo $status_filter === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Type</label>
                        <select name="type" class="form-select">
                            <option value="">All Types</option>
                            <option value="order" <?php echo $type_filter === 'order' ? 'selected' : ''; ?>>Pet Store Orders</option>
                            <option value="booking" <?php echo $type_filter === 'booking' ? 'selected' : ''; ?>>Service Bookings</option>
                            <option value="service" <?php echo $type_filter === 'service' ? 'selected' : ''; ?>>Service Refunds</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">From Date</label>
                        <input type="date" name="date_from" class="form-control" value="<?php echo $date_from; ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">To Date</label>
                        <input type="date" name="date_to" class="form-control" value="<?php echo $date_to; ?>">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="fas fa-filter"></i> Filter
                        </button>
                        <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Refunds List -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-list me-2"></i>Refund Requests</h5>
            </div>
            <div class="card-body">
                <?php if ($refunds->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Refund ID</th>
                                    <th>Type</th>
                                    <th>Reference</th>
                                    <th>Customer</th>
                                    <th>Amount</th>
                                    <th>Reason</th>
                                    <th>Status</th>
                                    <th>Request Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($refund = $refunds->fetch_assoc()): ?>
                                    <tr>
                                        <td><strong>#<?php echo $refund['refund_id']; ?></strong></td>
                                        <td>
                                            <span class="badge bg-secondary">
                                                <?php echo ucfirst($refund['refund_type']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $refund['reference_display']; ?></td>
                                        <td>
                                            <div>
                                                <strong><?php echo htmlspecialchars($refund['customer_name']); ?></strong><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($refund['customer_email']); ?></small>
                                            </div>
                                        </td>
                                        <td><strong>$<?php echo number_format($refund['amount'], 2); ?></strong></td>
                                        <td>
                                            <span class="badge bg-light text-dark">
                                                <?php echo htmlspecialchars($refund['reason']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo match($refund['status']) {
                                                    'Pending' => 'warning',
                                                    'Approved' => 'success',
                                                    'Rejected' => 'danger',
                                                    'Processed' => 'info',
                                                    'Completed' => 'primary',
                                                    default => 'secondary'
                                                };
                                            ?>">
                                                <?php echo $refund['status']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M j, Y g:i A', strtotime($refund['request_date'])); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary btn-action" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#updateRefundModal"
                                                    data-refund-id="<?php echo $refund['refund_id']; ?>"
                                                    data-refund-status="<?php echo $refund['status']; ?>"
                                                    data-admin-notes="<?php echo htmlspecialchars($refund['admin_notes']); ?>"
                                                    data-customer-notes="<?php echo htmlspecialchars($refund['customer_notes']); ?>">
                                                <i class="fas fa-edit"></i> Update
                                            </button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No refund requests found</h5>
                        <p class="text-muted">Try adjusting your search filters or create a new refund request.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Create Refund Modal -->
    <div class="modal fade" id="createRefundModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Create New Refund Request</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <label class="form-label">Refund Type</label>
                                <select name="refund_type" class="form-select" required>
                                    <option value="">Select Type</option>
                                    <option value="order">Pet Store Order</option>
                                    <option value="booking">Service Booking</option>
                                    <option value="service">Service Refund</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Reference ID</label>
                                <input type="number" name="reference_id" class="form-control" placeholder="Order/Booking ID" required>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <label class="form-label">Customer ID</label>
                                <input type="number" name="customer_id" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Refund Amount</label>
                                <input type="number" name="amount" class="form-control" step="0.01" placeholder="0.00" required>
                            </div>
                        </div>
                        <div class="mt-3">
                            <label class="form-label">Reason</label>
                            <select name="reason" class="form-select" required>
                                <option value="">Select Reason</option>
                                <option value="Cancellation">Cancellation</option>
                                <option value="Product Issue">Product Issue</option>
                                <option value="Service Issue">Service Issue</option>
                                <option value="Billing Error">Billing Error</option>
                                <option value="Customer Request">Customer Request</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="mt-3">
                            <label class="form-label">Customer Notes</label>
                            <textarea name="customer_notes" class="form-control" rows="3" placeholder="Additional details..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="create_refund" class="btn btn-primary">
                            <i class="fas fa-save"></i> Create Refund Request
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Update Refund Modal -->
    <div class="modal fade" id="updateRefundModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Update Refund Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="refund_id" id="update_refund_id">
                        
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select name="refund_status" id="update_refund_status" class="form-select" required>
                                <option value="Pending">Pending</option>
                                <option value="Approved">Approved</option>
                                <option value="Rejected">Rejected</option>
                                <option value="Processed">Processed</option>
                                <option value="Completed">Completed</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Admin Notes</label>
                            <textarea name="admin_notes" id="update_admin_notes" class="form-control" rows="3" placeholder="Add notes about this refund..."></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Customer Notes (Read Only)</label>
                            <textarea id="view_customer_notes" class="form-control" rows="2" readonly></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_refund_status" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Status
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Handle update refund modal
        document.getElementById('updateRefundModal').addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const refundId = button.getAttribute('data-refund-id');
            const refundStatus = button.getAttribute('data-refund-status');
            const adminNotes = button.getAttribute('data-admin-notes');
            const customerNotes = button.getAttribute('data-customer-notes');
            
            document.getElementById('update_refund_id').value = refundId;
            document.getElementById('update_refund_status').value = refundStatus;
            document.getElementById('update_admin_notes').value = adminNotes;
            document.getElementById('view_customer_notes').value = customerNotes;
        });
    </script>
</body>
</html>

<?php
$conn->close();
?>