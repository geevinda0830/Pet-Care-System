<?php
// Customer Refund Request System - FIXED VERSION
// File: user/customer_refund_request.php
// This version works with your exact database structure (no totalCost column in booking)

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in as pet owner
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'pet_owner') {
    $_SESSION['error_message'] = "You must be logged in as a pet owner to access this page.";
    header("Location: ../login.php");
    exit();
}

// Include database connection
require_once '../config/db_connect.php';

$customer_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_refund'])) {
    $refund_type = trim($_POST['refund_type']);
    $reference_id = intval($_POST['reference_id']);
    $amount = floatval($_POST['amount']);
    $reason = trim($_POST['reason']);
    $customer_notes = trim($_POST['customer_notes']);
    
    // Validation
    $errors = [];
    
    if (empty($refund_type) || !in_array($refund_type, ['order', 'booking'])) {
        $errors[] = "Please select a valid refund type.";
    }
    
    if ($reference_id <= 0) {
        $errors[] = "Please select a valid order or booking.";
    }
    
    if ($amount <= 0) {
        $errors[] = "Please enter a valid refund amount.";
    }
    
    if (empty($reason)) {
        $errors[] = "Please select a reason for the refund.";
    }
    
    if (empty($customer_notes)) {
        $errors[] = "Please provide additional details about your refund request.";
    }
    
    // Verify ownership and calculate max refundable amount
    if (empty($errors)) {
        if ($refund_type === 'order') {
            $verify_sql = "SELECT o.orderID, COALESCE(c.total_amount, 0) as totalAmount 
                          FROM `order` o 
                          LEFT JOIN cart c ON o.orderID = c.orderID 
                          WHERE o.orderID = ? AND o.userID = ?";
        } else {
            // For bookings, we need to calculate cost dynamically
            $verify_sql = "SELECT b.bookingID, b.checkInDate, b.checkInTime, b.checkOutDate, b.checkOutTime,
                                  COALESCE(ps.price, 0) as hourly_rate
                          FROM booking b 
                          LEFT JOIN pet_sitter ps ON b.sitterID = ps.userID
                          WHERE b.bookingID = ? AND b.userID = ?";
        }
        
        $verify_stmt = $conn->prepare($verify_sql);
        if ($verify_stmt) {
            $verify_stmt->bind_param("ii", $reference_id, $customer_id);
            $verify_stmt->execute();
            $verify_result = $verify_stmt->get_result();
            
            if ($verify_result->num_rows === 0) {
                $errors[] = "Invalid order/booking ID or you don't have permission to request a refund for this item.";
            } else {
                $record = $verify_result->fetch_assoc();
                
                if ($refund_type === 'booking') {
                    // Calculate total cost for booking dynamically
                    $totalAmount = 0;
                    if ($record['checkInDate'] && $record['checkInTime'] && $record['checkOutDate'] && $record['checkOutTime'] && $record['hourly_rate'] > 0) {
                        try {
                            $check_in = new DateTime($record['checkInDate'] . ' ' . $record['checkInTime']);
                            $check_out = new DateTime($record['checkOutDate'] . ' ' . $record['checkOutTime']);
                            $interval = $check_in->diff($check_out);
                            $total_hours = $interval->days * 24 + $interval->h + ($interval->i / 60);
                            $totalAmount = $total_hours * $record['hourly_rate'];
                        } catch (Exception $e) {
                            $errors[] = "Error calculating booking cost: " . $e->getMessage();
                        }
                    } else {
                        $errors[] = "Cannot calculate booking cost. Missing booking details or sitter rate.";
                    }
                } else {
                    $totalAmount = $record['totalAmount'];
                }
                
                if ($amount > $totalAmount) {
                    $errors[] = "Refund amount cannot exceed the original order/booking amount of $" . number_format($totalAmount, 2);
                }
            }
            $verify_stmt->close();
        } else {
            $errors[] = "Database error occurred while verifying your request.";
        }
    }
    
    // Check for existing refund requests
    if (empty($errors)) {
        $existing_sql = "SELECT refund_id FROM refunds WHERE refund_type = ? AND reference_id = ? AND customer_id = ? AND status IN ('Pending', 'Approved', 'Processed')";
        $existing_stmt = $conn->prepare($existing_sql);
        if ($existing_stmt) {
            $existing_stmt->bind_param("sii", $refund_type, $reference_id, $customer_id);
            $existing_stmt->execute();
            $existing_result = $existing_stmt->get_result();
            
            if ($existing_result->num_rows > 0) {
                $errors[] = "You already have a pending or approved refund request for this " . $refund_type . ".";
            }
            $existing_stmt->close();
        }
    }
    
    // Insert refund request if no errors
    if (empty($errors)) {
        $insert_sql = "INSERT INTO refunds (refund_type, reference_id, customer_id, amount, reason, customer_notes, status, request_date) VALUES (?, ?, ?, ?, ?, ?, 'Pending', CURRENT_TIMESTAMP)";
        $insert_stmt = $conn->prepare($insert_sql);
        
        if ($insert_stmt && $insert_stmt->bind_param("siidss", $refund_type, $reference_id, $customer_id, $amount, $reason, $customer_notes)) {
            if ($insert_stmt->execute()) {
                $refund_id = $conn->insert_id;
                $message = "Your refund request #$refund_id has been submitted successfully! We will review it within 2-3 business days.";
                // Clear form data
                $_POST = [];
            } else {
                $error = "Failed to submit refund request. Please try again.";
            }
            $insert_stmt->close();
        } else {
            $error = "Database error occurred. Please try again later.";
        }
    } else {
        $error = implode("<br>", $errors);
    }
}

