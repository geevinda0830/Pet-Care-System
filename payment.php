<?php
// Handle AJAX payment processing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_payment'])) {
    header('Content-Type: application/json');
    
    require_once 'config/db_connect.php';
    $conn->autocommit(FALSE);
    
    try {
        session_start();
        $user_id = $_SESSION['user_id'] ?? null;
        
        if (!$user_id) {
            throw new Exception("User not logged in.");
        }
        
        $payment_method = htmlspecialchars($_POST['payment_method'] ?? '');
        $cart_id = isset($_POST['cart_id']) ? intval($_POST['cart_id']) : null;
        $booking_id = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : null;
        
        if (!$cart_id && !$booking_id) {
            throw new Exception("Invalid payment request.");
        }
        
        $total_amount = 0;
        
        if ($cart_id) {
            // Cart payment
            $total_sql = "SELECT SUM(ci.quantity * ci.price) as total 
                         FROM cart_items ci WHERE ci.cartID = ?";
            $total_stmt = $conn->prepare($total_sql);
            $total_stmt->bind_param("i", $cart_id);
            $total_stmt->execute();
            $total_result = $total_stmt->get_result();
            $total_amount = $total_result->fetch_assoc()['total'] ?? 0;
            $total_stmt->close();
            
        } else if ($booking_id) {
            // FIXED: Get booking details and calculate total cost
            $booking_sql = "SELECT b.*, ps.price as hourly_rate 
                           FROM booking b 
                           JOIN pet_sitter ps ON b.sitterID = ps.userID
                           WHERE b.bookingID = ? AND b.userID = ?";
            $booking_stmt = $conn->prepare($booking_sql);
            $booking_stmt->bind_param("ii", $booking_id, $user_id);
            $booking_stmt->execute();
            $booking_result = $booking_stmt->get_result();
            
            if ($booking_result->num_rows === 0) {
                throw new Exception("Booking not found.");
            }
            
            $booking = $booking_result->fetch_assoc();
            
            // Calculate total cost based on duration
            $check_in = new DateTime($booking['checkInDate'] . ' ' . $booking['checkInTime']);
            $check_out = new DateTime($booking['checkOutDate'] . ' ' . $booking['checkOutTime']);
            $interval = $check_in->diff($check_out);
            $total_hours = $interval->days * 24 + $interval->h + ($interval->i / 60);
            $total_amount = $total_hours * $booking['hourly_rate'];
            
            $booking_stmt->close();
        }
        
        if ($total_amount <= 0) {
            throw new Exception("Invalid amount.");
        }
        
        // FIXED: Insert payment without status column
        if ($cart_id) {
            $payment_sql = "INSERT INTO payment (amount, paymentDate, payment_method, cartID) VALUES (?, NOW(), ?, ?)";
            $payment_stmt = $conn->prepare($payment_sql);
            $payment_stmt->bind_param("dsi", $total_amount, $payment_method, $cart_id);
        } else {
            $payment_sql = "INSERT INTO payment (amount, paymentDate, payment_method, bookingID) VALUES (?, NOW(), ?, ?)";
            $payment_stmt = $conn->prepare($payment_sql);
            $payment_stmt->bind_param("dsi", $total_amount, $payment_method, $booking_id);
        }
        
        if (!$payment_stmt->execute()) {
            throw new Exception("Payment failed: " . $payment_stmt->error);
        }
        
        // Update booking status to Completed after successful payment
        if ($booking_id) {
            $update_booking_sql = "UPDATE booking SET status = 'Completed' WHERE bookingID = ?";
            $update_booking_stmt = $conn->prepare($update_booking_sql);
            $update_booking_stmt->bind_param("i", $booking_id);
            $update_booking_stmt->execute();
            $update_booking_stmt->close();
        }
        
        $payment_stmt->close();
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Payment processed successfully!',
            'redirect' => $cart_id ? 'user/orders.php' : 'user/bookings.php'
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
    
    $conn->autocommit(TRUE);
    exit();
}

// Regular page load
session_start();
require_once 'config/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    $_SESSION['error_message'] = "Please log in to continue.";
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$cart_id = isset($_GET['cart_id']) ? intval($_GET['cart_id']) : null;
$booking_id = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : null;

if (!$cart_id && !$booking_id) {
    $_SESSION['error_message'] = "Invalid payment request.";
    header("Location: cart.php");
    exit();
}

$payment_type = $cart_id ? 'cart' : 'booking';
$payment_items = [];
$total_amount = 0;
$customer_info = [];

