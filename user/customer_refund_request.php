<?php
// Enhanced Customer Refund Request System - User-Friendly Version
// File: user/customer_refund_request.php

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
    
    // Enhanced validation with user-friendly messages
    $errors = [];
    
    if (empty($refund_type) || !in_array($refund_type, ['order', 'booking'])) {
        $errors[] = "🔍 Please select whether you want to refund an order or booking.";
    }
    
    if ($reference_id <= 0) {
        $errors[] = "📝 Please select the specific order or booking you want to refund.";
    }
    
                if ($amount <= 0) {
        $errors[] = "💰 Please enter a valid refund amount greater than Rs.0.";
    }
    
    if (empty($reason)) {
        $errors[] = "❓ Please tell us why you're requesting this refund.";
    }
    
    if (empty($customer_notes)) {
        $errors[] = "📋 Please provide some details about your refund request to help us process it quickly.";
    }
    
    // Verify ownership and calculate max refundable amount
    if (empty($errors)) {
        if ($refund_type === 'order') {
            $verify_sql = "SELECT o.orderID, COALESCE(c.total_amount, 0) as totalAmount 
                          FROM `order` o 
                          LEFT JOIN cart c ON o.orderID = c.orderID 
                          WHERE o.orderID = ? AND o.userID = ?";
        } else {
            // For bookings, calculate cost dynamically
            $verify_sql = "SELECT b.bookingID, b.checkInDate, b.checkInTime, b.checkOutDate, b.checkOutTime,
                                  COALESCE(ps.price, 0) as hourly_rate
                          FROM booking b 
                          LEFT JOIN pet_sitter ps ON b.sitterID = ps.userID
                          WHERE b.bookingID = ? AND b.userID = ?";
        }
        
        $verify_stmt = $conn->prepare($verify_sql);
        if ($verify_stmt && $verify_stmt->bind_param("ii", $reference_id, $customer_id)) {
            $verify_stmt->execute();
            $verify_result = $verify_stmt->get_result();
            
            if ($verify_result->num_rows === 0) {
                $errors[] = "❌ The selected " . $refund_type . " doesn't exist or doesn't belong to you.";
            } else {
                $row = $verify_result->fetch_assoc();
                if ($refund_type === 'order') {
                    $totalAmount = $row['totalAmount'];
                } else {
                    // Calculate booking total
                    $checkIn = new DateTime($row['checkInDate'] . ' ' . $row['checkInTime']);
                    $checkOut = new DateTime($row['checkOutDate'] . ' ' . $row['checkOutTime']);
                    $hours = max(1, ($checkOut->getTimestamp() - $checkIn->getTimestamp()) / 3600);
                    $totalAmount = $hours * $row['hourly_rate'];
                }
                
                if ($amount > $totalAmount) {
                    $errors[] = "💳 Refund amount cannot exceed the total paid amount of Rs." . number_format($totalAmount, 2);
                }
            }
            $verify_stmt->close();
        } else {
            $errors[] = "⚠️ Unable to verify your request. Please try again.";
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
                $errors[] = "🔄 You already have a pending or approved refund request for this " . $refund_type . ". Please wait for it to be processed.";
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
                $message = "🎉 Great! Your refund request #$refund_id has been submitted successfully! We'll review it within 2-3 business days and send you an email update.";
                $_POST = []; // Clear form data
            } else {
                $error = "❌ Oops! We couldn't submit your refund request right now. Please try again in a few minutes.";
            }
            $insert_stmt->close();
        } else {
            $error = "⚠️ Technical issue occurred. Please contact support if this continues.";
        }
    } else {
        $error = implode("<br>", $errors);
    }
}

// Get user's orders for dropdown
$orders_sql = "SELECT o.orderID, o.date, o.time, COALESCE(c.total_amount, 0) as totalAmount 
               FROM `order` o 
               LEFT JOIN cart c ON o.orderID = c.orderID 
               WHERE o.userID = ? AND o.date >= DATE_SUB(CURRENT_DATE, INTERVAL 90 DAY)
               ORDER BY o.date DESC, o.time DESC";
$orders_stmt = $conn->prepare($orders_sql);
$orders = [];
if ($orders_stmt) {
    $orders_stmt->bind_param("i", $customer_id);
    $orders_stmt->execute();
    $orders_result = $orders_stmt->get_result();
    $orders = $orders_result->fetch_all(MYSQLI_ASSOC);
    $orders_stmt->close();
}

