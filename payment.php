<?php
// AJAX Payment Handler - Must be first, before any output
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_payment'])) {
    // Start session
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    // Set JSON header immediately
    header('Content-Type: application/json');
    
    // Include database connection
    require_once 'config/db_connect.php';
    
    try {
        // Check login
        if (!isset($_SESSION['user_id'])) {
            throw new Exception("You must be logged in to make a payment.");
        }

        $user_id = $_SESSION['user_id'];
        $cart_id = intval($_GET['cart_id']);

        // Start transaction
        $conn->autocommit(FALSE);
        
        // Get payment details
        $payment_method = trim($_POST['payment_method']);
        $cardholder_name = isset($_POST['cardholder_name']) ? trim($_POST['cardholder_name']) : 'Cash Payment';
        
        // Get cart and calculate total
        $cart_sql = "SELECT c.*, po.fullName FROM cart c 
                     JOIN pet_owner po ON c.userID = po.userID 
                     WHERE c.cartID = ? AND c.userID = ?";
        $cart_stmt = $conn->prepare($cart_sql);
        $cart_stmt->bind_param("ii", $cart_id, $user_id);
        $cart_stmt->execute();
        $cart_result = $cart_stmt->get_result();
        
        if ($cart_result->num_rows === 0) {
            throw new Exception("Cart not found.");
        }
        
        $cart = $cart_result->fetch_assoc();
        $cart_stmt->close();
        
        // Calculate total
        $total_sql = "SELECT SUM(quantity * price) as total FROM cart_items WHERE cartID = ?";
        $total_stmt = $conn->prepare($total_sql);
        $total_stmt->bind_param("i", $cart_id);
        $total_stmt->execute();
        $total_result = $total_stmt->get_result();
        $total_amount = $total_result->fetch_assoc()['total'] ?? 0;
        $total_stmt->close();
        
        if ($total_amount <= 0) {
            throw new Exception("Cart is empty.");
        }
        
        // Insert payment
        $payment_status = ($payment_method === 'cash_on_delivery') ? 'pending' : 'completed';
        $payment_sql = "INSERT INTO payment (cartID, amount, payment_method, payment_status, payment_date, cardholder_name) VALUES (?, ?, ?, ?, NOW(), ?)";
        $payment_stmt = $conn->prepare($payment_sql);
        $payment_stmt->bind_param("idsss", $cart_id, $total_amount, $payment_method, $payment_status, $cardholder_name);
        $payment_stmt->execute();
        $payment_stmt->close();
        
        // Update order status
        if ($cart['orderID']) {
            $order_status = ($payment_method === 'cash_on_delivery') ? 'pending' : 'paid';
            $update_order_sql = "UPDATE `order` SET status = ? WHERE orderID = ?";
            $update_order_stmt = $conn->prepare($update_order_sql);
            $update_order_stmt->bind_param("si", $order_status, $cart['orderID']);
            $update_order_stmt->execute();
            $update_order_stmt->close();
        }
        
        // Update stock for completed payments
        if ($payment_status === 'completed') {
            $stock_sql = "SELECT productID, quantity FROM cart_items WHERE cartID = ?";
            $stock_stmt = $conn->prepare($stock_sql);
            $stock_stmt->bind_param("i", $cart_id);
            $stock_stmt->execute();
            $stock_result = $stock_stmt->get_result();
            
            while ($stock_row = $stock_result->fetch_assoc()) {
                $update_stock_sql = "UPDATE pet_food_and_accessories SET stock = stock - ? WHERE productID = ?";
                $update_stock_stmt = $conn->prepare($update_stock_sql);
                $update_stock_stmt->bind_param("ii", $stock_row['quantity'], $stock_row['productID']);
                $update_stock_stmt->execute();
                $update_stock_stmt->close();
            }
            $stock_stmt->close();
        }
        
        $conn->commit();
        $conn->autocommit(TRUE);
        
        $message = ($payment_method === 'cash_on_delivery') ? 
                   "Order placed successfully! You will pay cash on delivery. Order ID: " . $cart['orderID'] :
                   "Payment completed successfully! Your order has been confirmed. Order ID: " . $cart['orderID'];
        
        echo json_encode(['success' => true, 'message' => $message, 'order_id' => $cart['orderID']]);
        exit();
        
    } catch (Exception $e) {
        if (isset($conn)) {
            $conn->rollback();
            $conn->autocommit(TRUE);
        }
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit();
    }
}

