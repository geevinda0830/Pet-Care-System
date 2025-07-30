<?php
// customer_refund_request.php
session_start();

// Check if customer is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'petowner') {
    $_SESSION['error_message'] = "Please log in to request a refund.";
    header("Location: login.php");
    exit();
}

require_once 'config/db_connect.php';

$customer_id = $_SESSION['user_id'];

// Handle refund request submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_refund_request'])) {
    $refund_type = trim($_POST['refund_type']);
    $reference_id = intval($_POST['reference_id']);
    $amount = floatval($_POST['amount']);
    $reason = trim($_POST['reason']);
    $customer_notes = trim($_POST['customer_notes']);
    
    // Validate the reference exists and belongs to the customer
    $validation_passed = false;
    
    if ($refund_type === 'order') {
        $check_sql = "SELECT orderID, total FROM `order` WHERE orderID = ? AND petOwnerID = ? AND status IN ('Delivered', 'Processing', 'Shipped')";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ii", $reference_id, $customer_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        if ($order = $result->fetch_assoc()) {
            $validation_passed = true;
            $max_amount = $order['total'];
        }
    } elseif ($refund_type === 'booking') {
        // Adjust table name and fields based on your booking table structure
        $check_sql = "SELECT booking_id, amount FROM bookings WHERE booking_id = ? AND customer_id = ? AND status IN ('Confirmed', 'Completed')";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ii", $reference_id, $customer_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        if ($booking = $result->fetch_assoc()) {
            $validation_passed = true;
            $max_amount = $booking['amount'];
        }
    }
    
    if ($validation_passed && $amount <= $max_amount && $amount > 0) {
        // Check if refund request already exists
        $existing_sql = "SELECT refund_id FROM refunds WHERE refund_type = ? AND reference_id = ? AND customer_id = ? AND status NOT IN ('Rejected', 'Completed')";
        $existing_stmt = $conn->prepare($existing_sql);
        $existing_stmt->bind_param("sii", $refund_type, $reference_id, $customer_id);
        $existing_stmt->execute();
        
        if ($existing_stmt->get_result()->num_rows === 0) {
            // Insert refund request
            $insert_sql = "INSERT INTO refunds (refund_type, reference_id, customer_id, amount, reason, customer_notes, status, request_date) VALUES (?, ?, ?, ?, ?, ?, 'Pending', CURRENT_TIMESTAMP)";
            $insert_stmt = $conn->prepare($insert_sql);
            
            if ($insert_stmt) {
                $insert_stmt->bind_param("siidss", $refund_type, $reference_id, $customer_id, $amount, $reason, $customer_notes);
                
                if ($insert_stmt->execute()) {
                    $refund_id = $conn->insert_id;
                    
                    // Log the refund request
                    $log_sql = "INSERT INTO refund_audit_log (refund_id, action, new_status, notes, ip_address, created_at) VALUES (?, 'created', 'Pending', 'Customer submitted refund request', ?, CURRENT_TIMESTAMP)";
                    $log_stmt = $conn->prepare($log_sql);
                    $ip_address = $_SERVER['REMOTE_ADDR'];
                    $log_stmt->bind_param("is", $refund_id, $ip_address);
                    $log_stmt->execute();
                    
                    $_SESSION['success_message'] = "Your refund request #$refund_id has been submitted successfully. We will review it within 2-3 business days.";
                } else {
                    $_SESSION['error_message'] = "Failed to submit refund request. Please try again.";
                }
                $insert_stmt->close();
            }
        } else {
            $_SESSION['error_message'] = "A refund request for this order/booking already exists.";
        }
    } else {
        $_SESSION['error_message'] = "Invalid refund request. Please check the details and try again.";
    }
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Get customer's orders for dropdown
$orders_sql = "SELECT orderID, total, date, status FROM `order` WHERE petOwnerID = ? AND status IN ('Delivered', 'Processing', 'Shipped') ORDER BY date DESC";
$orders_stmt = $conn->prepare($orders_sql);
$orders_stmt->bind_param("i", $customer_id);
$orders_stmt->execute();
$orders = $orders_stmt->get_result();