// Get user's bookings for dropdown
$bookings_sql = "SELECT b.bookingID, b.checkInDate, b.checkInTime, b.checkOutDate, b.checkOutTime,
                         ps.price as hourly_rate, po.fullName as sitter_name
                  FROM booking b 
                  LEFT JOIN pet_sitter ps ON b.sitterID = ps.userID
                  LEFT JOIN pet_owner po ON ps.userID = po.userID
                  WHERE b.userID = ? AND b.checkInDate >= DATE_SUB(CURRENT_DATE, INTERVAL 90 DAY)
                  ORDER BY b.checkInDate DESC";
$bookings_stmt = $conn->prepare($bookings_sql);
$bookings = [];
if ($bookings_stmt) {
    $bookings_stmt->bind_param("i", $customer_id);
    $bookings_stmt->execute();
    $bookings_result = $bookings_stmt->get_result();
    $bookings = $bookings_result->fetch_all(MYSQLI_ASSOC);
    $bookings_stmt->close();
}

// Get existing refund requests
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
        :root {
            --primary-color: #28a745;
            --secondary-color: #20c997;
            --accent-color: #17a2b8;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --success-color: #28a745;
        }

        body { 
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
        }

        .header-section {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white; 
            padding: 3rem 0; 
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

        .main-card {
            background: white; 
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1); 
            overflow: hidden;
            border: none;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .main-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
        }

        .form-section { 
            padding: 3rem; 
        }

        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2rem;
            padding: 0 1rem;
        }

        .step {
            display: flex;
            align-items: center;
            flex: 1;
            text-align: center;
            position: relative;
        }

        .step-number {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e9ecef;
            color: #6c757d;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin: 0 auto 0.5rem;
            transition: all 0.3s ease;
        }

        .step.active .step-number {
            background: var(--primary-color);
            color: white;
        }

        .step.completed .step-number {
            background: var(--success-color);
            color: white;
        }

        .step-text {
            font-size: 0.9rem;
            color: #6c757d;
        }

        .step.active .step-text {
            color: var(--primary-color);
            font-weight: 600;
        }

        .form-group {
            margin-bottom: 2rem;
        }

        .form-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-control, .form-select {
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 0.75rem 1rem;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.15);
        }

        .help-text {
            font-size: 0.875rem;
            color: #6c757d;
            margin-top: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .status-badge {
            font-size: 0.875rem; 
            padding: 0.5rem 1rem; 
            border-radius: 20px; 
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-pending { background: #fff3cd; color: #856404; }
        .status-approved { background: #d1ecf1; color: #0c5460; }
        .status-rejected { background: #f8d7da; color: #721c24; }
        .status-processed { background: #d4edda; color: #155724; }

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
            border-radius: 12px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
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

        .sidebar-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            margin-bottom: 1.5rem;
        }

        .card-header {
            background: linear-gradient(135deg, var(--accent-color) 0%, #138496 100%);
            color: white;
            padding: 1rem 1.5rem;
            border: none;
        }

        .refund-item {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #e9ecef;
            transition: background 0.3s ease;
        }

        .refund-item:hover {
            background: #f8f9fa;
        }

        .refund-item:last-child {
            border-bottom: none;
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

        @media (max-width: 768px) {
            .header-section h1 { font-size: 2rem; }
            .form-section { padding: 2rem 1.5rem; }
            .step-indicator { flex-direction: column; gap: 1rem; }
            .step { flex-direction: row; text-align: left; }
            .step-number { margin: 0 1rem 0 0; }
        }

        .amount-helper {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 1rem;
            margin-top: 0.5rem;
            display: none;
        }

        .amount-helper.show {
            display: block;
        }

        .progress-bar-custom {
            height: 6px;
            background: #e9ecef;
            border-radius: 3px;
            overflow: hidden;
            margin: 1rem 0;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
            transition: width 0.5s ease;
        }
    </style>
</head>
<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="text-center">
            <div class="loading-spinner"></div>
            <p class="mt-3">Processing your request...</p>
        </div>
    </div>

    <!-- Header Section -->
    <div class="header-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1><i class="fas fa-undo-alt me-3"></i>Request a Refund</h1>
                    <p class="mb-0">Need a refund? We're here to help! Follow the simple steps below to submit your request.</p>
                </div>
                <div class="col-md-4 text-md-end">
                    <div class="d-flex align-items-center justify-content-md-end">
                        <div class="me-3">
                            <i class="fas fa-clock fa-2x opacity-75"></i>
                        </div>
                        <div>
                            <small class="opacity-75">Average processing time</small>
                            <div class="fw-bold">2-3 business days</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Success/Error Messages -->
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
            <!-- Main Form -->
            <div class="col-lg-8">
                <div class="main-card">
                    <div class="form-section">
                        <!-- Progress Steps -->
                        <div class="step-indicator">
                            <div class="step active" id="step1">
                                <div>
                                    <div class="step-number">1</div>
                                    <div class="step-text">Select Type</div>
                                </div>
                            </div>
                            <div class="step" id="step2">
                                <div>
                                    <div class="step-number">2</div>
                                    <div class="step-text">Choose Item</div>
                                </div>
                            </div>
                            <div class="step" id="step3">
                                <div>
                                    <div class="step-number">3</div>
                                    <div class="step-text">Enter Details</div>
                                </div>
                            </div>
                            <div class="step" id="step4">
                                <div>
                                    <div class="step-number">4</div>
                                    <div class="step-text">Submit</div>
                                </div>
                            </div>
                        </div>

                        <!-- Progress Bar -->
                        <div class="progress-bar-custom">
                            <div class="progress-fill" id="progressFill" style="width: 25%"></div>
                        </div>

                        <form method="POST" id="refundForm">
                            <!-- Step 1: Refund Type -->
                            <div class="form-group">
                                <label for="refund_type" class="form-label">
                                    <i class="fas fa-list-alt text-primary"></i>
                                    What would you like to refund?
                                </label>
                                <select class="form-select" id="refund_type" name="refund_type" required>
                                    <option value="">Choose refund type...</option>
                                    <option value="order" <?php echo (isset($_POST['refund_type']) && $_POST['refund_type'] === 'order') ? 'selected' : ''; ?>>
                                        🛒 Product Order
                                    </option>
                                    <option value="booking" <?php echo (isset($_POST['refund_type']) && $_POST['refund_type'] === 'booking') ? 'selected' : ''; ?>>
                                        📅 Pet Sitting Booking
                                    </option>
                                </select>
                                <div class="help-text">
                                    <i class="fas fa-info-circle text-info"></i>
                                    Select whether you want to refund a product order or a pet sitting service booking.
                                </div>
                            </div>

                            <!-- Step 2: Reference Selection -->
                            <div class="form-group">
                                <label for="reference_id" class="form-label">
                                    <i class="fas fa-search text-primary"></i>
                                    Which item do you want to refund?
                                </label>
                                <select class="form-select" id="reference_id" name="reference_id" required disabled>
                                    <option value="">First select a refund type above...</option>
                                </select>
                                <div class="help-text">
                                    <i class="fas fa-calendar-alt text-info"></i>
                                    Only items from the last 90 days are eligible for refunds.
                                </div>
                                
                                <!-- Amount Helper -->
                                <div class="amount-helper" id="amountHelper">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span>Total Amount:</span>
                                        <strong id="maxAmount">Rs.0.00</strong>
                                    </div>
                                    <small class="text-muted">You can request a partial or full refund up to this amount in Sri Lankan Rupees.</small>
                                </div>
                            </div>

                            <!-- Step 3: Amount and Reason -->
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="amount" class="form-label">
                                            <i class="fas fa-coins text-primary"></i>
                                            Refund Amount (Rs.)
                                        </label>
                                        <input type="number" class="form-control" id="amount" name="amount" 
                                               step="0.01" min="0.01" placeholder="0.00" required
                                               value="<?php echo isset($_POST['amount']) ? htmlspecialchars($_POST['amount']) : ''; ?>">
                                        <div class="help-text">
                                            <i class="fas fa-calculator text-info"></i>
                                            Enter the amount in Sri Lankan Rupees you want refunded.
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="reason" class="form-label">
                                            <i class="fas fa-question-circle text-primary"></i>
                                            Reason for Refund
                                        </label>
                                        <select class="form-select" id="reason" name="reason" required>
                                            <option value="">Select a reason...</option>
                                            <option value="damaged_product" <?php echo (isset($_POST['reason']) && $_POST['reason'] === 'damaged_product') ? 'selected' : ''; ?>>
                                                📦 Product was damaged
                                            </option>
                                            <option value="wrong_product" <?php echo (isset($_POST['reason']) && $_POST['reason'] === 'wrong_product') ? 'selected' : ''; ?>>
                                                🔄 Wrong product received
                                            </option>
                                            <option value="service_unsatisfactory" <?php echo (isset($_POST['reason']) && $_POST['reason'] === 'service_unsatisfactory') ? 'selected' : ''; ?>>
                                                ⭐ Service was unsatisfactory
                                            </option>
                                            <option value="cancelled_appointment" <?php echo (isset($_POST['reason']) && $_POST['reason'] === 'cancelled_appointment') ? 'selected' : ''; ?>>
                                                ❌ Had to cancel appointment
                                            </option>
                                            <option value="billing_error" <?php echo (isset($_POST['reason']) && $_POST['reason'] === 'billing_error') ? 'selected' : ''; ?>>
                                                💳 Billing error
                                            </option>
                                            <option value="other" <?php echo (isset($_POST['reason']) && $_POST['reason'] === 'other') ? 'selected' : ''; ?>>
                                                📝 Other reason
                                            </option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Step 4: Additional Details -->
                            <div class="form-group">
                                <label for="customer_notes" class="form-label">
                                    <i class="fas fa-comment-dots text-primary"></i>
                                    Tell us more about your request
                                </label>
                                <textarea class="form-control" id="customer_notes" name="customer_notes" rows="4" 
                                          placeholder="Please provide any additional details that will help us process your refund request quickly..."
                                          required><?php echo isset($_POST['customer_notes']) ? htmlspecialchars($_POST['customer_notes']) : ''; ?></textarea>
                                <div class="help-text">
                                    <i class="fas fa-lightbulb text-warning"></i>
                                    The more details you provide, the faster we can process your request!
                                </div>
                            </div>

                            <!-- Submit Buttons -->
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <button type="reset" class="btn btn-outline-secondary me-md-2">
                                    <i class="fas fa-undo me-2"></i>Reset Form
                                </button>
                                <button type="submit" name="submit_refund" class="btn btn-primary btn-lg">
                                    <i class="fas fa-paper-plane me-2"></i>Submit Refund Request
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Quick Help -->
                <div class="sidebar-card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-question-circle me-2"></i>Need Help?</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <h6><i class="fas fa-clock text-info me-2"></i>Processing Time</h6>
                            <p class="small mb-0">Most refund requests are processed within 2-3 business days.</p>
                        </div>
                        <div class="mb-3">
                            <h6><i class="fas fa-envelope text-info me-2"></i>Stay Updated</h6>
                            <p class="small mb-0">We'll send you email updates about your refund status.</p>
                        </div>
                        <div class="mb-3">
                            <h6><i class="fas fa-phone text-info me-2"></i>Contact Support</h6>
                            <p class="small mb-0">Need immediate help? Call us at (555) 123-4567</p>
                        </div>
                    </div>
                </div>

                <!-- Your Recent Refund Requests -->
                <div class="sidebar-card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-history me-2"></i>Your Recent Requests</h6>
                    </div>
                    <div class="card-body p-0">
                        <?php if (!empty($existing_refunds)): ?>
                            <?php foreach (array_slice($existing_refunds, 0, 5) as $refund): ?>
                                <div class="refund-item">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <strong>#<?php echo $refund['refund_id']; ?></strong>
                                            <br><small class="text-muted">
                                                <?php echo ucfirst($refund['refund_type']); ?> #<?php echo $refund['reference_id']; ?>
                                            </small>
                                            <br><strong class="text-success">
                                                Rs.<?php echo number_format($refund['amount'], 2); ?>
                                            </strong>
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
                                </div>
                            <?php endforeach; ?>
                            <?php if (count($existing_refunds) > 5): ?>
                                <div class="text-center p-3">
                                    <a href="#" class="btn btn-outline-primary btn-sm">View All Requests</a>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="text-center p-4">
                                <i class="fas fa-inbox fa-2x text-muted mb-2"></i>
                                <p class="text-muted mb-0">No refund requests yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Refund Policy -->
                <div class="sidebar-card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-shield-alt me-2"></i>Refund Policy</h6>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled small">
                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Full refunds within 30 days</li>
                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Partial refunds for services</li>
                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Quick processing times</li>
                            <li class="mb-0"><i class="fas fa-check text-success me-2"></i>Email notifications</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Data from PHP
            const ordersData = <?php echo json_encode($orders); ?>;
            const bookingsData = <?php echo json_encode($bookings); ?>;
            
            // Form elements
            const refundTypeSelect = document.getElementById('refund_type');
            const referenceSelect = document.getElementById('reference_id');
            const amountInput = document.getElementById('amount');
            const amountHelper = document.getElementById('amountHelper');
            const maxAmountSpan = document.getElementById('maxAmount');
            const form = document.getElementById('refundForm');
            const loadingOverlay = document.getElementById('loadingOverlay');
            
            // Step tracking
            let currentStep = 1;
            const totalSteps = 4;
            
            // Update progress
            function updateProgress() {
                const progressFill = document.getElementById('progressFill');
                const progressPercent = (currentStep / totalSteps) * 100;
                progressFill.style.width = progressPercent + '%';
                
                // Update step indicators
                for (let i = 1; i <= totalSteps; i++) {
                    const step = document.getElementById(`step${i}`);
                    step.classList.remove('active', 'completed');
                    
                    if (i < currentStep) {
                        step.classList.add('completed');
                        step.querySelector('.step-number').innerHTML = '<i class="fas fa-check"></i>';
                    } else if (i === currentStep) {
                        step.classList.add('active');
                        step.querySelector('.step-number').innerHTML = i;
                    } else {
                        step.querySelector('.step-number').innerHTML = i;
                    }
                }
            }
            
            // Handle refund type change
            refundTypeSelect.addEventListener('change', function() {
                const refundType = this.value;
                referenceSelect.innerHTML = '<option value="">Select an item...</option>';
                referenceSelect.disabled = !refundType;
                amountHelper.classList.remove('show');
                amountInput.value = '';
                
                if (refundType === 'order') {
                    ordersData.forEach(order => {
                        const option = document.createElement('option');
                        option.value = order.orderID;
                        option.textContent = `Order #${order.orderID} - ${order.date} (Rs.${parseFloat(order.totalAmount).toFixed(2)})`;
                        option.dataset.amount = order.totalAmount;
                        referenceSelect.appendChild(option);
                    });
                    currentStep = 2;
                } else if (refundType === 'booking') {
                    bookingsData.forEach(booking => {
                        // Calculate booking total
                        const checkIn = new Date(booking.checkInDate + ' ' + booking.checkInTime);
                        const checkOut = new Date(booking.checkOutDate + ' ' + booking.checkOutTime);
                        const hours = Math.max(1, (checkOut - checkIn) / (1000 * 60 * 60));
                        const total = hours * parseFloat(booking.hourly_rate || 0);
                        
                        const option = document.createElement('option');
                        option.value = booking.bookingID;
                        option.textContent = `Booking #${booking.bookingID} - ${booking.checkInDate} with ${booking.sitter_name || 'Unknown'} (Rs.${total.toFixed(2)})`;
                        option.dataset.amount = total.toFixed(2);
                        referenceSelect.appendChild(option);
                    });
                    currentStep = 2;
                }
                
                updateProgress();
            });
            
            // Handle reference selection
            referenceSelect.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                
                if (selectedOption.value) {
                    const maxAmount = parseFloat(selectedOption.dataset.amount || 0);
                    maxAmountSpan.textContent = `Rs.${maxAmount.toFixed(2)}`;
                    amountHelper.classList.add('show');
                    amountInput.max = maxAmount;
                    amountInput.value = maxAmount.toFixed(2); // Pre-fill with full amount
                    currentStep = 3;
                } else {
                    amountHelper.classList.remove('show');
                    amountInput.value = '';
                    currentStep = 2;
                }
                
                updateProgress();
            });
            
            // Handle form field changes for step tracking
            [document.getElementById('reason'), document.getElementById('customer_notes')].forEach(field => {
                field.addEventListener('change', function() {
                    if (this.value && currentStep === 3) {
                        currentStep = 4;
                        updateProgress();
                    }
                });
                
                field.addEventListener('input', function() {
                    if (this.value && currentStep === 3) {
                        currentStep = 4;
                        updateProgress();
                    }
                });
            });
            
            // Form submission with loading
            form.addEventListener('submit', function(e) {
                const maxAmount = parseFloat(amountInput.max || 0);
                const amount = parseFloat(amountInput.value || 0);
                
                if (amount <= 0) {
                    e.preventDefault();
                    alert('⚠️ Please enter a valid refund amount greater than Rs.0.');
                    return;
                }
                
                if (maxAmount > 0 && amount > maxAmount) {
                    e.preventDefault();
                    alert(`⚠️ Refund amount cannot exceed Rs.${maxAmount.toFixed(2)}`);
                    return;
                }
                
                // Show loading overlay
                loadingOverlay.style.display = 'flex';
            });
            
            // Form reset
            form.addEventListener('reset', function() {
                currentStep = 1;
                updateProgress();
                amountHelper.classList.remove('show');
                referenceSelect.disabled = true;
                referenceSelect.innerHTML = '<option value="">First select a refund type above...</option>';
            });
            
            // Initialize progress
            updateProgress();
            
            console.log('✅ Enhanced Customer Refund Request System loaded successfully');
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