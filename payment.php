<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error_message'] = "You must be logged in to make a payment.";
    header("Location: login.php");
    exit();
}

// Include database connection
require_once 'config/db_connect.php';

// Check if payment type is provided (booking or cart)
if (!isset($_GET['type']) || ($_GET['type'] !== 'booking' && $_GET['type'] !== 'cart')) {
    $_SESSION['error_message'] = "Invalid payment type.";
    header("Location: index.php");
    exit();
}

$payment_type = $_GET['type'];
$total_amount = 0;
$payment_details = [];
$booking_id = null;
$cart_id = null;

// Get payment details based on payment type
if ($payment_type === 'booking') {
    // Get booking_id from URL parameter
    if (!isset($_GET['booking_id']) || empty($_GET['booking_id'])) {
        $_SESSION['error_message'] = "Missing booking ID.";
        header("Location: user/bookings.php");
        exit();
    }
    
    $booking_id = intval($_GET['booking_id']);
    
    // Get booking details and verify it belongs to user and is confirmed but not paid
    $booking_sql = "SELECT b.*, p.petName, ps.fullName as sitterName, ps.service, ps.price as hourlyRate
                    FROM booking b 
                    JOIN pet_profile p ON b.petID = p.petID 
                    JOIN pet_sitter ps ON b.sitterID = ps.userID 
                    WHERE b.bookingID = ? AND b.userID = ? AND b.status = 'Confirmed'";
    $booking_stmt = $conn->prepare($booking_sql);
    $booking_stmt->bind_param("ii", $booking_id, $_SESSION['user_id']);
    $booking_stmt->execute();
    $booking_result = $booking_stmt->get_result();
    
    if ($booking_result->num_rows === 0) {
        $_SESSION['error_message'] = "Booking not found, doesn't belong to you, or is not ready for payment.";
        header("Location: user/bookings.php");
        exit();
    }
    
    $booking = $booking_result->fetch_assoc();
    
    // Check if already paid
    $payment_check_sql = "SELECT paymentID FROM payment WHERE bookingID = ? AND status = 'Completed'";
    $payment_check_stmt = $conn->prepare($payment_check_sql);
    $payment_check_stmt->bind_param("i", $booking_id);
    $payment_check_stmt->execute();
    $payment_check_result = $payment_check_stmt->get_result();
    
    if ($payment_check_result->num_rows > 0) {
        $_SESSION['error_message'] = "This booking has already been paid for.";
        header("Location: user/bookings.php");
        exit();
    }
    $payment_check_stmt->close();
    
    // Calculate total amount based on booking duration
    $check_in_datetime = new DateTime($booking['checkInDate'] . ' ' . $booking['checkInTime']);
    $check_out_datetime = new DateTime($booking['checkOutDate'] . ' ' . $booking['checkOutTime']);
    $interval = $check_in_datetime->diff($check_out_datetime);
    $hours = $interval->h + ($interval->days * 24) + ($interval->i > 0 ? 1 : 0); // Round up partial hours
    $total_amount = $hours * $booking['hourlyRate'];
    
    $payment_details = $booking;
    $payment_details['duration_hours'] = $hours;
    $booking_stmt->close();
    
} elseif ($payment_type === 'cart') {
    // Get active cart for the user
    $cart_sql = "SELECT c.* FROM cart c WHERE c.userID = ? AND c.orderID IS NULL";
    $cart_stmt = $conn->prepare($cart_sql);
    $cart_stmt->bind_param("i", $_SESSION['user_id']);
    $cart_stmt->execute();
    $cart_result = $cart_stmt->get_result();
    
    if ($cart_result->num_rows === 0) {
        $_SESSION['error_message'] = "Empty cart.";
        header("Location: cart.php");
        exit();
    }
    
    $cart = $cart_result->fetch_assoc();
    $cart_id = $cart['cartID'];
    $total_amount = $cart['total_amount'];
    
    // Get cart items
    $items_sql = "SELECT ci.*, p.name, p.brand, p.image 
                  FROM cart_items ci 
                  JOIN pet_food_and_accessories p ON ci.productID = p.productID 
                  WHERE ci.cartID = ?";
    $items_stmt = $conn->prepare($items_sql);
    $items_stmt->bind_param("i", $cart_id);
    $items_stmt->execute();
    $items_result = $items_stmt->get_result();
    
    $cart_items = [];
    while ($row = $items_result->fetch_assoc()) {
        $cart_items[] = $row;
    }
    
    $payment_details = [
        'cart' => $cart,
        'items' => $cart_items
    ];
    
    $items_stmt->close();
    $cart_stmt->close();
}