// Get customer's bookings (adjust based on your booking table structure)
$bookings_sql = "SELECT booking_id, amount, booking_date, status FROM bookings WHERE customer_id = ? AND status IN ('Confirmed', 'Completed') ORDER BY booking_date DESC";
$bookings_stmt = $conn->prepare($bookings_sql);
$bookings_stmt->bind_param("i", $customer_id);
$bookings_stmt->execute();
$bookings = $bookings_stmt->get_result();

// Get customer's refund history
$refunds_sql = "SELECT r.*, 
        CASE 
            WHEN r.refund_type = 'order' THEN CONCAT('Order #', r.reference_id)
            WHEN r.refund_type = 'booking' THEN CONCAT('Booking #', r.reference_id)
            ELSE CONCAT(UPPER(r.refund_type), ' #', r.reference_id)
        END as reference_display
        FROM refunds r 
        WHERE r.customer_id = ? 
        ORDER BY r.request_date DESC";
$refunds_stmt = $conn->prepare($refunds_sql);
$refunds_stmt->bind_param("i", $customer_id);
$refunds_stmt->execute();
$refunds = $refunds_stmt->get_result();

// Get refund policies
$policies_sql = "SELECT * FROM refund_policies WHERE is_active = 1 ORDER BY service_type, policy_name";
$policies = $conn->query($policies_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Refund - Pet Care & Sitting System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .hero-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 4rem 0;
        }
        
        .refund-card {
            border: none;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s;
        }
        
        .refund-card:hover {
            transform: translateY(-2px);
        }
        
        .status-badge {
            font-size: 0.8em;
            padding: 0.5em 1em;
        }
        
        .policy-card {
            background: #f8f9fa;
            border-left: 4px solid #007bff;
        }
    </style>
