<?php
// Minimal working version of refund management
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
session_start();

// Simple authentication check
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    echo "<!DOCTYPE html>
    <html>
    <head><title>Access Denied</title></head>
    <body>
        <h2>Access Denied</h2>
        <p>You must be logged in as an administrator to access this page.</p>
        <a href='../login.php'>Login</a>
    </body>
    </html>";
    exit();
}

// Simple database connection
try {
    require_once '../config/db_connect.php';
    
    if (!isset($conn) || !$conn instanceof mysqli) {
        throw new Exception("Database connection failed");
    }
    
} catch (Exception $e) {
    echo "<!DOCTYPE html>
    <html>
    <head><title>Database Error</title></head>
    <body>
        <h2>Database Connection Error</h2>
        <p>Error: " . $e->getMessage() . "</p>
        <a href='dashboard.php'>Back to Dashboard</a>
    </body>
    </html>";
    exit();
}

// Handle form submissions
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_refund_status'])) {
        $refund_id = intval($_POST['refund_id']);
        $new_status = trim($_POST['refund_status']);
        $admin_notes = trim($_POST['admin_notes']);
        
        $valid_statuses = ['Pending', 'Approved', 'Rejected', 'Processed', 'Completed'];
        
        if (in_array($new_status, $valid_statuses) && $refund_id > 0) {
            $update_sql = "UPDATE refunds SET status = ?, admin_notes = ?, processed_date = CURRENT_TIMESTAMP WHERE refund_id = ?";
            $update_stmt = $conn->prepare($update_sql);
            
            if ($update_stmt && $update_stmt->bind_param("ssi", $new_status, $admin_notes, $refund_id) && $update_stmt->execute()) {
                $message = "Refund #$refund_id updated successfully!";
            } else {
                $error = "Failed to update refund status.";
            }
        } else {
            $error = "Invalid refund data.";
        }
    }
}

// Get refunds data
$refunds_sql = "SELECT * FROM refunds ORDER BY request_date DESC";
$refunds_result = $conn->query($refunds_sql);

if (!$refunds_result) {
    $error = "Error fetching refunds: " . $conn->error;
}

// Get basic statistics
$stats = ['total_refunds' => 0, 'pending_count' => 0, 'approved_count' => 0];
$stats_sql = "SELECT 
    COUNT(*) as total_refunds,
    SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending_count,
    SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) as approved_count
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
    <title>Refund Management - Pet Care System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .status-pending { color: #ffc107; }
        .status-approved { color: #28a745; }
        .status-rejected { color: #dc3545; }
        .status-processed { color: #17a2b8; }
        .status-completed { color: #6f42c1; }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid">
        <!-- Header -->
        <div class="bg-primary text-white p-4 mb-4">
            <div class="d-flex justify-content-between align-items-center">
                <h1 class="h3 mb-0"><i class="fas fa-undo-alt me-2"></i>Refund Management System</h1>
                <a href="dashboard.php" class="btn btn-light btn-sm">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>

        <!-- Messages -->
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card bg-primary text-white">
                    <div class="card-body text-center">
                        <h4><?php echo $stats['total_refunds']; ?></h4>
                        <small>Total Refunds</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-warning text-dark">
                    <div class="card-body text-center">
                        <h4><?php echo $stats['pending_count']; ?></h4>
                        <small>Pending</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-success text-white">
                    <div class="card-body text-center">
                        <h4><?php echo $stats['approved_count']; ?></h4>
                        <small>Approved</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Refunds Table -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-list me-2"></i>Refund Requests</h5>
            </div>
            <div class="card-body">
                <?php if ($refunds_result && $refunds_result->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead class="table-dark">
                                <tr>
                                    <th>ID</th>
                                    <th>Type</th>
                                    <th>Reference</th>
                                    <th>Customer</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($refund = $refunds_result->fetch_assoc()): ?>
                                    <tr>
                                        <td><strong>#<?php echo $refund['refund_id']; ?></strong></td>
                                        <td>
                                            <span class="badge bg-secondary">
                                                <?php echo ucfirst($refund['refund_type']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $refund['reference_id']; ?></td>
                                        <td><?php echo $refund['customer_id']; ?></td>
                                        <td><strong>$<?php echo number_format($refund['amount'], 2); ?></strong></td>
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
                                        <td><?php echo date('M j, Y', strtotime($refund['request_date'])); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#updateModal"
                                                    data-refund-id="<?php echo $refund['refund_id']; ?>"
                                                    data-refund-status="<?php echo $refund['status']; ?>"
                                                    data-admin-notes="<?php echo htmlspecialchars($refund['admin_notes'] ?? ''); ?>">
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
                        <p class="text-muted">Refund requests will appear here when customers submit them.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Update Modal -->
    <div class="modal fade" id="updateModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Update Refund Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="refund_id" id="refundId">
                        
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select name="refund_status" id="refundStatus" class="form-select" required>
                                <option value="Pending">Pending</option>
                                <option value="Approved">Approved</option>
                                <option value="Rejected">Rejected</option>
                                <option value="Processed">Processed</option>
                                <option value="Completed">Completed</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Admin Notes</label>
                            <textarea name="admin_notes" id="adminNotes" class="form-control" rows="3"></textarea>
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
                    const refundId = button.getAttribute('data-refund-id');
                    const refundStatus = button.getAttribute('data-refund-status');
                    const adminNotes = button.getAttribute('data-admin-notes');
                    
                    document.getElementById('refundId').value = refundId || '';
                    document.getElementById('refundStatus').value = refundStatus || '';
                    document.getElementById('adminNotes').value = adminNotes || '';
                });
            }
            
            console.log('✅ Refund management loaded successfully');
        });
    </script>
</body>
</html>

<?php
if (isset($conn)) {
    $conn->close();
}
?>