// Regular page processing starts here
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error_message'] = "You must be logged in to make a payment.";
    header("Location: login.php");
    exit();
}

// Check if cart_id is provided
if (!isset($_GET['cart_id']) || empty($_GET['cart_id'])) {
    $_SESSION['error_message'] = "Invalid payment request.";
    header("Location: cart.php");
    exit();
}

// Include database connection for regular page
require_once 'config/db_connect.php';

$user_id = $_SESSION['user_id'];
$cart_id = intval($_GET['cart_id']);

// Get cart details for display
$cart_sql = "SELECT c.*, po.fullName, po.email, po.address, po.contact FROM cart c 
             JOIN pet_owner po ON c.userID = po.userID 
             WHERE c.cartID = ? AND c.userID = ?";
$cart_stmt = $conn->prepare($cart_sql);
$cart_stmt->bind_param("ii", $cart_id, $user_id);
$cart_stmt->execute();
$cart_result = $cart_stmt->get_result();

if ($cart_result->num_rows === 0) {
    $_SESSION['error_message'] = "Cart not found.";
    header("Location: cart.php");
    exit();
}

$cart = $cart_result->fetch_assoc();
$cart_stmt->close();

// Get cart items
$items_sql = "SELECT ci.*, p.name, p.brand, p.image FROM cart_items ci 
              JOIN pet_food_and_accessories p ON ci.productID = p.productID 
              WHERE ci.cartID = ?";
