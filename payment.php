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

// Get payment type (cart or booking)
$payment_type = $_GET['type'] ?? 'cart';
$booking_id = $_GET['booking_id'] ?? null;

// Get user information
$user_sql = "SELECT * FROM pet_owner WHERE userID = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("i", $_SESSION['user_id']);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user = $user_result->fetch_assoc();
$user_stmt->close();

// Initialize variables
$order_items = [];
$total_amount = 0;
$booking_details = null;

if ($payment_type === 'booking' && $booking_id) {
    // Get booking details
    $booking_sql = "SELECT b.*, ps.price as hourly_rate, ps.fullName as sitterName,
                           p.petName, p.type as petType, p.breed as petBreed
                    FROM booking b 
                    JOIN pet_sitter ps ON b.sitterID = ps.userID
                    JOIN pet_profile p ON b.petID = p.petID
                    WHERE b.bookingID = ? AND b.userID = ?";
    $booking_stmt = $conn->prepare($booking_sql);
    $booking_stmt->bind_param("ii", $booking_id, $_SESSION['user_id']);
    $booking_stmt->execute();
    $booking_result = $booking_stmt->get_result();
    
    if ($booking_result->num_rows > 0) {
        $booking_details = $booking_result->fetch_assoc();
        
        // Calculate booking cost
        $check_in = new DateTime($booking_details['checkInDate'] . ' ' . $booking_details['checkInTime']);
        $check_out = new DateTime($booking_details['checkOutDate'] . ' ' . $booking_details['checkOutTime']);
        $interval = $check_in->diff($check_out);
        $total_hours = $interval->days * 24 + $interval->h + ($interval->i / 60);
        $total_amount = $total_hours * $booking_details['hourly_rate'];
    }
    $booking_stmt->close();
} else {
    // Get cart items for regular payment
    $cart_sql = "SELECT c.*, 
                        (SELECT SUM(ci.quantity * ci.price) FROM cart_items ci WHERE ci.cartID = c.cartID) as calculated_total
                 FROM cart c 
                 WHERE c.userID = ? AND c.status = 'active'";
    $cart_stmt = $conn->prepare($cart_sql);
    $cart_stmt->bind_param("i", $_SESSION['user_id']);
    $cart_stmt->execute();
    $cart_result = $cart_stmt->get_result();
    
    if ($cart_result->num_rows > 0) {
        $cart = $cart_result->fetch_assoc();
        $total_amount = $cart['calculated_total'] ?? $cart['total_amount'];
        
        // Get cart items
        $items_sql = "SELECT ci.*, p.name, p.brand, p.image, p.category 
                      FROM cart_items ci 
                      JOIN pet_food_and_accessories p ON ci.productID = p.productID 
                      WHERE ci.cartID = ?";
        $items_stmt = $conn->prepare($items_sql);
        $items_stmt->bind_param("i", $cart['cartID']);
        $items_stmt->execute();
        $items_result = $items_stmt->get_result();
        
        while ($row = $items_result->fetch_assoc()) {
            $row['subtotal'] = $row['quantity'] * $row['price'];
            $order_items[] = $row;
        }
        $items_stmt->close();
    }
    $cart_stmt->close();
}