// Process payment form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $payment_method = $_POST['payment_method'];
    $card_number = isset($_POST['card_number']) ? $_POST['card_number'] : '';
    $card_holder = isset($_POST['card_holder']) ? $_POST['card_holder'] : '';
    $expiry_month = isset($_POST['expiry_month']) ? $_POST['expiry_month'] : '';
    $expiry_year = isset($_POST['expiry_year']) ? $_POST['expiry_year'] : '';
    $cvv = isset($_POST['cvv']) ? $_POST['cvv'] : '';
    
    // Validate form data
    $errors = array();
    
    if (empty($payment_method)) {
        $errors[] = "Please select a payment method.";
    }
    
    if ($payment_method === 'credit_card') {
        if (empty($card_number)) {
            $errors[] = "Card number is required.";
        } elseif (!preg_match("/^\d{16}$/", $card_number)) {
            $errors[] = "Invalid card number format.";
        }
        
        if (empty($card_holder)) {
            $errors[] = "Card holder name is required.";
        }
        
        if (empty($expiry_month) || empty($expiry_year)) {
            $errors[] = "Expiry date is required.";
        }
        
        if (empty($cvv)) {
            $errors[] = "CVV is required.";
        } elseif (!preg_match("/^\d{3,4}$/", $cvv)) {
            $errors[] = "Invalid CVV format.";
        }
    }
    
    // If no errors, proceed with payment
    if (empty($errors)) {
        if ($payment_type === 'booking' && $booking_id) {
            // Create payment record
            $payment_sql = "INSERT INTO payment (amount, payment_method, status, bookingID) VALUES (?, ?, 'Completed', ?)";
            $payment_stmt = $conn->prepare($payment_sql);
            $payment_stmt->bind_param("dsi", $total_amount, $payment_method, $booking_id);
            
            if ($payment_stmt->execute()) {
                // Update booking status to 'Paid'
                $update_sql = "UPDATE booking SET status = 'Paid' WHERE bookingID = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("i", $booking_id);
                
                if ($update_stmt->execute()) {
                    $update_stmt->close();
                    $_SESSION['success_message'] = "Payment successful! Your booking is now confirmed and paid.";
                    
                    // Debug: Check if we're in root directory or subdirectory
                    $redirect_path = "user/bookings.php";
                    if (!file_exists($redirect_path)) {
                        $redirect_path = "../user/bookings.php";
                    }
                    
                    header("Location: " . $redirect_path);
                    exit();
                } else {
                    $errors[] = "Failed to update booking status: " . $update_stmt->error;
                    $update_stmt->close();
                }
            } else {
                $errors[] = "Payment failed: " . $payment_stmt->error;
            }
            
            $payment_stmt->close();
            
        } elseif ($payment_type === 'cart' && $cart_id) {
            // Create order
            $order_sql = "INSERT INTO `order` (date, time, address, contact, status, userID) VALUES (CURDATE(), CURTIME(), ?, ?, 'Pending', ?)";
            $order_stmt = $conn->prepare($order_sql);
            
            // Get user's address and contact
            $user_sql = "SELECT address, contact FROM pet_owner WHERE userID = ?";
            $user_stmt = $conn->prepare($user_sql);
            $user_stmt->bind_param("i", $_SESSION['user_id']);
            $user_stmt->execute();
            $user = $user_stmt->get_result()->fetch_assoc();
            $user_stmt->close();
            
            $order_stmt->bind_param("ssi", $user['address'], $user['contact'], $_SESSION['user_id']);
            
            if ($order_stmt->execute()) {
                $order_id = $order_stmt->insert_id;
                
                // Update cart with order_id
                $update_cart_sql = "UPDATE cart SET orderID = ? WHERE cartID = ?";
                $update_cart_stmt = $conn->prepare($update_cart_sql);
                $update_cart_stmt->bind_param("ii", $order_id, $cart_id);
                $update_cart_stmt->execute();
                $update_cart_stmt->close();
                
                // Create payment record
                $payment_sql = "INSERT INTO payment (amount, payment_method, status, cartID) VALUES (?, ?, 'Completed', ?)";
                $payment_stmt = $conn->prepare($payment_sql);
                $payment_stmt->bind_param("dsi", $total_amount, $payment_method, $cart_id);
                
                if ($payment_stmt->execute()) {
                    $_SESSION['success_message'] = "Payment successful! Your order has been placed.";
                    header("Location: user/orders.php");
                    exit();
                } else {
                    $errors[] = "Payment failed: " . $conn->error;
                }
                
                $payment_stmt->close();
            } else {
                $errors[] = "Order creation failed: " . $conn->error;
            }
            
            $order_stmt->close();
        }
    }
}