</head>
<body>
    <!-- Hero Section -->
    <div class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="display-4 mb-3">
                        <i class="fas fa-undo-alt me-3"></i>Request a Refund
                    </h1>
                    <p class="lead">Need to return a pet store purchase or cancel a service booking? We're here to help make the process quick and easy.</p>
                </div>
                <div class="col-md-4 text-center">
                    <i class="fas fa-headset fa-5x opacity-75"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="container py-5">
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

        <div class="row">
            <!-- Refund Request Form -->
            <div class="col-lg-8">
                <div class="card refund-card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0"><i class="fas fa-file-alt me-2"></i>Submit Refund Request</h4>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="refundForm">
                            <div class="row">
                                <div class="col-md-6">
                                    <label class="form-label">What would you like to refund?</label>
                                    <select name="refund_type" id="refund_type" class="form-select" required onchange="updateReferenceOptions()">
                                        <option value="">Select type</option>
                                        <option value="order">Pet Store Order</option>
                                        <option value="booking">Service Booking</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Select Order/Booking</label>
                                    <select name="reference_id" id="reference_id" class="form-select" required onchange="updateAmount()">
                                        <option value="">First select type above</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row mt-3">
                                <div class="col-md-6">
                                    <label class="form-label">Refund Amount</label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" name="amount" id="amount" class="form-control" step="0.01" placeholder="0.00" required readonly>
                                    </div>
                                    <small class="form-text text-muted">Amount will be automatically filled based on your selection</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Reason for Refund</label>
                                    <select name="reason" class="form-select" required>
                                        <option value="">Select reason</option>
                                        <option value="Cancellation">Cancellation</option>
                                        <option value="Product Issue">Product Issue/Defective</option>
                                        <option value="Service Issue">Service Quality Issue</option>
                                        <option value="Billing Error">Billing Error</option>
                                        <option value="Not as Described">Not as Described</option>
                                        <option value="Change of Mind">Change of Mind</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="mt-3">
                                <label class="form-label">Additional Details</label>
                                <textarea name="customer_notes" class="form-control" rows="4" 
                                          placeholder="Please provide more details about your refund request. This helps us process it faster."></textarea>
                            </div>
                            
                            <div class="mt-4 text-center">
                                <button type="submit" name="submit_refund_request" class="btn btn-primary btn-lg">
                                    <i class="fas fa-paper-plane me-2"></i>Submit Refund Request
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Refund History -->
                <div class="card refund-card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-history me-2"></i>Your Refund History</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($refunds->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Request ID</th>
                                            <th>Type</th>
                                            <th>Amount</th>
                                            <th>Reason</th>
                                            <th>Status</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($refund = $refunds->fetch_assoc()): ?>
                                            <tr>
                                                <td><strong>#<?php echo $refund['refund_id']; ?></strong></td>
                                                <td><?php echo $refund['reference_display']; ?></td>
                                                <td><strong>$<?php echo number_format($refund['amount'], 2); ?></strong></td>
                                                <td><?php echo htmlspecialchars($refund['reason']); ?></td>
                                                <td>
                                                    <span class="badge status-badge bg-<?php 
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
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No refund requests yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Refund Policies Sidebar -->
            <div class="col-lg-4">
                <div class="card refund-card mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Refund Policies</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($policies->num_rows > 0): ?>
                            <?php while ($policy = $policies->fetch_assoc()): ?>
                                <div class="policy-card p-3 mb-3 rounded">
                                    <h6 class="fw-bold text-primary">
                                        <i class="fas fa-shield-alt me-1"></i>
                                        <?php echo htmlspecialchars($policy['policy_name']); ?>
                                    </h6>
                                    <p class="mb-2 small"><?php echo htmlspecialchars($policy['description']); ?></p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="badge bg-success"><?php echo $policy['refund_percentage']; ?>% Refund</span>
                                        <?php if ($policy['time_limit_hours']): ?>
                                            <small class="text-muted">
                                                <i class="fas fa-clock me-1"></i>
                                                <?php echo $policy['time_limit_hours']; ?>h limit
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($policy['conditions']): ?>
                                        <hr class="my-2">
                                        <small class="text-muted">
                                            <i class="fas fa-exclamation-triangle me-1"></i>
                                            <?php echo htmlspecialchars($policy['conditions']); ?>
                                        </small>
                                    <?php endif; ?>
                                </div>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Contact Support -->
                <div class="card refund-card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-headset me-2"></i>Need Help?</h5>
                    </div>
                    <div class="card-body text-center">
                        <p>Have questions about your refund?</p>
                        <div class="d-grid gap-2">
                            <a href="mailto:support@petcare.lk" class="btn btn-outline-success">
                                <i class="fas fa-envelope me-2"></i>Email Support
                            </a>
                            <a href="tel:+94112345678" class="btn btn-outline-success">
                                <i class="fas fa-phone me-2"></i>Call Support
                            </a>
                        </div>
                        <small class="text-muted mt-2 d-block">
                            Business Hours: Mon-Fri 9AM-6PM
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Store order and booking data
        const orders = <?php 
            $orders->data_seek(0);
            $order_data = [];
            while ($order = $orders->fetch_assoc()) {
                $order_data[] = $order;
            }
            echo json_encode($order_data);
        ?>;
        
        const bookings = <?php 
            $bookings->data_seek(0);
            $booking_data = [];
            while ($booking = $bookings->fetch_assoc()) {
                $booking_data[] = $booking;
            }
            echo json_encode($booking_data);
        ?>;
        
        function updateReferenceOptions() {
            const refundType = document.getElementById('refund_type').value;
            const referenceSelect = document.getElementById('reference_id');
            const amountInput = document.getElementById('amount');
            
            // Clear current options
            referenceSelect.innerHTML = '<option value="">Select ' + (refundType === 'order' ? 'order' : 'booking') + '</option>';
            amountInput.value = '';
            
            let data = refundType === 'order' ? orders : bookings;
            
            data.forEach(function(item) {
                const option = document.createElement('option');
                if (refundType === 'order') {
                    option.value = item.orderID;
                    option.textContent = `Order #${item.orderID} - $${parseFloat(item.total).toFixed(2)} (${item.status})`;
                    option.dataset.amount = item.total;
                } else {
                    option.value = item.booking_id;
                    option.textContent = `Booking #${item.booking_id} - $${parseFloat(item.amount).toFixed(2)} (${item.status})`;
                    option.dataset.amount = item.amount;
                }
                referenceSelect.appendChild(option);
            });
        }
        
        function updateAmount() {
            const referenceSelect = document.getElementById('reference_id');
            const amountInput = document.getElementById('amount');
            const selectedOption = referenceSelect.options[referenceSelect.selectedIndex];
            
            if (selectedOption && selectedOption.dataset.amount) {
                amountInput.value = parseFloat(selectedOption.dataset.amount).toFixed(2);
            } else {
                amountInput.value = '';
            }
        }
    </script>
</body>
</html>

<?php
$conn->close();
?>