<?php
// Enhanced Admin Refund Management System - User-Friendly Version with Sri Lankan Rupees
// File: admin/refund_management.php

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

include_once '../includes/header.php';

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
                    $message = "🎉 Refund #$refund_id updated successfully to '$new_status' status!";
                } else {
                    $error = "❌ Failed to update refund status: " . $update_stmt->error;
                }
                $update_stmt->close();
            } else {
                $error = "⚠️ Failed to prepare update statement: " . $conn->error;
            }
        } else {
            $error = "❌ Invalid refund data provided.";
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
                $message = "🎉 Successfully updated $updated_count refund(s) to '$bulk_action' status.";
            } else {
                $error = "❌ Failed to update any refunds.";
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
    $error = "⚠️ Error fetching refunds: " . $conn->error;
}

// Get summary statistics
$stats_sql = "SELECT 
    COUNT(*) as total_refunds,
    SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending_count,
    SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) as approved_count,
    SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) as rejected_count,
    SUM(CASE WHEN status = 'Processed' THEN 1 ELSE 0 END) as processed_count,
    SUM(CASE WHEN status = 'Approved' OR status = 'Processed' THEN amount ELSE 0 END) as total_approved_amount,
    SUM(CASE WHEN status = 'Pending' THEN amount ELSE 0 END) as pending_amount
    FROM refunds";