// Include header
include_once 'includes/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment - Pet Care & Sitting System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
        }

        .payment-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .payment-header {
            text-align: center;
            color: white;
            margin-bottom: 40px;
        }

        .payment-header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .payment-header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .payment-content {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 30px;
            align-items: start;
        }

        .payment-form-section {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
        }

        .order-summary-section {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
            height: fit-content;
            position: sticky;
            top: 20px;
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .payment-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }

        .payment-option {
            border: 2px solid #e5e7eb;
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background: #f8fafc;
        }

        .payment-option:hover {
            border-color: #667eea;
            background: #f0f9ff;
        }

        .payment-option.active {
            border-color: #667eea;
            background: #f0f9ff;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
        }

        .payment-option i {
            font-size: 2rem;
            color: #667eea;
            margin-bottom: 10px;
        }

        .payment-option h6 {
            font-weight: 600;
            color: #1e293b;
            margin: 0;
        }

        .card-details {
            display: none;
            background: #f8fafc;
            border-radius: 15px;
            padding: 25px;
            margin-top: 20px;
        }

        .card-details.active {
            display: block;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            font-weight: 600;
            color: #374151;
            margin-bottom: 8px;
            display: block;
        }

        .form-control {
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            padding: 15px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 120px;
            gap: 15px;
        }

        .order-summary h3 {
            font-size: 1.3rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .order-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .order-item:last-child {
            border-bottom: none;
        }

        .item-info h4 {
            font-size: 1rem;
            font-weight: 600;
            color: #1e293b;
            margin: 0 0 5px 0;
        }

        .item-info p {
            font-size: 0.9rem;
            color: #64748b;
            margin: 0;
        }

        .item-price {
            font-weight: 700;
            color: #667eea;
            font-size: 1.1rem;
        }

        .order-total {
            border-top: 2px solid #e5e7eb;
            padding-top: 20px;
            margin-top: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .order-total h3 {
            font-size: 1.2rem;
            font-weight: 700;
            color: #1e293b;
            margin: 0;
        }

        .total-amount {
            font-size: 1.5rem;
            font-weight: 700;
            color: #667eea;
        }

        .billing-info {
            background: #f8fafc;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
        }

        .billing-info h5 {
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 15px;
        }

        .billing-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        .billing-item:last-child {
            margin-bottom: 0;
        }

        .pay-now-btn {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border: none;
            border-radius: 12px;
            padding: 15px 30px;
            color: white;
            font-weight: 700;
            font-size: 1.1rem;
            width: 100%;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .pay-now-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        }

        .security-notice {
            background: #ecfdf5;
            border: 1px solid #10b981;
            border-radius: 12px;
            padding: 15px;
            margin-top: 20px;
            text-align: center;
        }

        .security-notice i {
            color: #10b981;
            margin-right: 8px;
        }

        .security-notice small {
            color: #064e3b;
            font-weight: 500;
        }

        @media (max-width: 768px) {
            .payment-content {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .payment-form-section,
            .order-summary-section {
                padding: 25px;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .payment-options {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <div class="payment-container">
        <!-- Payment Header -->
        <div class="payment-header">
            <h1><i class="fas fa-credit-card me-3"></i>Secure Payment</h1>
            <p>Complete your <?php echo $payment_type === 'booking' ? 'booking' : 'order'; ?> with our secure payment system</p>
        </div>

        <div class="payment-content">
            <!-- Payment Form -->
            <div class="payment-form-section">
                <h2 class="section-title">
                    <i class="fas fa-lock"></i>
                    Payment Details
                </h2>

                <form id="paymentForm" method="POST" action="process_payment.php">
                    <input type="hidden" name="payment_type" value="<?php echo $payment_type; ?>">
                    <?php if ($booking_id): ?>
                        <input type="hidden" name="booking_id" value="<?php echo $booking_id; ?>">
                    <?php endif; ?>
                    <input type="hidden" name="total_amount" value="<?php echo $total_amount; ?>">

                    <!-- Payment Method Selection -->
                    <div class="form-group">
                        <label class="form-label">Select Payment Method</label>
                        <div class="payment-options">
                            <div class="payment-option active" data-method="credit_card">
                                <i class="fas fa-credit-card"></i>
                                <h6>Credit Card</h6>
                            </div>
                            <div class="payment-option" data-method="debit_card">
                                <i class="fas fa-money-check-alt"></i>
                                <h6>Debit Card</h6>
                            </div>
                            <div class="payment-option" data-method="paypal">
                                <i class="fab fa-paypal"></i>
                                <h6>PayPal</h6>
                            </div>
                            <div class="payment-option" data-method="bank_transfer">
                                <i class="fas fa-university"></i>
                                <h6>Bank Transfer</h6>
                            </div>
                        </div>
                        <input type="hidden" name="payment_method" id="payment_method" value="credit_card">
                    </div>

                    <!-- Card Details -->
                    <div class="card-details active" id="card_details">
                        <div class="form-group">
                            <label for="card_name" class="form-label">Cardholder Name</label>
                            <input type="text" class="form-control" id="card_name" name="card_name" 
                                   placeholder="John Doe" required>
                        </div>

                        <div class="form-group">
                            <label for="card_number" class="form-label">Card Number</label>
                            <input type="text" class="form-control" id="card_number" name="card_number" 
                                   placeholder="1234 5678 9012 3456" maxlength="19" required>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="expiry_date" class="form-label">Expiry Date</label>
                                <input type="text" class="form-control" id="expiry_date" name="expiry_date" 
                                       placeholder="MM/YY" maxlength="5" required>
                            </div>
                            <div class="form-group">
                                <label for="cvv" class="form-label">CVV</label>
                                <input type="text" class="form-control" id="cvv" name="cvv" 
                                       placeholder="123" maxlength="4" required>
                            </div>
                        </div>
                    </div>

                    <!-- Billing Information -->
                    <div class="billing-info">
                        <h5><i class="fas fa-map-marker-alt me-2"></i>Billing Address</h5>
                        <div class="form-group">
                            <label for="billing_address" class="form-label">Address</label>
                            <input type="text" class="form-control" id="billing_address" name="billing_address" 
                                   value="<?php echo htmlspecialchars($user['address'] ?? ''); ?>" required>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="billing_city" class="form-label">City</label>
                                <input type="text" class="form-control" id="billing_city" name="billing_city" 
                                       placeholder="Colombo" required>
                            </div>
                            <div class="form-group">
                                <label for="billing_postal" class="form-label">Postal Code</label>
                                <input type="text" class="form-control" id="billing_postal" name="billing_postal" 
                                       placeholder="00100" required>
                            </div>
                        </div>
                    </div>

                    <!-- Pay Now Button -->
                    <button type="submit" class="pay-now-btn">
                        <i class="fas fa-lock"></i>
                        Pay Now - Rs. <?php echo number_format($total_amount, 2); ?>
                    </button>

                    <div class="security-notice">
                        <i class="fas fa-shield-alt"></i>
                        <small>Your payment information is encrypted and secure</small>
                    </div>
                </form>
            </div>

            <!-- Order Summary -->
            <div class="order-summary-section">
                <div class="order-summary">
                    <h3><i class="fas fa-receipt"></i> Order Summary</h3>
                    
                    <?php if ($payment_type === 'booking' && $booking_details): ?>
                        <!-- Booking Summary -->
                        <div class="order-item">
                            <div class="item-info">
                                <h4>Pet Sitting Service</h4>
                                <p><?php echo htmlspecialchars($booking_details['petType'] . ' - ' . $booking_details['petName']); ?></p>
                                <p><?php echo date('M d-d, Y', strtotime($booking_details['checkInDate'])); ?></p>
                            </div>
                            <div class="item-price">Rs. <?php echo number_format($total_amount * 0.8, 2); ?></div>
                        </div>

                        <div class="order-item">
                            <div class="item-info">
                                <h4>Pet Sitter</h4>
                                <p><?php echo htmlspecialchars($booking_details['sitterName']); ?></p>
                            </div>
                            <div class="item-price">Rs. <?php echo number_format($booking_details['hourly_rate'], 2); ?>/hr</div>
                        </div>

                        <div class="order-item">
                            <div class="item-info">
                                <h4>Service Fee</h4>
                                <p>Platform fee</p>
                            </div>
                            <div class="item-price">Rs. <?php echo number_format($total_amount * 0.2, 2); ?></div>
                        </div>

                    <?php else: ?>
                        <!-- Cart Items Summary -->
                        <?php if (empty($order_items)): ?>
                            <div class="order-item">
                                <div class="item-info">
                                    <h4>Sample Pet Food</h4>
                                    <p>Premium Dog Food (5kg)</p>
                                </div>
                                <div class="item-price">Rs. 2,500.00</div>
                            </div>

                            <div class="order-item">
                                <div class="item-info">
                                    <h4>Pet Accessories</h4>
                                    <p>Collar & Leash Set</p>
                                </div>
                                <div class="item-price">Rs. 1,250.00</div>
                            </div>

                            <div class="order-item">
                                <div class="item-info">
                                    <h4>Service Fee</h4>
                                    <p>Platform fee</p>
                                </div>
                                <div class="item-price">Rs. 187.50</div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($order_items as $item): ?>
                                <div class="order-item">
                                    <div class="item-info">
                                        <h4><?php echo htmlspecialchars($item['name']); ?></h4>
                                        <p><?php echo htmlspecialchars($item['brand']); ?> (Qty: <?php echo $item['quantity']; ?>)</p>
                                    </div>
                                    <div class="item-price">Rs. <?php echo number_format($item['subtotal'], 2); ?></div>
                                </div>
                            <?php endforeach; ?>

                            <div class="order-item">
                                <div class="item-info">
                                    <h4>Service Fee</h4>
                                    <p>Platform fee</p>
                                </div>
                                <div class="item-price">Rs. <?php echo number_format($total_amount * 0.05, 2); ?></div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <div class="order-total">
                        <h3>Total</h3>
                        <div class="total-amount">Rs. <?php echo number_format($total_amount, 2); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const paymentOptions = document.querySelectorAll('.payment-option');
            const cardDetails = document.querySelectorAll('.card-details');
            const form = document.getElementById('paymentForm');
            const paymentMethodInput = document.getElementById('payment_method');

            // Payment method selection
            paymentOptions.forEach(option => {
                option.addEventListener('click', function() {
                    // Remove active class from all options
                    paymentOptions.forEach(opt => opt.classList.remove('active'));
                    cardDetails.forEach(details => details.classList.remove('active'));
                    
                    // Add active class to clicked option
                    this.classList.add('active');
                    const method = this.dataset.method;
                    paymentMethodInput.value = method;
                    
                    // Show appropriate form fields
                    if (method === 'credit_card' || method === 'debit_card') {
                        document.getElementById('card_details').classList.add('active');
                    }
                });
            });

            // Card number formatting
            const cardNumberInput = document.getElementById('card_number');
            if (cardNumberInput) {
                cardNumberInput.addEventListener('input', function(e) {
                    let value = e.target.value.replace(/\s/g, '').replace(/\D/g, '');
                    let formattedValue = value.replace(/(.{4})/g, '$1 ').trim();
                    if (formattedValue.length > 19) {
                        formattedValue = formattedValue.substr(0, 19);
                    }
                    e.target.value = formattedValue;
                });
            }

            // Expiry date formatting
            const expiryInput = document.getElementById('expiry_date');
            if (expiryInput) {
                expiryInput.addEventListener('input', function(e) {
                    let value = e.target.value.replace(/\D/g, '');
                    if (value.length >= 2) {
                        value = value.substring(0,2) + '/' + value.substring(2,4);
                    }
                    e.target.value = value;
                });
            }

            // CVV input restriction
            const cvvInput = document.getElementById('cvv');
            if (cvvInput) {
                cvvInput.addEventListener('input', function(e) {
                    e.target.value = e.target.value.replace(/\D/g, '');
                });
            }

            // Form submission
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Basic validation
                const requiredFields = form.querySelectorAll('[required]');
                let isValid = true;
                
                requiredFields.forEach(field => {
                    if (!field.value.trim()) {
                        isValid = false;
                        field.classList.add('is-invalid');
                    } else {
                        field.classList.remove('is-invalid');
                    }
                });
                
                if (isValid) {
                    // Show loading state
                    const submitBtn = form.querySelector('.pay-now-btn');
                    const originalText = submitBtn.innerHTML;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                    submitBtn.disabled = true;
                    
                    // Simulate processing
                    setTimeout(() => {
                        alert('Payment processed successfully!');
                        // Redirect to success page
                        window.location.href = 'payment_success.php';
                    }, 2000);
                } else {
                    alert('Please fill in all required fields.');
                }
            });
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
// Include footer
include_once 'includes/footer.php';

// Close database connection
$conn->close();
?>