// Include header
include_once 'includes/header.php';
?>

<div class="container py-5">
    <div class="row">
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-body">
                    <h3 class="card-title mb-4">Payment</h3>
                    
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach($errors as $error): ?>
                                    <li><?php echo $error; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Booking confirmation info -->
                    <?php if ($payment_type === 'booking'): ?>
                        <div class="alert alert-success">
                            <h6><i class="fas fa-check-circle me-2"></i>Booking Confirmed!</h6>
                            <p class="mb-0"><?php echo htmlspecialchars($payment_details['sitterName']); ?> has accepted your booking request. Complete payment to finalize the booking.</p>
                        </div>
                    <?php endif; ?>
                    
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . "?type=" . $payment_type . ($payment_type === 'booking' ? "&booking_id=" . $booking_id : "")); ?>" method="post" id="payment-form">
                        <div class="mb-4">
                            <h5>Payment Method</h5>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio" name="payment_method" id="credit_card" value="credit_card" checked>
                                <label class="form-check-label" for="credit_card">
                                    <i class="fab fa-cc-visa me-2"></i>
                                    <i class="fab fa-cc-mastercard me-2"></i>
                                    <i class="fab fa-cc-amex me-2"></i>
                                    Credit/Debit Card
                                </label>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio" name="payment_method" id="paypal" value="paypal">
                                <label class="form-check-label" for="paypal">
                                    <i class="fab fa-paypal me-2"></i>
                                    PayPal
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="payment_method" id="bank_transfer" value="bank_transfer">
                                <label class="form-check-label" for="bank_transfer">
                                    <i class="fas fa-university me-2"></i>
                                    Bank Transfer
                                </label>
                            </div>
                        </div>
                        
                        <div id="credit-card-fields">
                            <div class="mb-3">
                                <label for="card_number" class="form-label">Card Number</label>
                                <input type="text" class="form-control" id="card_number" name="card_number" placeholder="1234 5678 9012 3456" maxlength="16">
                            </div>
                            
                            <div class="mb-3">
                                <label for="card_holder" class="form-label">Card Holder Name</label>
                                <input type="text" class="form-control" id="card_holder" name="card_holder" placeholder="John Doe">
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-8">
                                    <label class="form-label">Expiry Date</label>
                                    <div class="d-flex">
                                        <select class="form-select me-2" name="expiry_month">
                                            <option value="">Month</option>
                                            <?php for ($i = 1; $i <= 12; $i++): ?>
                                                <option value="<?php echo sprintf('%02d', $i); ?>"><?php echo sprintf('%02d', $i); ?></option>
                                            <?php endfor; ?>
                                        </select>
                                        <select class="form-select" name="expiry_year">
                                            <option value="">Year</option>
                                            <?php for ($i = date('Y'); $i <= date('Y') + 10; $i++): ?>
                                                <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <label for="cvv" class="form-label">CVV</label>
                                    <input type="password" class="form-control" id="cvv" name="cvv" placeholder="123" maxlength="4">
                                </div>
                            </div>
                        </div>
                        
                        <div id="paypal-fields" style="display: none;">
                            <div class="alert alert-info">
                                <p>You will be redirected to PayPal to complete your payment.</p>
                            </div>
                        </div>
                        
                        <div id="bank-transfer-fields" style="display: none;">
                            <div class="alert alert-info">
                                <p>Please use the following bank details to make your transfer:</p>
                                <p><strong>Bank:</strong> National Bank of Sri Lanka</p>
                                <p><strong>Account Name:</strong> Pet Care & Sitting System</p>
                                <p><strong>Account Number:</strong> 1234567890</p>
                                <p><strong>Reference:</strong> Your name and booking/order number</p>
                            </div>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="save_payment" name="save_payment">
                            <label class="form-check-label" for="save_payment">Save payment method for future transactions</label>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg">
                                Complete Payment - $<?php echo number_format($total_amount, 2); ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Payment Summary</h5>
                    <hr>
                    
                    <?php if ($payment_type === 'booking'): ?>
                        <div class="mb-3">
                            <h6>Booking Details</h6>
                            <p><strong>Pet Sitter:</strong> <?php echo htmlspecialchars($payment_details['sitterName']); ?></p>
                            <p><strong>Service:</strong> <?php echo htmlspecialchars($payment_details['service']); ?></p>
                            <p><strong>Pet:</strong> <?php echo htmlspecialchars($payment_details['petName']); ?></p>
                            <p><strong>Check-in:</strong> <?php echo date('M d, Y', strtotime($payment_details['checkInDate'])) . ' at ' . date('h:i A', strtotime($payment_details['checkInTime'])); ?></p>
                            <p><strong>Check-out:</strong> <?php echo date('M d, Y', strtotime($payment_details['checkOutDate'])) . ' at ' . date('h:i A', strtotime($payment_details['checkOutTime'])); ?></p>
                            
                            <!-- Cost breakdown -->
                            <div class="cost-breakdown mt-3 p-3 bg-light rounded">
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Hourly Rate:</span>
                                    <span>$<?php echo number_format($payment_details['hourlyRate'], 2); ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Duration:</span>
                                    <span><?php echo $payment_details['duration_hours']; ?> hours</span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Subtotal:</span>
                                    <span>$<?php echo number_format($total_amount, 2); ?></span>
                                </div>
                            </div>
                        </div>
                    <?php elseif ($payment_type === 'cart'): ?>
                        <div class="mb-3">
                            <h6>Items (<?php echo count($payment_details['items']); ?>)</h6>
                            <?php foreach ($payment_details['items'] as $item): ?>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div>
                                        <p class="mb-0"><?php echo htmlspecialchars($item['name']); ?></p>
                                        <small class="text-muted">Qty: <?php echo $item['quantity']; ?></small>
                                    </div>
                                    <p class="mb-0">$<?php echo number_format($item['price'] * $item['quantity'], 2); ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <hr>
                    <div class="d-flex justify-content-between mb-2">
                        <p>Subtotal:</p>
                        <p>$<?php echo number_format($total_amount, 2); ?></p>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <p>Tax:</p>
                        <p>$0.00</p>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between fw-bold">
                        <p>Total:</p>
                        <p>$<?php echo number_format($total_amount, 2); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript for Payment Method Selection -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const paymentMethods = document.querySelectorAll('input[name="payment_method"]');
        const creditCardFields = document.getElementById('credit-card-fields');
        const paypalFields = document.getElementById('paypal-fields');
        const bankTransferFields = document.getElementById('bank-transfer-fields');
        
        paymentMethods.forEach(function(method) {
            method.addEventListener('change', function() {
                // Hide all fields first
                creditCardFields.style.display = 'none';
                paypalFields.style.display = 'none';
                bankTransferFields.style.display = 'none';
                
                // Show fields based on selected payment method
                if (this.value === 'credit_card') {
                    creditCardFields.style.display = 'block';
                } else if (this.value === 'paypal') {
                    paypalFields.style.display = 'block';
                } else if (this.value === 'bank_transfer') {
                    bankTransferFields.style.display = 'block';
                }
            });
        });
    });
</script>

<?php
// Include footer
include_once 'includes/footer.php';

// Close database connection
$conn->close();
?>