if ($cart_id) {
    // Get cart details
    $cart_sql = "SELECT c.*, po.fullName, po.email, po.address, po.contact 
                 FROM cart c 
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
    $customer_info = $cart;
    $cart_stmt->close();

    // Get cart items
    $items_sql = "SELECT ci.*, p.name, p.brand, p.image 
                  FROM cart_items ci 
                  JOIN pet_food_and_accessories p ON ci.productID = p.productID 
                  WHERE ci.cartID = ?";
    $items_stmt = $conn->prepare($items_sql);
    $items_stmt->bind_param("i", $cart_id);
    $items_stmt->execute();
    $items_result = $items_stmt->get_result();
    
    while ($row = $items_result->fetch_assoc()) {
        $row['subtotal'] = $row['quantity'] * $row['price'];
        $total_amount += $row['subtotal'];
        $payment_items[] = $row;
    }
    $items_stmt->close();
    
} else if ($booking_id) {
    // FIXED: Get booking details and calculate total cost
    $booking_sql = "SELECT b.*, pp.petName, ps.fullName as sitter_name, ps.service as service_name, 
                    ps.price as hourly_rate, ps.image, po.fullName, po.email, po.address, po.contact
                    FROM booking b 
                    JOIN pet_sitter ps ON b.sitterID = ps.userID
                    JOIN pet_profile pp ON b.petID = pp.petID
                    JOIN pet_owner po ON b.userID = po.userID
                    WHERE b.bookingID = ? AND b.userID = ?";
    $booking_stmt = $conn->prepare($booking_sql);
    $booking_stmt->bind_param("ii", $booking_id, $user_id);
    $booking_stmt->execute();
    $booking_result = $booking_stmt->get_result();

    if ($booking_result->num_rows === 0) {
        $_SESSION['error_message'] = "Booking not found.";
        header("Location: user/bookings.php");
        exit();
    }

    $booking = $booking_result->fetch_assoc();
    $customer_info = $booking;
    
    // Calculate total cost based on duration
    $check_in = new DateTime($booking['checkInDate'] . ' ' . $booking['checkInTime']);
    $check_out = new DateTime($booking['checkOutDate'] . ' ' . $booking['checkOutTime']);
    $interval = $check_in->diff($check_out);
    $total_hours = $interval->days * 24 + $interval->h + ($interval->i / 60);
    $total_amount = $total_hours * $booking['hourly_rate'];
    
    $payment_items[] = [
        'name' => $booking['service_name'] ?: 'Pet Sitting Service',
        'brand' => 'Pet Sitting (' . number_format($total_hours, 1) . ' hours)',
        'image' => $booking['image'] ?: 'assets/images/default-service.jpg',
        'quantity' => 1,
        'price' => $total_amount,
        'subtotal' => $total_amount
    ];
    $booking_stmt->close();
}

include_once 'includes/header.php';
?>

