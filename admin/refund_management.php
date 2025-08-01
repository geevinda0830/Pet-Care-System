<?php
// Admin Refund Management System
// File: admin/refund_management.php

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
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

// Initialize variables
$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_refund_status'])) {
        $refund_id = intval($_POST['refund_id']);
        $new_status = trim($_POST['refund_status']);
        $admin_notes = trim($_POST['admin_notes']);
        
        $valid_statuses = ['Pending', 'Approved', 'Rejected', 'Processed'];
        
        if (in_array($new_status, $valid_statuses) && $refund_id > 0) {
            $update_sql = "UPDATE refunds SET status = ?, admin_notes = ?, processed_date = CURRENT_TIMESTAMP WHERE refund_id = ?";
            $update_stmt = $conn->prepare($update_sql);
            
            if ($update_stmt && $update_stmt->bind_param("ssi", $new_status, $admin_notes, $refund_id)) {
                if ($update_stmt->execute()) {
                    $message = "Refund #$refund_id updated successfully to '$new_status' status!";
                } else {
                    $error = "Failed to update refund status: " . $update_stmt->error;
                }
                $update_stmt->close();
            } else {
                $error = "Failed to prepare update statement: " . $conn->error;
            }
        } else {
            $error = "Invalid refund data provided.";
        }
    }
    
    // Handle bulk actions
    if (isset($_POST['bulk_action']) && isset($_POST['selected_refunds'])) {
        $bulk_action = $_POST['bulk_action'];
        $selected_refunds = $_POST['selected_refunds'];
        
        if (in_array($bulk_action, ['Approved', 'Rejected']) && !empty($selected_refunds)) {
            $updated_count = 0;
            foreach ($selected_refunds as $refund_id) {
                $refund_id = intval($refund_id);
                if ($refund_id > 0) {
                    $bulk_sql = "UPDATE refunds SET status = ?, processed_date = CURRENT_TIMESTAMP WHERE refund_id = ?";
                    $bulk_stmt = $conn->prepare($bulk_sql);
                    if ($bulk_stmt && $bulk_stmt->bind_param("si", $bulk_action, $refund_id)) {
                        if ($bulk_stmt->execute()) {
                            $updated_count++;
                        }
                        $bulk_stmt->close();
                    }
                }
            }
            if ($updated_count > 0) {
                $message = "Successfully updated $updated_count refund(s) to '$bulk_action' status.";
            } else {
                $error = "Failed to update any refunds.";
            }
        }
    }
}

// Get refunds data with customer information
$refunds_sql = "SELECT r.*, po.fullName as customer_name, po.email as customer_email 
                FROM refunds r 
                LEFT JOIN pet_owner po ON r.customer_id = po.userID 
                ORDER BY r.request_date DESC";
$refunds_result = $conn->query($refunds_sql);

if (!$refunds_result) {
    $error = "Error fetching refunds: " . $conn->error;
}

// Get statistics
$stats = ['total_refunds' => 0, 'pending_count' => 0, 'approved_count' => 0, 'rejected_count' => 0, 'processed_count' => 0, 'total_amount' => 0];
$stats_sql = "SELECT 
    COUNT(*) as total_refunds,
    SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending_count,
    SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) as approved_count,
    SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) as rejected_count,
    SUM(CASE WHEN status = 'Processed' THEN 1 ELSE 0 END) as processed_count,
    SUM(CASE WHEN status IN ('Approved', 'Processed') THEN amount ELSE 0 END) as total_amount
    FROM refunds";