$items_stmt = $conn->prepare($items_sql);
$items_stmt->bind_param("i", $cart_id);
$items_stmt->execute();
$items_result = $items_stmt->get_result();
$order_items = [];
$total_amount = 0;
while ($row = $items_result->fetch_assoc()) {
    $row['subtotal'] = $row['quantity'] * $row['price'];
    $total_amount += $row['subtotal'];
    $order_items[] = $row;
}
$items_stmt->close();

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

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            margin-bottom: 25px;
        }

        .back-link:hover {
            color: #4f46e5;
            text-decoration: none;
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

        .customer-info {
            background: #f8fafc;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
        }

        .customer-info h4 {
            color: #1e293b;
            font-weight: 600;
            margin-bottom: 15px;
        }

        .payment-options {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
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
            font-size: 0.9rem;
        }

        .card-details {
            display: none;
            background: #e8f0fe;
            border-radius: 15px;
            padding: 25px;
            margin-top: 20px;
            border: 1px solid #dadce0;
        }

        .card-details.active {
            display: block;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #374151;
        }

        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 14px;
            box-sizing: border-box;
        }

        .form-group input:focus {
            border-color: #667eea;
            outline: none;
        }

        .form-row {
            display: flex;
            gap: 15px;
        }

        .form-row .form-group {
            flex: 1;
        }

        .pay-btn {
            width: 100%;
            background: linear-gradient(135deg, #10b981, #047857);
            color: white;
            padding: 15px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            margin-top: 20px;
        }

        .pay-btn:hover {
            background: linear-gradient(135deg, #047857, #065f46);
        }

        .order-summary h3 {
            font-size: 1.3rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f1f5f9;
        }

        .order-item {
            display: flex;
            justify-content: space-between;
            padding: 15px 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .order-total {
            border-top: 2px solid #e5e7eb;
            padding-top: 20px;
            margin-top: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .total-amount {
            font-size: 1.5rem;
            font-weight: 700;
            color: #10b981;
        }

        .error-message {
            background: #fef2f2;
            color: #dc2626;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #fecaca;
        }

        .method-description {
            display: none;
            background: #f0f9ff;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
            border: 1px solid #bae6fd;
        }

        .method-description.active {
            display: block;
        }

        @media (max-width: 768px) {
            .payment-content {
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
        <div class="payment-header">
            <h1><i class="fas fa-credit-card"></i> Secure Payment</h1>
            <p>Complete your order with our secure payment system</p>
        </div>

        <div class="payment-content">
            <div class="payment-form-section">
                <a href="cart.php" class="back-link">
                    <i class="fas fa-arrow-left"></i> Back to Cart
                </a>
                
                <?php if (isset($error_message)): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error_message); ?>
                </div>
                <?php endif; ?>
                
                <div class="customer-info">
                    <h4><i class="fas fa-user"></i> Customer Information</h4>
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($cart['fullName']); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($cart['email']); ?></p>
                    <p><strong>Address:</strong> <?php echo htmlspecialchars($cart['address'] ?: 'Not provided'); ?></p>
                    <p><strong>Contact:</strong> <?php echo htmlspecialchars($cart['contact'] ?: 'Not provided'); ?></p>
                </div>
                
                <h2 class="section-title">
                    <i class="fas fa-lock"></i> Payment Details
                </h2>

                <form method="POST" id="paymentForm">
                    <!-- Payment Method Selection -->
                    <div class="form-group">
                        <label>Select Payment Method</label>
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
                            <div class="payment-option" data-method="cash_on_delivery">
                                <i class="fas fa-money-bill-wave"></i>
                                <h6>Cash on Delivery</h6>
                            </div>
                        </div>
                        <input type="hidden" name="payment_method" id="payment_method" value="credit_card">
                    </div>

                    <!-- Card Details -->
                    <div class="card-details active" id="card_details">
                        <div class="form-group">
                            <label for="cardholder_name">Cardholder Name</label>
                            <input type="text" id="cardholder_name" name="cardholder_name" placeholder="Enter cardholder name" required>
                        </div>

                        <div class="form-group">
                            <label for="card_number">Card Number</label>
                            <input type="text" id="card_number" name="card_number" placeholder="XXXX XXXX XXXX XXXX" maxlength="19" required>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="expire_date">Expire Date</label>
                                <input type="text" id="expire_date" name="expire_date" placeholder="MM/YY" maxlength="5" required>
                            </div>
                            <div class="form-group">
                                <label for="cvc">CVC</label>
                                <input type="text" id="cvc" name="cvc" placeholder="123" maxlength="4" required>
                            </div>
                        </div>
                    </div>

                    <!-- Method Descriptions -->
                    <div class="method-description" id="paypal_desc">
                        <h6><i class="fab fa-paypal"></i> PayPal Payment</h6>
                        <p>You will be redirected to PayPal to complete your payment securely.</p>
                    </div>

                    <div class="method-description" id="bank_desc">
                        <h6><i class="fas fa-university"></i> Bank Transfer</h6>
                        <p>Bank details will be provided after order confirmation. Payment must be completed within 24 hours.</p>
                    </div>

                    <div class="method-description" id="cod_desc">
                        <h6><i class="fas fa-money-bill-wave"></i> Cash on Delivery</h6>
                        <p>Pay with cash when your order is delivered to your doorstep. No advance payment required.</p>
                    </div>

                    <button type="submit" name="pay_now" class="pay-btn">
                        <i class="fas fa-lock"></i> Complete Payment - Rs.<?php echo number_format($total_amount, 2); ?>
                    </button>
                </form>
            </div>
            
            <div class="order-summary-section">
                <h3><i class="fas fa-receipt"></i> Order Summary</h3>
                
                <?php foreach ($order_items as $item): ?>
                <div class="order-item">
                    <div>
                        <strong><?php echo htmlspecialchars($item['name']); ?></strong><br>
                        <small><?php echo htmlspecialchars($item['brand']); ?> • Qty: <?php echo $item['quantity']; ?></small>
                    </div>
                    <div>Rs.<?php echo number_format($item['subtotal'], 2); ?></div>
                </div>
                <?php endforeach; ?>
                
                <div class="order-item">
                    <strong>Shipping</strong>
                    <span style="color: #10b981; font-weight: bold;">FREE</span>
                </div>
                
                <div class="order-total">
                    <h4>Total:</h4>
                    <div class="total-amount">Rs.<?php echo number_format($total_amount, 2); ?></div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const paymentOptions = document.querySelectorAll('.payment-option');
            const cardDetails = document.getElementById('card_details');
            const methodDescriptions = document.querySelectorAll('.method-description');
            const paymentMethodInput = document.getElementById('payment_method');
            const form = document.getElementById('paymentForm');

            // Payment method selection
            paymentOptions.forEach(option => {
                option.addEventListener('click', function() {
                    paymentOptions.forEach(opt => opt.classList.remove('active'));
                    methodDescriptions.forEach(desc => desc.classList.remove('active'));
                    
                    this.classList.add('active');
                    const method = this.dataset.method;
                    paymentMethodInput.value = method;
                    
                    // Show/hide card details
                    if (method === 'credit_card' || method === 'debit_card') {
                        cardDetails.classList.add('active');
                        cardDetails.querySelectorAll('input').forEach(input => {
                            input.setAttribute('required', 'required');
                        });
                    } else {
                        cardDetails.classList.remove('active');
                        cardDetails.querySelectorAll('input').forEach(input => {
                            input.removeAttribute('required');
                        });
                    }
                    
                    // Show method description
                    if (method === 'paypal') document.getElementById('paypal_desc').classList.add('active');
                    if (method === 'bank_transfer') document.getElementById('bank_desc').classList.add('active');
                    if (method === 'cash_on_delivery') document.getElementById('cod_desc').classList.add('active');
                });
            });

            // Card formatting
            const cardNumberInput = document.getElementById('card_number');
            if (cardNumberInput) {
                cardNumberInput.addEventListener('input', function(e) {
                    let value = e.target.value.replace(/\D/g, '');
                    let formattedValue = value.replace(/(\d{4})(?=\d)/g, '$1 ');
                    if (formattedValue.length <= 19) {
                        e.target.value = formattedValue;
                    }
                });
            }

            const expireDateInput = document.getElementById('expire_date');
            if (expireDateInput) {
                expireDateInput.addEventListener('input', function(e) {
                    let value = e.target.value.replace(/\D/g, '');
                    if (value.length >= 2) {
                        value = value.substring(0, 2) + '/' + value.substring(2, 4);
                    }
                    e.target.value = value;
                });
            }

            const cvcInput = document.getElementById('cvc');
            if (cvcInput) {
                cvcInput.addEventListener('input', function(e) {
                    e.target.value = e.target.value.replace(/\D/g, '');
                });
            }

            // Form submission with AJAX
            form.addEventListener('submit', function(e) {
                e.preventDefault(); // Prevent default form submission
                
                const method = paymentMethodInput.value;
                
                if (method === 'credit_card' || method === 'debit_card') {
                    const cardName = document.getElementById('cardholder_name').value.trim();
                    const cardNumber = document.getElementById('card_number').value.replace(/\s/g, '');
                    const expireDate = document.getElementById('expire_date').value;
                    const cvc = document.getElementById('cvc').value;

                    if (!cardName || cardNumber.length < 13 || !/^\d{2}\/\d{2}$/.test(expireDate) || cvc.length < 3) {
                        alert('Please fill in all card details correctly.');
                        return;
                    }
                }

                // Confirm payment
                let confirmMessage = '';
                if (method === 'cash_on_delivery') {
                    confirmMessage = 'Confirm order with Cash on Delivery payment?';
                } else {
                    confirmMessage = `Confirm payment of Rs.<?php echo number_format($total_amount, 2); ?>?`;
                }
                
                if (!confirm(confirmMessage)) {
                    return;
                }

                // Show loading
                const btn = this.querySelector('.pay-btn');
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing Payment...';
                btn.disabled = true;

                // Submit form with AJAX
                const formData = new FormData(this);
                formData.append('ajax_payment', '1');

                fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                })
                .then(response => {
                    console.log('Response status:', response.status);
                    return response.text(); // Get as text first to see what we're getting
                })
                .then(text => {
                    console.log('Response text:', text);
                    try {
                        const data = JSON.parse(text);
                        if (data.success) {
                            alert('✅ ' + data.message);
                            window.location.href = 'user/orders.php';
                        } else {
                            alert('❌ Payment failed: ' + data.message);
                            btn.innerHTML = '<i class="fas fa-lock"></i> Complete Payment - Rs.<?php echo number_format($total_amount, 2); ?>';
                            btn.disabled = false;
                        }
                    } catch (parseError) {
                        console.error('JSON parse error:', parseError);
                        console.error('Response was:', text);
                        alert('❌ Payment failed: Server returned invalid response. Check console for details.');
                        btn.innerHTML = '<i class="fas fa-lock"></i> Complete Payment - Rs.<?php echo number_format($total_amount, 2); ?>';
                        btn.disabled = false;
                    }
                })
                .catch(error => {
                    console.error('Fetch error:', error);
                    alert('❌ Payment failed: Network error. Please check your connection.');
                    btn.innerHTML = '<i class="fas fa-lock"></i> Complete Payment - Rs.<?php echo number_format($total_amount, 2); ?>';
                    btn.disabled = false;
                });
            });
        });
    </script>
</body>
</html>

<?php
// Include footer
include_once 'includes/footer.php';

// Close database connection
$conn->close();
?>