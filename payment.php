<?php
// AJAX Payment Handler - Must be first, before any output
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_payment'])) {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    header('Content-Type: application/json');
    require_once 'config/db_connect.php';
    
    try {
        if (!isset($_SESSION['user_id'])) {
            throw new Exception("You must be logged in to make a payment.");
        }

        $user_id = $_SESSION['user_id'];
        $payment_method_raw = trim($_POST['payment_method']);
        
        // Shorten payment method names to fit database column
        $payment_method_map = [
            'credit_card' => 'card',
            'paypal' => 'paypal',
            'bank_transfer' => 'bank',
            'cash_on_delivery' => 'cod'
        ];
        $payment_method = $payment_method_map[$payment_method_raw] ?? 'card';
        
        // Determine payment type (cart or booking)
        $cart_id = isset($_GET['cart_id']) ? intval($_GET['cart_id']) : null;
        $booking_id = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : null;
        
        if (!$cart_id && !$booking_id) {
            throw new Exception("Invalid payment request.");
        }

        $conn->autocommit(FALSE);
        
        $total_amount = 0;
        
        if ($cart_id) {
            // Cart payment
            $cart_sql = "SELECT c.*, po.fullName, po.email, po.address, po.contact FROM cart c 
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
            
        } else if ($booking_id) {
            // Booking payment
            $booking_sql = "SELECT b.*, ps.price FROM booking b 
                           JOIN pet_service ps ON b.serviceID = ps.serviceID 
                           WHERE b.bookingID = ? AND b.userID = ?";
            $booking_stmt = $conn->prepare($booking_sql);
            $booking_stmt->bind_param("ii", $booking_id, $user_id);
            $booking_stmt->execute();
            $booking_result = $booking_stmt->get_result();
            
            if ($booking_result->num_rows === 0) {
                throw new Exception("Booking not found.");
            }
            
            $booking = $booking_result->fetch_assoc();
            $total_amount = $booking['price'];
            $booking_stmt->close();
        }
        
        if ($total_amount <= 0) {
            throw new Exception("Invalid amount.");
        }
        
        // Insert payment - FIXED: single character status
        $payment_status = ($payment_method_raw === 'cash_on_delivery') ? 'P' : 'C';
        $payment_sql = "INSERT INTO payment (amount, paymentDate, payment_method, status, cartID, bookingID) VALUES (?, NOW(), ?, ?, ?, ?)";
        $payment_stmt = $conn->prepare($payment_sql);
        $payment_stmt->bind_param("dssii", $total_amount, $payment_method, $payment_status, $cart_id, $booking_id);
        $payment_stmt->execute();
        $payment_stmt->close();
        
        // Update order/booking status
        if ($cart_id && isset($cart['orderID']) && $cart['orderID']) {
            $order_status = ($payment_method === 'cash_on_delivery') ? 'pending' : 'paid';
            $update_order_sql = "UPDATE `order` SET status = ? WHERE orderID = ?";
            $update_order_stmt = $conn->prepare($update_order_sql);
            $update_order_stmt->bind_param("si", $order_status, $cart['orderID']);
            $update_order_stmt->execute();
            $update_order_stmt->close();
        }
        
        if ($booking_id) {
            $booking_status = ($payment_method === 'cash_on_delivery') ? 'confirmed' : 'paid';
            $update_booking_sql = "UPDATE booking SET status = ? WHERE bookingID = ?";
            $update_booking_stmt = $conn->prepare($update_booking_sql);
            $update_booking_stmt->bind_param("si", $booking_status, $booking_id);
            $update_booking_stmt->execute();
            $update_booking_stmt->close();
        }
        
        // Update stock for completed cart payments
        if ($cart_id && $payment_status === 'Completed') {
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
                   "Order placed successfully! You will pay cash on delivery." :
                   "Payment completed successfully! Your order has been confirmed.";
        
        echo json_encode(['success' => true, 'message' => $message]);
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

// Include database connection
require_once 'config/db_connect.php';

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
    // Get booking details
    $booking_sql = "SELECT b.*, ps.name as service_name, ps.price, ps.image, po.fullName, po.email, po.address, po.contact
                    FROM booking b 
                    JOIN pet_service ps ON b.serviceID = ps.serviceID 
                    JOIN pet_owner po ON b.userID = po.userID
                    WHERE b.bookingID = ? AND b.userID = ?";
    $booking_stmt = $conn->prepare($booking_sql);
    $booking_stmt->bind_param("ii", $booking_id, $user_id);
    $booking_stmt->execute();
    $booking_result = $booking_stmt->get_result();

    if ($booking_result->num_rows === 0) {
        $_SESSION['error_message'] = "Booking not found.";
        header("Location: bookings.php");
        exit();
    }

    $booking = $booking_result->fetch_assoc();
    $customer_info = $booking;
    $total_amount = $booking['price'];
    
    // Format booking as payment item
    $payment_items[] = [
        'name' => $booking['service_name'],
        'brand' => 'Pet Service',
        'image' => $booking['image'],
        'quantity' => 1,
        'price' => $booking['price'],
        'subtotal' => $booking['price']
    ];
    $booking_stmt->close();
}

// Include header
include_once 'includes/header.php';
?>

<!-- Payment Header -->
<section class="page-header-modern">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <div class="header-content">
                    <div class="header-badge">
                        <i class="fas fa-credit-card me-2"></i>
                        Secure Payment Gateway
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
                        <a href="bookings.php" class="btn btn-glass">
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
                            <i class="fas fa-user"></i>
                            Customer Information
                        </h3>
                    </div>
                    
                    <div class="customer-details">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="detail-item">
                                    <label>Full Name</label>
                                    <p><?php echo htmlspecialchars($customer_info['fullName']); ?></p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="detail-item">
                                    <label>Email</label>
                                    <p><?php echo htmlspecialchars($customer_info['email']); ?></p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="detail-item">
                                    <label>Contact</label>
                                    <p><?php echo htmlspecialchars($customer_info['contact'] ?: 'Not provided'); ?></p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="detail-item">
                                    <label>Address</label>
                                    <p><?php echo htmlspecialchars($customer_info['address'] ?: 'Not provided'); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Methods -->
                    <div class="section-header mt-4">
                        <h3 class="section-title">
                            <i class="fas fa-credit-card"></i>
                            Payment Method
                        </h3>
                    </div>

                    <div id="message-container"></div>

                    <form id="payment-form">
                        <div class="payment-methods">
                            <div class="payment-option" data-method="credit_card">
                                <input type="radio" name="payment_method" value="credit_card" id="credit_card">
                                <label for="credit_card">
                                    <div class="method-icon">
                                        <i class="fas fa-credit-card"></i>
                                    </div>
                                    <div class="method-info">
                                        <h6>Credit/Debit Card</h6>
                                        <p>Pay securely with your card</p>
                                    </div>
                                </label>
                            </div>

                            <div class="payment-option" data-method="paypal">
                                <input type="radio" name="payment_method" value="paypal" id="paypal">
                                <label for="paypal">
                                    <div class="method-icon paypal">
                                        <i class="fab fa-paypal"></i>
                                    </div>
                                    <div class="method-info">
                                        <h6>PayPal</h6>
                                        <p>Pay with your PayPal account</p>
                                    </div>
                                </label>
                            </div>

                            <div class="payment-option" data-method="bank_transfer">
                                <input type="radio" name="payment_method" value="bank_transfer" id="bank_transfer">
                                <label for="bank_transfer">
                                    <div class="method-icon bank">
                                        <i class="fas fa-university"></i>
                                    </div>
                                    <div class="method-info">
                                        <h6>Bank Transfer</h6>
                                        <p>Direct bank transfer</p>
                                    </div>
                                </label>
                            </div>

                            <div class="payment-option" data-method="cash_on_delivery">
                                <input type="radio" name="payment_method" value="cash_on_delivery" id="cash_on_delivery">
                                <label for="cash_on_delivery">
                                    <div class="method-icon cash">
                                        <i class="fas fa-money-bill-wave"></i>
                                    </div>
                                    <div class="method-info">
                                        <h6>Cash on Delivery</h6>
                                        <p>Pay when you receive</p>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Card Details -->
                        <div class="card-details" id="card-details">
                            <h5><i class="fas fa-lock me-2"></i>Card Details</h5>
                            
                            <div class="row">
                                <div class="col-12">
                                    <div class="form-group">
                                        <label>Cardholder Name</label>
                                        <input type="text" class="form-control" id="cardholder_name" name="cardholder_name" placeholder="John Doe">
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="form-group">
                                        <label>Card Number</label>
                                        <input type="text" class="form-control" id="card_number" name="card_number" placeholder="1234 5678 9012 3456" maxlength="19">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Expiry Date</label>
                                        <input type="text" class="form-control" id="expiry_date" name="expiry_date" placeholder="MM/YY" maxlength="5">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>CVV</label>
                                        <input type="text" class="form-control" id="cvv" name="cvv" placeholder="123" maxlength="4">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="pay-btn" id="pay-btn" disabled>
                            <i class="fas fa-lock me-2"></i>
                            Pay Rs.<?php echo number_format($total_amount, 2); ?>
                        </button>
                    </form>

                    <div class="loading-overlay" id="loading">
                        <div class="spinner"></div>
                        <p>Processing payment...</p>
                    </div>
                </div>
            </div>

            <!-- Order Summary -->
            <div class="col-lg-4">
                <div class="summary-card">
                    <h4 class="summary-title">
                        <i class="fas fa-shopping-cart me-2"></i>
                        <?php echo $payment_type === 'cart' ? 'Order Summary' : 'Booking Summary'; ?>
                    </h4>

                    <div class="summary-items">
                        <?php foreach ($payment_items as $item): ?>
                        <div class="summary-item">
                            <div class="item-image">
                                <img src="<?php echo htmlspecialchars($item['image'] ?: 'assets/images/no-image.png'); ?>" 
                                     alt="<?php echo htmlspecialchars($item['name']); ?>">
                            </div>
                            <div class="item-details">
                                <h6><?php echo htmlspecialchars($item['name']); ?></h6>
                                <p><?php echo htmlspecialchars($item['brand']); ?></p>
                                <?php if ($payment_type === 'cart'): ?>
                                    <span>Qty: <?php echo $item['quantity']; ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="item-price">
                                Rs.<?php echo number_format($item['subtotal'], 2); ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="summary-total">
                        <div class="total-row final">
                            <span>Total Amount:</span>
                            <span>Rs.<?php echo number_format($total_amount, 2); ?></span>
                        </div>
                    </div>

                    <div class="security-info">
                        <div class="security-item">
                            <i class="fas fa-shield-alt"></i>
                            <span>SSL Secured</span>
                        </div>
                        <div class="security-item">
                            <i class="fas fa-lock"></i>
                            <span>256-bit Encryption</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
/* Payment Header */
.page-header-modern {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 80px 0;
    position: relative;
    overflow: hidden;
}

.page-header-modern::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="20" cy="20" r="3" fill="rgba(255,255,255,0.1)"/><circle cx="80" cy="80" r="3" fill="rgba(255,255,255,0.1)"/></svg>');
    opacity: 0.3;
}