<!-- Payment Header -->
<section class="page-header-modern">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <div class="header-content">
                    <div class="header-badge">
                        <i class="fas fa-credit-card me-2"></i>Secure Payment Gateway
                    </div>
                    <h1 class="page-title">Complete Payment</h1>
                    <p class="page-subtitle">
                        <?php if ($payment_type === 'cart'): ?>
                            Secure checkout for your pet care products
                        <?php else: ?>
                            Payment for your pet service booking
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            <div class="col-lg-4 text-lg-end">
                <div class="page-actions">
                    <?php if ($payment_type === 'cart'): ?>
                        <a href="cart.php" class="btn btn-glass">
                            <i class="fas fa-arrow-left me-2"></i>Back to Cart
                        </a>
                    <?php else: ?>
                        <a href="user/bookings.php" class="btn btn-glass">
                            <i class="fas fa-arrow-left me-2"></i>Back to Bookings
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Payment Content -->
<section class="payment-content">
    <div class="container">
        <div class="row">
            <!-- Payment Form -->
            <div class="col-lg-8">
                <div class="payment-card">
                    <!-- Customer Information -->
                    <div class="section-header">
                        <h3 class="section-title">
                            <i class="fas fa-user me-2"></i>Customer Information
                        </h3>
                    </div>
                    <div class="section-content">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="detail-item">
                                    <label>Full Name</label>
                                    <p><?php echo htmlspecialchars($customer_info['fullName'] ?? ''); ?></p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="detail-item">
                                    <label>Email</label>
                                    <p><?php echo htmlspecialchars($customer_info['email'] ?? ''); ?></p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="detail-item">
                                    <label>Phone</label>
                                    <p><?php echo htmlspecialchars($customer_info['contact'] ?? ''); ?></p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="detail-item">
                                    <label>Address</label>
                                    <p><?php echo htmlspecialchars($customer_info['address'] ?? ''); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Method -->
                    <div class="section-header mt-4">
                        <h3 class="section-title">
                            <i class="fas fa-credit-card me-2"></i>Payment Method
                        </h3>
                    </div>
                    <div class="section-content">
                        <div class="payment-methods">
                            <div class="payment-option">
                                <input type="radio" name="payment_method" value="credit_card" id="credit_card" checked>
                                <label for="credit_card" class="payment-label">
                                    <i class="fas fa-credit-card"></i>
                                    <span>Credit/Debit Card</span>
                                </label>
                            </div>
                            <div class="payment-option">
                                <input type="radio" name="payment_method" value="paypal" id="paypal">
                                <label for="paypal" class="payment-label">
                                    <i class="fab fa-paypal"></i>
                                    <span>PayPal</span>
                                </label>
                            </div>
                            <?php if ($payment_type === 'cart'): ?>
                            <div class="payment-option">
                                <input type="radio" name="payment_method" value="cash_on_delivery" id="cash_on_delivery">
                                <label for="cash_on_delivery" class="payment-label">
                                    <i class="fas fa-truck"></i>
                                    <span>Cash on Delivery</span>
                                </label>
                            </div>
                            <?php else: ?>
                            <div class="payment-option">
                                <input type="radio" name="payment_method" value="pay_on_service" id="pay_on_service">
                                <label for="pay_on_service" class="payment-label">
                                    <i class="fas fa-handshake"></i>
                                    <span>Pay on Service</span>
                                </label>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Credit Card Details Form -->
                        <div id="card-details" class="card-details-form mt-4">
                            <h4>Card Details</h4>
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label>Card Number</label>
                                    <input type="text" class="form-control" placeholder="1234 5678 9012 3456" maxlength="19">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label>Expiry Date</label>
                                    <input type="text" class="form-control" placeholder="MM/YY" maxlength="5">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label>CVV</label>
                                    <input type="text" class="form-control" placeholder="123" maxlength="4">
                                </div>
                                <div class="col-md-12 mb-3">
                                    <label>Cardholder Name</label>
                                    <input type="text" class="form-control" placeholder="John Doe">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Order Summary -->
            <div class="col-lg-4">
                <div class="order-summary">
                    <h3 class="summary-title">
                        <i class="fas fa-receipt me-2"></i>Order Summary
                    </h3>
                    
                    <div class="summary-items">
                        <?php foreach ($payment_items as $item): ?>
                            <div class="summary-item">
                                <div class="item-image">
                                    <img src="<?php echo htmlspecialchars($item['image'] ?? 'assets/images/default.jpg'); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                                </div>
                                <div class="item-details">
                                    <h4><?php echo htmlspecialchars($item['name']); ?></h4>
                                    <p><?php echo htmlspecialchars($item['brand'] ?? ''); ?></p>
                                    <div class="item-price">
                                        <span class="quantity">Qty: <?php echo $item['quantity']; ?></span>
                                        <span class="price">LKR <?php echo number_format($item['subtotal'], 2); ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="summary-total">
                        <div class="total-line">
                            <span>Total Amount:</span>
                            <span class="total-amount">LKR <?php echo number_format($total_amount, 2); ?></span>
                        </div>
                    </div>
                    
                    <button type="button" class="btn btn-primary btn-lg w-100 pay-now-btn">
                        <i class="fas fa-lock me-2"></i>Pay Now
                    </button>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
.payment-content {
    padding: 60px 0;
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
}

.payment-card, .order-summary {
    background: white;
    border-radius: 20px;
    padding: 30px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    margin-bottom: 30px;
}

.section-header {
    border-bottom: 2px solid #f1f3f4;
    padding-bottom: 15px;
    margin-bottom: 20px;
}

.section-title {
    color: #2d3748;
    font-weight: 600;
    margin: 0;
}

.detail-item {
    margin-bottom: 20px;
}

.detail-item label {
    font-weight: 600;
    color: #4a5568;
    margin-bottom: 5px;
    display: block;
}

.detail-item p {
    color: #2d3748;
    margin: 0;
    padding: 8px 0;
}