// Get user's orders for dropdown (with cart totals)
$orders_sql = "SELECT o.orderID, o.date, o.time, COALESCE(c.total_amount, 0) as totalAmount 
               FROM `order` o 
               LEFT JOIN cart c ON o.orderID = c.orderID 
               WHERE o.userID = ? 
               ORDER BY o.date DESC, o.time DESC LIMIT 50";
$orders_stmt = $conn->prepare($orders_sql);
$orders = [];
if ($orders_stmt) {
    $orders_stmt->bind_param("i", $customer_id);
    $orders_stmt->execute();
    $orders_result = $orders_stmt->get_result();
    $orders = $orders_result->fetch_all(MYSQLI_ASSOC);
    $orders_stmt->close();
}

// Get user's bookings for dropdown (calculate cost dynamically)
$bookings_sql = "SELECT b.bookingID, b.checkInDate, b.checkInTime, b.checkOutDate, b.checkOutTime, 
                        COALESCE(ps.price, 0) as hourly_rate, ps.fullName as sitter_name
                 FROM booking b 
                 LEFT JOIN pet_sitter ps ON b.sitterID = ps.userID 
                 WHERE b.userID = ? 
                 ORDER BY b.checkInDate DESC LIMIT 50";
$bookings_stmt = $conn->prepare($bookings_sql);
$bookings = [];
if ($bookings_stmt) {
    $bookings_stmt->bind_param("i", $customer_id);
    $bookings_stmt->execute();
    $bookings_result = $bookings_stmt->get_result();
    
    while ($row = $bookings_result->fetch_assoc()) {
        // Calculate total cost dynamically
        $row['totalCost'] = 0;
        if ($row['checkInDate'] && $row['checkInTime'] && $row['checkOutDate'] && $row['checkOutTime'] && $row['hourly_rate'] > 0) {
            try {
                $check_in = new DateTime($row['checkInDate'] . ' ' . $row['checkInTime']);
                $check_out = new DateTime($row['checkOutDate'] . ' ' . $row['checkOutTime']);
                $interval = $check_in->diff($check_out);
                $total_hours = $interval->days * 24 + $interval->h + ($interval->i / 60);
                $row['totalCost'] = $total_hours * $row['hourly_rate'];
            } catch (Exception $e) {
                // If date calculation fails, keep totalCost as 0
            }
        }
        $row['bookingDate'] = $row['checkInDate']; // Use checkInDate as bookingDate
        $bookings[] = $row;
    }
    $bookings_stmt->close();
}