.header-content {
    position: relative;
    z-index: 2;
}

.header-badge {
    background: rgba(255, 255, 255, 0.15);
    padding: 8px 16px;
    border-radius: 50px;
    font-size: 0.9rem;
    font-weight: 500;
    display: inline-block;
    margin-bottom: 16px;
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.page-title {
    font-size: 3rem;
    font-weight: 700;
    margin-bottom: 16px;
}

.page-subtitle {
    font-size: 1.2rem;
    opacity: 0.9;
    margin: 0;
}

.btn-glass {
    background: rgba(255, 255, 255, 0.15);
    border: 1px solid rgba(255, 255, 255, 0.2);
    color: white;
    padding: 12px 24px;
    border-radius: 50px;
    text-decoration: none;
    font-weight: 500;
    transition: all 0.3s ease;
    backdrop-filter: blur(10px);
}

.btn-glass:hover {
    background: rgba(255, 255, 255, 0.25);
    color: white;
    transform: translateY(-2px);
}

/* Payment Content */
.payment-content {
    padding: 80px 0;
    background: #f8f9ff;
}

.payment-card {
    background: white;
    border-radius: 20px;
    padding: 40px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    position: relative;
}

.summary-card {
    background: white;
    border-radius: 20px;
    padding: 30px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    position: sticky;
    top: 20px;
}

/* Section Headers */
.section-header {
    margin-bottom: 30px;
}

.section-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: #1e293b;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

/* Customer Details */
.customer-details {
    background: #f8fafc;
    padding: 25px;
    border-radius: 15px;
    margin-bottom: 30px;
}

.detail-item {
    margin-bottom: 15px;
}

.detail-item label {
    font-weight: 600;
    color: #64748b;
    font-size: 0.9rem;
    margin-bottom: 5px;
    display: block;
}

.detail-item p {
    color: #1e293b;
    margin: 0;
    font-weight: 500;
}

/* Payment Methods */
.payment-methods {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 15px;
    margin-bottom: 30px;
}

.payment-option {
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    overflow: hidden;
    transition: all 0.3s ease;
    cursor: pointer;
}

.payment-option:hover {
    border-color: #667eea;
    transform: translateY(-2px);
}

.payment-option.selected {
    border-color: #667eea;
    background: #f0f9ff;
}

.payment-option input[type="radio"] {
    display: none;
}

.payment-option label {
    display: flex;
    align-items: center;
    padding: 20px;
    cursor: pointer;
    margin: 0;
    gap: 15px;
}

.method-icon {
    width: 50px;
    height: 50px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f1f5f9;
    color: #667eea;
    font-size: 1.5rem;
}

.method-icon.paypal {
    background: #0070ba;
    color: white;
}

.method-icon.bank {
    background: #10b981;
    color: white;
}

.method-icon.cash {
    background: #f59e0b;
    color: white;
}

.method-info h6 {
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 5px;
}

.method-info p {
    color: #64748b;
    margin: 0;
    font-size: 0.9rem;
}

/* Card Details */
.card-details {
    display: none;
    background: #f8fafc;
    padding: 25px;
    border-radius: 12px;
    margin: 25px 0;
    border: 1px solid #e5e7eb;
}

.card-details.active {
    display: block;
}

.card-details h5 {
    margin-bottom: 20px;
    color: #1e293b;
    display: flex;
    align-items: center;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    color: #374151;
    font-weight: 600;
}

.form-control {
    width: 100%;
    padding: 12px 15px;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    font-size: 1rem;
    transition: all 0.3s ease;
}

.form-control:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

/* Pay Button */
.pay-btn {
    width: 100%;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    border: none;
    padding: 15px;
    border-radius: 12px;
    font-size: 1.1rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.pay-btn:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
}

.pay-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

/* Loading */
.loading-overlay {
    display: none;
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(255, 255, 255, 0.9);
    border-radius: 20px;
    z-index: 1000;
    justify-content: center;
    align-items: center;
    flex-direction: column;
}

.loading-overlay.active {
    display: flex;
}

.spinner {
    border: 3px solid #f3f4f6;
    border-top: 3px solid #667eea;
    border-radius: 50%;
    width: 40px;
    height: 40px;
    animation: spin 1s linear infinite;
    margin-bottom: 15px;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Summary */
.summary-title {
    font-size: 1.3rem;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 25px;
    display: flex;
    align-items: center;
}

.summary-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 15px 0;
    border-bottom: 1px solid #f1f5f9;
}

.summary-item:last-child {
    border-bottom: none;
}

.item-image {
    width: 60px;
    height: 60px;
    border-radius: 8px;
    overflow: hidden;
    background: #f8fafc;
}

.item-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.item-details {
    flex: 1;
}

.item-details h6 {
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 5px;
    font-size: 0.9rem;
}

.item-details p {
    color: #64748b;
    font-size: 0.8rem;
    margin-bottom: 5px;
}

.item-details span {
    color: #64748b;
    font-size: 0.8rem;
}

.item-price {
    font-weight: 600;
    color: #10b981;
}

.summary-total {
    border-top: 2px solid #f1f5f9;
    padding-top: 20px;
    margin-top: 20px;
}

.total-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
}