.payment-methods {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.payment-option input[type="radio"] {
    display: none;
}

.payment-label {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 20px;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.3s ease;
    background: white;
}

.payment-label:hover {
    border-color: #667eea;
    background: #f7fafc;
}

.payment-option input[type="radio"]:checked + .payment-label {
    border-color: #667eea;
    background: linear-gradient(135deg, #667eea20, #764ba220);
}

.payment-label i {
    font-size: 24px;
    color: #667eea;
    width: 30px;
}

.card-details-form {
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    padding: 20px;
    background: #f8f9fa;
}

.card-details-form h4 {
    margin-bottom: 20px;
    color: #2d3748;
}

.card-details-form.hidden {
    display: none;
}

.summary-title {
    color: #2d3748;
    font-weight: 600;
    margin-bottom: 25px;
}

.summary-item {
    display: flex;
    gap: 15px;
    padding: 15px 0;
    border-bottom: 1px solid #f1f3f4;
}

.item-image img {
    width: 60px;
    height: 60px;
    object-fit: cover;
    border-radius: 8px;
}

.item-details h4 {
    font-size: 16px;
    font-weight: 600;
    color: #2d3748;
    margin: 0 0 5px 0;
}

.item-details p {
    color: #718096;
    margin: 0 0 10px 0;
    font-size: 14px;
}

.item-price {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.quantity {
    color: #718096;
    font-size: 14px;
}

.price {
    font-weight: 600;
    color: #2d3748;
}

.summary-total {
    padding: 20px 0;
    border-top: 2px solid #f1f3f4;
    margin-top: 20px;
}

.total-line {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.total-amount {
    font-size: 24px;
    font-weight: 700;
    color: #667eea;
}

.pay-now-btn {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    border-radius: 12px;
    padding: 15px;
    font-weight: 600;
    font-size: 18px;
    margin-top: 20px;
    transition: all 0.3s ease;
}

.pay-now-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
}

.page-header-modern {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 80px 0;
}

.header-badge {
    background: rgba(255,255,255,0.2);
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 14px;
    margin-bottom: 15px;
    display: inline-block;
}

.page-title {
    font-size: 48px;
    font-weight: 700;
    margin: 0 0 15px 0;
}

.page-subtitle {
    font-size: 18px;
    opacity: 0.9;
    margin: 0;
}

.btn-glass {
    background: rgba(255,255,255,0.2);
    border: 1px solid rgba(255,255,255,0.3);
    color: white;
    border-radius: 8px;
    padding: 10px 20px;
    text-decoration: none;
    transition: all 0.3s ease;
}

.btn-glass:hover {
    background: rgba(255,255,255,0.3);
    color: white;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const paymentMethods = document.querySelectorAll('input[name="payment_method"]');
    const cardDetails = document.getElementById('card-details');
    const payButton = document.querySelector('.pay-now-btn');
    
    // Toggle card details visibility
    paymentMethods.forEach(method => {
        method.addEventListener('change', function() {
            if (this.value === 'credit_card' && this.checked) {
                cardDetails.classList.remove('hidden');
            } else {
                cardDetails.classList.add('hidden');
            }
        });
    });
    
    // Initially show card details
    cardDetails.classList.remove('hidden');
    
    // Format card number input
    const cardNumberInput = cardDetails.querySelector('input[placeholder*="1234"]');
    if (cardNumberInput) {
        cardNumberInput.addEventListener('input', function() {
            let value = this.value.replace(/\s/g, '');
            let formattedValue = value.match(/.{1,4}/g)?.join(' ') || value;
            this.value = formattedValue;
        });
    }
    
    // Format expiry date
    const expiryInput = cardDetails.querySelector('input[placeholder="MM/YY"]');
    if (expiryInput) {
        expiryInput.addEventListener('input', function() {
            let value = this.value.replace(/\D/g, '');
            if (value.length >= 2) {
                value = value.slice(0, 2) + '/' + value.slice(2, 4);
            }
            this.value = value;
        });
    }
    
    payButton.addEventListener('click', function() {
        const selectedPaymentMethod = document.querySelector('input[name="payment_method"]:checked');
        
        if (!selectedPaymentMethod) {
            alert('Please select a payment method.');
            return;
        }
        
        payButton.disabled = true;
        payButton.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';
        
        const formData = new FormData();
        formData.append('ajax_payment', '1');
        formData.append('payment_method', selectedPaymentMethod.value);
        <?php if ($cart_id): ?>
        formData.append('cart_id', '<?php echo $cart_id; ?>');
        <?php endif; ?>
        <?php if ($booking_id): ?>
        formData.append('booking_id', '<?php echo $booking_id; ?>');
        <?php endif; ?>
        
        fetch('payment.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                window.location.href = data.redirect;
            } else {
                alert('Payment failed: ' + data.message);
                payButton.disabled = false;
                payButton.innerHTML = '<i class="fas fa-lock me-2"></i>Pay Now';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while processing payment. Please try again.');
            payButton.disabled = false;
            payButton.innerHTML = '<i class="fas fa-lock me-2"></i>Pay Now';
        });
    });
});
</script>

<?php include_once 'includes/footer.php'; ?>