$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();
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
        :root {
            --primary-color: #28a745;
            --secondary-color: #20c997;
            --accent-color: #17a2b8;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --success-color: #28a745;
            --dark-color: #343a40;
        }

        body { 
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
        }

        .header-section {
            background: linear-gradient(135deg, var(--dark-color) 0%, #495057 100%);
            color: white; 
            padding: 2rem 0; 
            margin-bottom: 2rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }

        .header-section h1 {
            font-weight: 700;
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }

        .header-section p {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .stats-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: none;
            overflow: hidden;
        }

        .stats-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
        }

        .stats-card .card-body {
            padding: 1.5rem;
        }

        .stats-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }

        .stats-pending { background: rgba(255, 193, 7, 0.1); color: var(--warning-color); }
        .stats-approved { background: rgba(40, 167, 69, 0.1); color: var(--success-color); }
        .stats-rejected { background: rgba(220, 53, 69, 0.1); color: var(--danger-color); }
        .stats-processed { background: rgba(23, 162, 184, 0.1); color: var(--accent-color); }

        .main-card {
            background: white; 
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1); 
            overflow: hidden;
            border: none;
        }

        .main-card .card-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 1.5rem 2rem;
            border: none;
        }

        .status-badge {
            font-size: 0.8rem; 
            padding: 0.4rem 0.8rem; 
            border-radius: 20px; 
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-pending { background: #fff3cd; color: #856404; }
        .status-approved { background: #d1ecf1; color: #0c5460; }
        .status-rejected { background: #f8d7da; color: #721c24; }
        .status-processed { background: #d4edda; color: #155724; }

        .action-btn {
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }

        .table {
            margin-bottom: 0;
        }

        .table th {
            background: #f8f9fa;
            border: none;
            font-weight: 600;
            color: var(--dark-color);
            padding: 1rem 0.75rem;
        }

        .table td {
            padding: 1rem 0.75rem;
            border: none;
            border-bottom: 1px solid #e9ecef;
            vertical-align: middle;
        }

        .table tbody tr:hover {
            background: #f8f9fa;
            transform: scale(1.001);
            transition: all 0.2s ease;
        }

        .alert {
            border: none;
            border-radius: 12px;
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            border-left: 4px solid;
        }

        .alert-success {
            background: #d4edda;
            border-left-color: var(--success-color);
            color: #155724;
        }

        .alert-danger {
            background: #f8d7da;
            border-left-color: var(--danger-color);
            color: #721c24;
        }

        .btn {
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            border: none;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
        }

        .bulk-actions {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        .modal-content {
            border-radius: 15px;
            border: none;
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }

        .modal-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            border: none;
            border-radius: 15px 15px 0 0;
        }

        .currency-highlight {
            color: var(--success-color);
            font-weight: 700;
        }

        @media (max-width: 768px) {
            .header-section h1 { font-size: 2rem; }
            .table-responsive { border-radius: 0; }
            .stats-card { margin-bottom: 1rem; }
        }

        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.9);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }

        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 4px solid #e9ecef;
            border-top: 4px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="text-center">
            <div class="loading-spinner"></div>
            <p class="mt-3">Processing refunds...</p>
        </div>
    </div>

    <!-- Header Section -->
    <!-- <div class="header-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1><i class="fas fa-cogs me-3"></i>Refund Management</h1>
                    <p class="mb-0">Manage customer refund requests efficiently and professionally</p>
                </div>
                <div class="col-md-4 text-md-end">
                    <div class="d-flex align-items-center justify-content-md-end">
                        <div class="me-3">
                            <i class="fas fa-chart-line fa-2x opacity-75"></i>
                        </div>
                        <div>
                            <small class="opacity-75">Total Approved Amount</small>
                            <div class="fw-bold currency-highlight">Rs.<?php echo number_format($stats['total_approved_amount'] ?? 0, 2); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div> -->
    

    <div class="container-fluid">
        <!-- Success/Error Messages -->
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stats-card">
                    <div class="card-body text-center">
                        <div class="stats-icon stats-pending mx-auto">
                            <i class="fas fa-clock"></i>
                        </div>
                        <h3 class="mb-1"><?php echo $stats['pending_count'] ?? 0; ?></h3>
                        <p class="text-muted mb-1">Pending Requests</p>
                        <small class="text-warning fw-bold">Rs.<?php echo number_format($stats['pending_amount'] ?? 0, 2); ?></small>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stats-card">
                    <div class="card-body text-center">
                        <div class="stats-icon stats-approved mx-auto">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h3 class="mb-1"><?php echo $stats['approved_count'] ?? 0; ?></h3>
                        <p class="text-muted mb-0">Approved</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stats-card">
                    <div class="card-body text-center">
                        <div class="stats-icon stats-rejected mx-auto">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <h3 class="mb-1"><?php echo $stats['rejected_count'] ?? 0; ?></h3>
                        <p class="text-muted mb-0">Rejected</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stats-card">
                    <div class="card-body text-center">
                        <div class="stats-icon stats-processed mx-auto">
                            <i class="fas fa-check-double"></i>
                        </div>
                        <h3 class="mb-1"><?php echo $stats['processed_count'] ?? 0; ?></h3>
                        <p class="text-muted mb-0">Processed</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="row">
            <div class="col-12">
                <div class="main-card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="fas fa-list me-2"></i>Refund Requests
                            </h5>
                            <div>
                                <span class="badge bg-light text-dark">
                                    Total: <?php echo $stats['total_refunds'] ?? 0; ?> requests
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <form method="POST" id="bulkForm">
                        <input type="hidden" name="bulk_action" id="bulkAction">
                        
                        <!-- Bulk Actions -->
                        <?php if ($refunds_result && $refunds_result->num_rows > 0): ?>
                            <div class="bulk-actions">
                                <div class="row align-items-center">
                                    <div class="col-md-6">
                                        <div class="d-flex align-items-center">
                                            <input type="checkbox" id="selectAll" class="form-check-input me-2">
                                            <label for="selectAll" class="form-check-label me-3">Select All</label>
                                            <span class="text-muted">Selected: <span id="selectedCount">0</span></span>
                                        </div>
                                    </div>
                                    <div class="col-md-6 text-md-end">
                                        <button type="button" class="btn btn-success btn-sm me-2" onclick="bulkAction('Approved')">
                                            <i class="fas fa-check me-1"></i>Approve Selected
                                        </button>
                                        <button type="button" class="btn btn-danger btn-sm" onclick="bulkAction('Rejected')">
                                            <i class="fas fa-times me-1"></i>Reject Selected
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="table-responsive">
                            <?php if ($refunds_result && $refunds_result->num_rows > 0): ?>
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th width="40">
                                                <i class="fas fa-check-square text-muted"></i>
                                            </th>
                                            <th>Refund ID</th>
                                            <th>Customer</th>
                                            <th>Type</th>
                                            <th>Reference</th>
                                            <th>Amount (Rs.)</th>
                                            <th>Reason</th>
                                            <th>Status</th>
                                            <th>Requested</th>
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
                                                <td>
                                                    <strong class="text-primary">#<?php echo $refund['refund_id']; ?></strong>
                                                </td>
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
                                                <td>
                                                    <span class="text-primary">#<?php echo $refund['reference_id']; ?></span>
                                                </td>
                                                <td>
                                                    <strong class="currency-highlight">Rs.<?php echo number_format($refund['amount'], 2); ?></strong>
                                                </td>
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
                                                    <br><small class="text-muted"><?php echo date('g:i A', strtotime($refund['request_date'])); ?></small>
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
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">No refund requests found</h5>
                                    <p class="text-muted">Refund requests will appear here when customers submit them.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Update Modal -->
    <div class="modal fade" id="updateModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-edit me-2"></i>Update Refund Status
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="refund_id" id="modalRefundId">
                        
                        <!-- Refund Details -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="card bg-light">
                                    <div class="card-body p-3">
                                        <h6 class="card-title"><i class="fas fa-user me-2"></i>Customer Details</h6>
                                        <p class="mb-1"><strong>Name:</strong> <span id="modalCustomerName"></span></p>
                                        <p class="mb-1"><strong>Type:</strong> <span id="modalRefundType"></span></p>
                                        <p class="mb-0"><strong>Amount:</strong> <span class="currency-highlight">Rs.<span id="modalAmount"></span></span></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card bg-light">
                                    <div class="card-body p-3">
                                        <h6 class="card-title"><i class="fas fa-info-circle me-2"></i>Request Details</h6>
                                        <p class="mb-1"><strong>Reason:</strong> <span id="modalReason"></span></p>
                                        <p class="mb-0"><strong>Customer Notes:</strong></p>
                                        <small class="text-muted" id="modalCustomerNotes"></small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Status Update -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-flag me-2"></i>Update Status
                                    </label>
                                    <select name="refund_status" id="modalRefundStatus" class="form-select" required>
                                        <option value="Pending">🕐 Pending Review</option>
                                        <option value="Approved">✅ Approved</option>
                                        <option value="Rejected">❌ Rejected</option>
                                        <option value="Processed">🎉 Processed & Completed</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-sticky-note me-2"></i>Admin Notes
                                    </label>
                                    <textarea name="admin_notes" id="modalAdminNotes" class="form-control" rows="3" 
                                              placeholder="Add notes about this refund decision..."></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Cancel
                        </button>
                        <button type="submit" name="update_refund_status" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Update Status
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Modal data population
        document.addEventListener('DOMContentLoaded', function() {
            const updateModal = document.getElementById('updateModal');
            const loadingOverlay = document.getElementById('loadingOverlay');
            
            if (updateModal) {
                updateModal.addEventListener('show.bs.modal', function (event) {
                    const button = event.relatedTarget;
                    
                    // Populate modal fields
                    document.getElementById('modalRefundId').value = button.getAttribute('data-refund-id');
                    document.getElementById('modalRefundStatus').value = button.getAttribute('data-refund-status');
                    document.getElementById('modalAdminNotes').value = button.getAttribute('data-admin-notes');
                    document.getElementById('modalCustomerName').textContent = button.getAttribute('data-customer-name');
                    document.getElementById('modalRefundType').textContent = button.getAttribute('data-refund-type');
                    document.getElementById('modalAmount').textContent = parseFloat(button.getAttribute('data-amount')).toFixed(2);
                    document.getElementById('modalReason').textContent = button.getAttribute('data-reason');
                    document.getElementById('modalCustomerNotes').textContent = button.getAttribute('data-customer-notes');
                });
            }
            
            // Bulk selection functionality
            const selectAllCheckbox = document.getElementById('selectAll');
            const refundCheckboxes = document.querySelectorAll('.refund-checkbox');
            const selectedCountSpan = document.getElementById('selectedCount');
            
            function updateSelectedCount() {
                const checkedBoxes = document.querySelectorAll('.refund-checkbox:checked');
                if (selectedCountSpan) {
                    selectedCountSpan.textContent = checkedBoxes.length;
                }
            }
            
            if (selectAllCheckbox) {
                selectAllCheckbox.addEventListener('change', function() {
                    refundCheckboxes.forEach(checkbox => {
                        checkbox.checked = this.checked;
                    });
                    updateSelectedCount();
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
                    updateSelectedCount();
                });
            });

            // Form submission loading
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function() {
                    loadingOverlay.style.display = 'flex';
                });
            });
            
            // Initialize count
            updateSelectedCount();
        });
        
        // Bulk actions with confirmation
        function bulkAction(action) {
            const checkedBoxes = document.querySelectorAll('.refund-checkbox:checked');
            
            if (checkedBoxes.length === 0) {
                alert('⚠️ Please select at least one refund to perform bulk action.');
                return;
            }
            
            const actionText = action === 'Approved' ? 'approve' : 'reject';
            const confirmMessage = `🤔 Are you sure you want to ${actionText} ${checkedBoxes.length} selected refund(s)?`;
            
            if (confirm(confirmMessage)) {
                document.getElementById('bulkAction').value = action;
                document.getElementById('loadingOverlay').style.display = 'flex';
                document.getElementById('bulkForm').submit();
            }
        }
        
        console.log('✅ Enhanced Refund Management System loaded successfully');
    </script>
</body>
</html>

<?php
if (isset($conn)) {
    $conn->close();
}
?>