.total-row.final {
    font-size: 1.2rem;
    font-weight: 700;
    color: #1e293b;
}

.security-info {
    margin-top: 25px;
    padding-top: 20px;
    border-top: 1px solid #f1f5f9;
    display: flex;
    gap: 15px;
}

.security-item {
    display: flex;
    align-items: center;
    gap: 6px;
    color: #10b981;
    font-size: 0.8rem;
    font-weight: 500;
}

/* Messages */
.error-message {
    background: #fef2f2;
    color: #dc2626;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    border: 1px solid #fecaca;
    display: flex;
    align-items: center;
    gap: 8px;
}

.success-message {
    background: #f0fdf4;
    color: #16a34a;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    border: 1px solid #bbf7d0;
    display: flex;
    align-items: center;
    gap: 8px;
}

/* Responsive */
@media (max-width: 991px) {
    .payment-card,
    .summary-card {
        padding: 25px;
    }
    
    .page-title {
        font-size: 2.5rem;
    }
    
    .payment-methods {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .payment-content {
        padding: 40px 0;
    }
    
    .security-info {
        flex-direction: column;
        gap: 8px;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const paymentOptions = document.querySelectorAll('.payment-option');
    const cardDetails = document.getElementById('card-details');
    const payBtn = document.getElementById('pay-btn');
    const paymentForm = document.getElementById('payment-form');
    const messageContainer = document.getElementById('message-container');
    const loading = document.getElementById('loading');

    // Payment method selection
    paymentOptions.forEach(option => {
        option.addEventListener('click', function() {
            paymentOptions.forEach(opt => opt.classList.remove('selected'));
            this.classList.add('selected');
            
            const radio = this.querySelector('input[type="radio"]');
            radio.checked = true;
            
            if (radio.value === 'credit_card') {
                cardDetails.classList.add('active');
            } else {
                cardDetails.classList.remove('active');
            }
            
            payBtn.disabled = false;
            updatePayButtonText(radio.value);
        });
    });

    function updatePayButtonText(method) {
        const amount = 'Rs.<?php echo number_format($total_amount, 2); ?>';
        switch(method) {
            case 'cash_on_delivery':
                payBtn.innerHTML = `<i class="fas fa-truck me-2"></i>Place Order - ${amount}`;
                break;
            case 'paypal':
                payBtn.innerHTML = `<i class="fab fa-paypal me-2"></i>Pay with PayPal - ${amount}`;
                break;
            case 'bank_transfer':
                payBtn.innerHTML = `<i class="fas fa-university me-2"></i>Pay via Bank - ${amount}`;
                break;
            default:
                payBtn.innerHTML = `<i class="fas fa-lock me-2"></i>Pay ${amount}`;
        }
    }

    // Card formatting
    const cardNumber = document.getElementById('card_number');
    if (cardNumber) {
        cardNumber.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\s/g, '').replace(/[^0-9]/gi, '');
            let formattedValue = value.match(/.{1,4}/g)?.join(' ') || value;
            e.target.value = formattedValue;
        });
    }

    const expiryDate = document.getElementById('expiry_date');
    if (expiryDate) {
        expiryDate.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length >= 2) {
                value = value.substring(0, 2) + '/' + value.substring(2, 4);
            }
            e.target.value = value;
        });
    }

    const cvv = document.getElementById('cvv');
    if (cvv) {
        cvv.addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/[^0-9]/g, '');
        });
    }

    // Form submission
    paymentForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('ajax_payment', '1');
        
        const selectedMethod = document.querySelector('input[name="payment_method"]:checked').value;
        
        if (selectedMethod === 'credit_card' && !validateCard()) {
            return;
        }
        
        loading.classList.add('active');
        payBtn.disabled = true;
        
        const url = `payment.php?<?php echo $cart_id ? "cart_id=$cart_id" : "booking_id=$booking_id"; ?>`;
        
        fetch(url, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            loading.classList.remove('active');
            
            if (data.success) {
                showMessage(data.message, 'success');
                setTimeout(() => {
                    <?php if ($payment_type === 'cart'): ?>
                        window.location.href = 'cart.php';
                    <?php else: ?>
                        window.location.href = 'bookings.php';
                    <?php endif; ?>
                }, 2000);
            } else {
                showMessage(data.message, 'error');
                payBtn.disabled = false;
            }
        })
        .catch(error => {
            loading.classList.remove('active');
            showMessage('Payment processing failed. Please try again.', 'error');
            payBtn.disabled = false;
        });
    });

    function validateCard() {
        const name = document.getElementById('cardholder_name').value.trim();
        const number = document.getElementById('card_number').value.replace(/\s/g, '');
        const expiry = document.getElementById('expiry_date').value;
        const cvvVal = document.getElementById('cvv').value;

        if (!name) {
            showMessage('Please enter cardholder name.', 'error');
            return false;
        }
        if (number.length < 13) {
            showMessage('Please enter a valid card number.', 'error');
            return false;
        }
        if (!/^\d{2}\/\d{2}$/.test(expiry)) {
            showMessage('Please enter a valid expiry date.', 'error');
            return false;
        }
        if (cvvVal.length < 3) {
            showMessage('Please enter a valid CVV.', 'error');
            return false;
        }
        return true;
    }

    function showMessage(message, type) {
        const className = type === 'success' ? 'success-message' : 'error-message';
        const icon = type === 'success' ? 'fas fa-check-circle' : 'fas fa-exclamation-triangle';
        
        messageContainer.innerHTML = `
            <div class="${className}">
                <i class="${icon}"></i> ${message}
            </div>
        `;
    }
});
</script>

<?php
include_once 'includes/footer.php';
$conn->close();
?>