$stats_result = $conn->query($stats_sql);
if ($stats_result) {
    $stats = $stats_result->fetch_assoc();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Refund Management - Pet Care Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .header-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            border-left: 4px solid #667eea;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s;
        }
        .stats-card:hover {
            transform: translateY(-2px);
        }
        .status-badge {
            font-size: 0.875rem;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 500;
        }
        .status-pending { background-color: #fff3cd; color: #856404; }
        .status-approved { background-color: #d1e7dd; color: #0f5132; }
        .status-rejected { background-color: #f8d7da; color: #721c24; }
        .status-processed { background-color: #cff4fc; color: #055160; }
        .main-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .table th {
            background-color: #f8f9fa;
            border: none;
            font-weight: 600;
            color: #495057;
        }
        .action-btn {
            padding: 0.375rem 0.75rem;
            border-radius: 8px;
            border: none;
            font-size: 0.875rem;
            transition: all 0.2s;
        }
        .btn-outline-primary:hover {
            transform: translateY(-1px);
        }
    </style>
</head>
<body>
    <!-- Header Section -->
    <div class="header-section">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-2"><i class="fas fa-undo-alt me-3"></i>Refund Management System</h1>
                    <p class="mb-0 opacity-75">Manage customer refund requests and process payments</p>
                </div>
                <a href="dashboard.php" class="btn btn-light">
                    <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                </a>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Messages -->
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-card text-center">
                    <h3 class="text-primary mb-1"><?php echo $stats['total_refunds']; ?></h3>
                    <small class="text-muted">Total Refunds</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card text-center">
                    <h3 class="text-warning mb-1"><?php echo $stats['pending_count']; ?></h3>
                    <small class="text-muted">Pending Review</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card text-center">
                    <h3 class="text-success mb-1"><?php echo $stats['approved_count']; ?></h3>
                    <small class="text-muted">Approved</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card text-center">
                    <h3 class="text-info mb-1">$<?php echo number_format($stats['total_amount'], 2); ?></h3>
                    <small class="text-muted">Total Refunded</small>
                </div>
            </div>
        </div>

        <!-- Refunds Table -->
        <div class="main-card">
            <div class="card-header bg-white py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-list me-2"></i>Refund Requests</h5>
                    <div>
                        <button type="button" class="btn btn-outline-success btn-sm me-2" onclick="bulkAction('Approved')">
                            <i class="fas fa-check me-1"></i>Bulk Approve
                        </button>
                        <button type="button" class="btn btn-outline-danger btn-sm" onclick="bulkAction('Rejected')">
                            <i class="fas fa-times me-1"></i>Bulk Reject
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if ($refunds_result && $refunds_result->num_rows > 0): ?>
                    <form id="bulkForm" method="POST">
                        <input type="hidden" name="bulk_action" id="bulkAction">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th width="5%">
                                            <input type="checkbox" id="selectAll" class="form-check-input">
                                        </th>
                                        <th>ID</th>
                                        <th>Customer</th>
                                        <th>Type</th>
                                        <th>Reference</th>
                                        <th>Amount</th>
                                        <th>Reason</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($refund = $refunds_result->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <input type="checkbox" name="selected_refunds[]" 
                                                       value="<?php echo $refund['refund_id']; ?>" 
                                                       class="form-check-input refund-checkbox">
                                            </td>
                                            <td><strong>#<?php echo $refund['refund_id']; ?></strong></td>
                                            <td>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($refund['customer_name'] ?? 'Unknown'); ?></strong>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($refund['customer_email'] ?? 'No email'); ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary">
                                                    <?php echo ucfirst($refund['refund_type']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo $refund['reference_id']; ?></td>
                                            <td><strong>$<?php echo number_format($refund['amount'], 2); ?></strong></td>
                                            <td>
                                                <span title="<?php echo htmlspecialchars($refund['customer_notes']); ?>">
                                                    <?php echo htmlspecialchars(substr($refund['reason'], 0, 30) . (strlen($refund['reason']) > 30 ? '...' : '')); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="status-badge status-<?php echo strtolower($refund['status']); ?>">
                                                    <?php echo $refund['status']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <small><?php echo date('M j, Y', strtotime($refund['request_date'])); ?></small>
                                            </td>
                                            <td>
                                                <button type="button" class="action-btn btn btn-outline-primary btn-sm" 
                                                        data-bs-toggle="modal" data-bs-target="#updateModal"
                                                        data-refund-id="<?php echo $refund['refund_id']; ?>"
                                                        data-refund-status="<?php echo $refund['status']; ?>"
                                                        data-admin-notes="<?php echo htmlspecialchars($refund['admin_notes'] ?? ''); ?>"
                                                        data-customer-name="<?php echo htmlspecialchars($refund['customer_name'] ?? 'Unknown'); ?>"
                                                        data-refund-type="<?php echo $refund['refund_type']; ?>"
                                                        data-amount="<?php echo $refund['amount']; ?>"
                                                        data-reason="<?php echo htmlspecialchars($refund['reason']); ?>"
                                                        data-customer-notes="<?php echo htmlspecialchars($refund['customer_notes'] ?? ''); ?>">
                                                    <i class="fas fa-edit"></i> Update
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No refund requests found</h5>
                        <p class="text-muted">Refund requests will appear here when customers submit them.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Update Modal -->
    <div class="modal fade" id="updateModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Update Refund Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="refund_id" id="modalRefundId">
                        
                        <!-- Refund Details -->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="card bg-light">
                                    <div class="card-body p-3">
                                        <h6 class="card-title">Refund Details</h6>
                                        <p class="mb-1"><strong>Customer:</strong> <span id="modalCustomerName"></span></p>
                                        <p class="mb-1"><strong>Type:</strong> <span id="modalRefundType"></span></p>
                                        <p class="mb-1"><strong>Amount:</strong> $<span id="modalAmount"></span></p>
                                        <p class="mb-0"><strong>Reason:</strong> <span id="modalReason"></span></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card bg-light">
                                    <div class="card-body p-3">
                                        <h6 class="card-title">Customer Notes</h6>
                                        <p id="modalCustomerNotes" class="mb-0 small"></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Status Update -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Status</label>
                                    <select name="refund_status" id="modalRefundStatus" class="form-select" required>
                                        <option value="Pending">Pending</option>
                                        <option value="Approved">Approved</option>
                                        <option value="Rejected">Rejected</option>
                                        <option value="Processed">Processed</option>
                                        <option value="Completed">Completed</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Admin Notes</label>
                                    <textarea name="admin_notes" id="modalAdminNotes" class="form-control" rows="3" 
                                              placeholder="Add notes about this refund decision..."></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_refund_status" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Update Status
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Modal data population
        document.addEventListener('DOMContentLoaded', function() {
            const updateModal = document.getElementById('updateModal');
            
            if (updateModal) {
                updateModal.addEventListener('show.bs.modal', function (event) {
                    const button = event.relatedTarget;
                    
                    // Populate modal fields
                    document.getElementById('modalRefundId').value = button.getAttribute('data-refund-id');
                    document.getElementById('modalRefundStatus').value = button.getAttribute('data-refund-status');
                    document.getElementById('modalAdminNotes').value = button.getAttribute('data-admin-notes');
                    document.getElementById('modalCustomerName').textContent = button.getAttribute('data-customer-name');
                    document.getElementById('modalRefundType').textContent = button.getAttribute('data-refund-type');
                    document.getElementById('modalAmount').textContent = button.getAttribute('data-amount');
                    document.getElementById('modalReason').textContent = button.getAttribute('data-reason');
                    document.getElementById('modalCustomerNotes').textContent = button.getAttribute('data-customer-notes');
                });
            }
            
            // Bulk selection
            const selectAllCheckbox = document.getElementById('selectAll');
            const refundCheckboxes = document.querySelectorAll('.refund-checkbox');
            
            if (selectAllCheckbox) {
                selectAllCheckbox.addEventListener('change', function() {
                    refundCheckboxes.forEach(checkbox => {
                        checkbox.checked = this.checked;
                    });
                });
            }
            
            refundCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    const allChecked = Array.from(refundCheckboxes).every(cb => cb.checked);
                    const someChecked = Array.from(refundCheckboxes).some(cb => cb.checked);
                    
                    if (selectAllCheckbox) {
                        selectAllCheckbox.checked = allChecked;
                        selectAllCheckbox.indeterminate = someChecked && !allChecked;
                    }
                });
            });
        });
        
        // Bulk actions
        function bulkAction(action) {
            const checkedBoxes = document.querySelectorAll('.refund-checkbox:checked');
            
            if (checkedBoxes.length === 0) {
                alert('Please select at least one refund to perform bulk action.');
                return;
            }
            
            if (confirm(`Are you sure you want to ${action.toLowerCase()} ${checkedBoxes.length} selected refund(s)?`)) {
                document.getElementById('bulkAction').value = action;
                document.getElementById('bulkForm').submit();
            }
        }
        
        console.log('✅ Refund Management System loaded successfully');
    </script>
</body>
</html>

<?php
if (isset($conn)) {
    $conn->close();
}
?>