// Get user's existing refund requests
$existing_refunds_sql = "SELECT r.*, CASE 
    WHEN r.refund_type = 'order' THEN (SELECT o.date FROM `order` o WHERE o.orderID = r.reference_id)
    WHEN r.refund_type = 'booking' THEN (SELECT b.checkInDate FROM booking b WHERE b.bookingID = r.reference_id)
    END as original_date
    FROM refunds r 
    WHERE r.customer_id = ? 
    ORDER BY r.request_date DESC";
$existing_refunds_stmt = $conn->prepare($existing_refunds_sql);
$existing_refunds = [];
if ($existing_refunds_stmt) {
    $existing_refunds_stmt->bind_param("i", $customer_id);
    $existing_refunds_stmt->execute();
    $existing_refunds_result = $existing_refunds_stmt->get_result();
    $existing_refunds = $existing_refunds_result->fetch_all(MYSQLI_ASSOC);
    $existing_refunds_stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Refund - Pet Care System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .header-section {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white; padding: 2rem 0; margin-bottom: 2rem;
        }
        .main-card {
            background: white; border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); overflow: hidden;
        }
        .form-section { padding: 2rem; }
        .status-badge {
            font-size: 0.875rem; padding: 0.5rem 1rem; border-radius: 20px; font-weight: 500;
        }
        .status-pending { background-color: #fff3cd; color: #856404; }
        .status-approved { background-color: #d1e7dd; color: #0f5132; }
        .status-rejected { background-color: #f8d7da; color: #721c24; }
        .status-processed { background-color: #cff4fc; color: #055160; }
        .info-card {
            background: #e8f4fd; border: 1px solid #bee5eb;
            border-radius: 10px; padding: 1rem; margin-bottom: 1.5rem;
        }
        .required { color: #dc3545; }
    </style>
</head>
<body>
    <!-- Header Section -->
    <div class="header-section">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-2"><i class="fas fa-undo-alt me-3"></i>Request Refund</h1>
                    <p class="mb-0 opacity-75">Submit a refund request for your orders or bookings</p>
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
                <i class="fas fa-check-circle me-2"></i><?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Refund Request Form -->
            <div class="col-md-8">
                <div class="main-card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-plus-circle me-2"></i>New Refund Request</h5>
                    </div>
                    <div class="form-section">
                        <!-- Info Card -->
                        <div class="info-card">
                            <div class="d-flex align-items-start">
                                <i class="fas fa-info-circle text-info me-3 mt-1"></i>
                                <div>
                                    <h6 class="mb-2">Refund Policy</h6>
                                    <p class="small mb-0">
                                        Refunds are processed within 2-3 business days after approval. 
                                        Please ensure you provide accurate information and detailed reasons for your request.
                                        For bookings, refund amounts are calculated based on booking duration and sitter rates.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <form method="POST" id="refundForm">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Refund Type <span class="required">*</span></label>
                                        <select name="refund_type" id="refundType" class="form-select" required onchange="updateReferenceOptions()">
                                            <option value="">Select type</option>
                                            <option value="order" <?php echo (isset($_POST['refund_type']) && $_POST['refund_type'] === 'order') ? 'selected' : ''; ?>>Order Refund</option>
                                            <option value="booking" <?php echo (isset($_POST['refund_type']) && $_POST['refund_type'] === 'booking') ? 'selected' : ''; ?>>Booking Refund</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Select Order/Booking <span class="required">*</span></label>
                                        <select name="reference_id" id="referenceId" class="form-select" required onchange="updateAmount()">
                                            <option value="">First select refund type</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Refund Amount <span class="required">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text">$</span>
                                            <input type="number" name="amount" id="refundAmount" class="form-control" 
                                                   step="0.01" min="0.01" required
                                                   value="<?php echo isset($_POST['amount']) ? htmlspecialchars($_POST['amount']) : ''; ?>">
                                        </div>
                                        <small class="text-muted">Maximum refundable amount will be shown after selecting an order/booking</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Reason for Refund <span class="required">*</span></label>
                                        <select name="reason" class="form-select" required>
                                            <option value="">Select reason</option>
                                            <option value="Product defective" <?php echo (isset($_POST['reason']) && $_POST['reason'] === 'Product defective') ? 'selected' : ''; ?>>Product defective</option>
                                            <option value="Wrong item received" <?php echo (isset($_POST['reason']) && $_POST['reason'] === 'Wrong item received') ? 'selected' : ''; ?>>Wrong item received</option>
                                            <option value="Service not provided" <?php echo (isset($_POST['reason']) && $_POST['reason'] === 'Service not provided') ? 'selected' : ''; ?>>Service not provided</option>
                                            <option value="Service cancelled" <?php echo (isset($_POST['reason']) && $_POST['reason'] === 'Service cancelled') ? 'selected' : ''; ?>>Service cancelled</option>
                                            <option value="Poor service quality" <?php echo (isset($_POST['reason']) && $_POST['reason'] === 'Poor service quality') ? 'selected' : ''; ?>>Poor service quality</option>
                                            <option value="Billing error" <?php echo (isset($_POST['reason']) && $_POST['reason'] === 'Billing error') ? 'selected' : ''; ?>>Billing error</option>
                                            <option value="Other" <?php echo (isset($_POST['reason']) && $_POST['reason'] === 'Other') ? 'selected' : ''; ?>>Other</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label">Additional Details <span class="required">*</span></label>
                                <textarea name="customer_notes" class="form-control" rows="4" required
                                          placeholder="Please provide detailed information about your refund request..."><?php echo isset($_POST['customer_notes']) ? htmlspecialchars($_POST['customer_notes']) : ''; ?></textarea>
                                <small class="text-muted">Provide as much detail as possible to help us process your request quickly</small>
                            </div>

                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <button type="reset" class="btn btn-outline-secondary me-md-2">
                                    <i class="fas fa-undo me-2"></i>Reset Form
                                </button>
                                <button type="submit" name="submit_refund" class="btn btn-primary">
                                    <i class="fas fa-paper-plane me-2"></i>Submit Refund Request
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-md-4">
                <!-- Your Refund Requests -->
                <div class="main-card mb-4">
                    <div class="card-header bg-info text-white">
                        <h6 class="mb-0"><i class="fas fa-history me-2"></i>Your Refund Requests</h6>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($existing_refunds)): ?>
                            <?php foreach (array_slice($existing_refunds, 0, 5) as $refund): ?>
                                <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                                    <div>
                                        <strong>#<?php echo $refund['refund_id']; ?></strong>
                                        <br><small class="text-muted">
                                            <?php echo ucfirst($refund['refund_type']); ?> #<?php echo $refund['reference_id']; ?>
                                        </small>
                                        <br><small class="text-muted">
                                            $<?php echo number_format($refund['amount'], 2); ?>
                                        </small>
                                    </div>
                                    <div class="text-end">
                                        <span class="status-badge status-<?php echo strtolower($refund['status']); ?>">
                                            <?php echo $refund['status']; ?>
                                        </span>
                                        <br><small class="text-muted">
                                            <?php echo date('M j', strtotime($refund['request_date'])); ?>
                                        </small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <?php if (count($existing_refunds) > 5): ?>
                                <small class="text-muted">And <?php echo count($existing_refunds) - 5; ?> more...</small>
                            <?php endif; ?>
                        <?php else: ?>
                            <p class="text-muted small mb-0">No previous refund requests found.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Help Card -->
                <div class="main-card">
                    <div class="card-header bg-warning text-dark">
                        <h6 class="mb-0"><i class="fas fa-question-circle me-2"></i>Need Help?</h6>
                    </div>
                    <div class="card-body">
                        <p class="small mb-3">If you have questions about refunds or need assistance:</p>
                        <ul class="list-unstyled small">
                            <li class="mb-2"><i class="fas fa-envelope text-primary me-2"></i>Email: support@petcare.com</li>
                            <li class="mb-2"><i class="fas fa-phone text-success me-2"></i>Phone: (555) 123-4567</li>
                            <li class="mb-2"><i class="fas fa-clock text-info me-2"></i>Mon-Fri: 9AM-6PM</li>
                        </ul>
                        <div class="alert alert-light mt-3">
                            <small><strong>Booking Refunds:</strong> Calculated based on booking duration and sitter hourly rates. Costs are calculated automatically.</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Orders and bookings data from PHP
        const ordersData = <?php echo json_encode($orders); ?>;
        const bookingsData = <?php echo json_encode($bookings); ?>;
        
        function updateReferenceOptions() {
            const refundType = document.getElementById('refundType').value;
            const referenceSelect = document.getElementById('referenceId');
            const amountInput = document.getElementById('refundAmount');
            
            // Clear existing options
            referenceSelect.innerHTML = '<option value="">Select ' + (refundType ? refundType : 'option') + '</option>';
            amountInput.value = '';
            
            if (refundType === 'order') {
                ordersData.forEach(order => {
                    const option = document.createElement('option');
                    option.value = order.orderID;
                    // Format date properly (order.date comes as YYYY-MM-DD from database)
                    const orderDate = order.date ? new Date(order.date).toLocaleDateString() : 'Unknown date';
                    option.textContent = `Order #${order.orderID} - $${parseFloat(order.totalAmount || 0).toFixed(2)} (${orderDate})`;
                    option.dataset.amount = order.totalAmount || 0;
                    referenceSelect.appendChild(option);
                });
            } else if (refundType === 'booking') {
                bookingsData.forEach(booking => {
                    const option = document.createElement('option');
                    option.value = booking.bookingID;
                    // Format date properly (booking.bookingDate comes as YYYY-MM-DD from database)
                    const bookingDate = booking.bookingDate ? new Date(booking.bookingDate).toLocaleDateString() : 'Unknown date';
                    const sitterInfo = booking.sitter_name ? ` (${booking.sitter_name})` : '';
                    option.textContent = `Booking #${booking.bookingID} - $${parseFloat(booking.totalCost || 0).toFixed(2)} (${bookingDate})${sitterInfo}`;
                    option.dataset.amount = booking.totalCost || 0;
                    referenceSelect.appendChild(option);
                });
            }
        }
        
        function updateAmount() {
            const referenceSelect = document.getElementById('referenceId');
            const amountInput = document.getElementById('refundAmount');
            const selectedOption = referenceSelect.options[referenceSelect.selectedIndex];
            
            if (selectedOption && selectedOption.dataset.amount) {
                amountInput.max = selectedOption.dataset.amount;
                amountInput.value = selectedOption.dataset.amount;
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            // Form validation
            document.getElementById('refundForm').addEventListener('submit', function(e) {
                const referenceId = document.getElementById('referenceId').value;
                const amount = parseFloat(document.getElementById('refundAmount').value);
                const maxAmount = parseFloat(document.getElementById('refundAmount').max);
                
                if (!referenceId) {
                    e.preventDefault();
                    alert('Please select an order or booking.');
                    return;
                }
                
                if (maxAmount > 0 && amount > maxAmount) {
                    e.preventDefault();
                    alert(`Refund amount cannot exceed $${maxAmount.toFixed(2)}`);
                    return;
                }
            });
            
            console.log('✅ Customer Refund Request System loaded successfully');
            console.log('📊 Available orders:', ordersData.length);
            console.log('📅 Available bookings:', bookingsData.length);
        });
    </script>
</body>
</html>

<?php
if (isset($conn)) {
    $conn->close();
}
?>