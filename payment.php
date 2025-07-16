<?php
// Handle AJAX payment processing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_payment'])) {
    header('Content-Type: application/json');
    
    // Start transaction
    require_once 'config/db_connect.php';
    $conn->autocommit(FALSE);
    
    try {
        session_start();
        $user_id = $_SESSION['user_id'] ?? null;
        
        if (!$user_id) {
            throw new Exception("User not logged in.");
        }
        
        // Get payment data
        $payment_method_raw = $_POST['payment_method'] ?? '';
        $payment_method = htmlspecialchars($payment_method_raw);
        $cart_id = isset($_POST['cart_id']) ? intval($_POST['cart_id']) : null;
        $booking_id = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : null;
        
        if (!$cart_id && !$booking_id) {
            throw new Exception("Invalid payment request.");
        }
        
        $total_amount = 0;
        
        if ($cart_id) {
            // Cart payment - get total amount
            $total_sql = "SELECT SUM(ci.quantity * ci.price) as total 
                         FROM cart_items ci WHERE ci.cartID = ?";
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
        
        // Insert payment - FIXED: Handle NULL foreign keys
        $payment_status = ($payment_method_raw === 'cash_on_delivery') ? 'Pending' : 'Completed';
        
        if ($cart_id) {
            $payment_sql = "INSERT INTO payment (amount, paymentDate, payment_method, status, cartID) VALUES (?, NOW(), ?, ?, ?)";
            $payment_stmt = $conn->prepare($payment_sql);
            $payment_stmt->bind_param("dssi", $total_amount, $payment_method, $payment_status, $cart_id);
        } else {
            $payment_sql = "INSERT INTO payment (amount, paymentDate, payment_method, status, bookingID) VALUES (?, NOW(), ?, ?, ?)";
            $payment_stmt = $conn->prepare($payment_sql);
            $payment_stmt->bind_param("dssi", $total_amount, $payment_method, $payment_status, $booking_id);
        }
        $payment_stmt->execute();
        $payment_stmt->close();
        
        // Update order/booking status - FIXED: Use ENUM values
        if ($cart_id) {
            // Check if cart has associated order
            $cart_check_sql = "SELECT orderID FROM cart WHERE cartID = ?";
            $cart_check_stmt = $conn->prepare($cart_check_sql);
            $cart_check_stmt->bind_param("i", $cart_id);
            $cart_check_stmt->execute();
            $cart_check_result = $cart_check_stmt->get_result();
            $cart_data = $cart_check_result->fetch_assoc();
            $cart_check_stmt->close();
            
            if ($cart_data && $cart_data['orderID']) {
                $order_status = ($payment_method_raw === 'cash_on_delivery') ? 'Pending' : 'Completed';
                $update_order_sql = "UPDATE `order` SET status = ? WHERE orderID = ?";
                $update_order_stmt = $conn->prepare($update_order_sql);
                $update_order_stmt->bind_param("si", $order_status, $cart_data['orderID']);
                $update_order_stmt->execute();
                $update_order_stmt->close();
            }
        }
        
        if ($booking_id) {
            $booking_status = ($payment_method_raw === 'cash_on_delivery') ? 'Pending' : 'Completed';
            $update_booking_sql = "UPDATE booking SET status = ? WHERE bookingID = ?";
            $update_booking_stmt = $conn->prepare($update_booking_sql);
            $update_booking_stmt->bind_param("si", $booking_status, $booking_id);
            $update_booking_stmt->execute();
            $update_booking_stmt->close();
        }
        
        // Update stock for completed cart payments - FIXED: Use 'Completed'
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
        
        $message = ($payment_method_raw === 'cash_on_delivery') ? 
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
        header("Location: user/bookings.php");
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
                                    <p><?php echo htmlspecialchars($customer_info['fullName'] ?? 'N/A'); ?></p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="detail-item">
                                    <label>Email</label>
                                    <p><?php echo htmlspecialchars($customer_info['email'] ?? 'N/A'); ?></p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="detail-item">
                                    <label>Contact</label>
                                    <p><?php echo htmlspecialchars($customer_info['contact'] ?? 'N/A'); ?></p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="detail-item">
                                    <label>Address</label>
                                    <p><?php echo htmlspecialchars($customer_info['address'] ?? 'N/A'); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Methods -->
                    <div class="section-header">
                        <h3 class="section-title">
                            <i class="fas fa-credit-card me-2"></i>Payment Method
                        </h3>
                    </div>
                    <div class="section-content">
                        <form id="payment-form">
                            <input type="hidden" name="ajax_payment" value="1">
                            <input type="hidden" name="cart_id" value="<?php echo $cart_id; ?>">
                            <input type="hidden" name="booking_id" value="<?php echo $booking_id; ?>">
                            
                            <div class="payment-methods">
                                <!-- Credit/Debit Card -->
                                <div class="payment-option" data-method="card">
                                    <input type="radio" name="payment_method" value="credit_debit_card" id="card">
                                    <label for="card">
                                        <div class="method-icon">
                                            <i class="fas fa-credit-card"></i>
                                        </div>
                                        <div class="method-info">
                                            <h6>Credit/Debit Card</h6>
                                            <p>Pay securely with your card</p>
                                        </div>
                                    </label>
                                </div>

                                <!-- PayPal -->
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

                                <!-- Bank Transfer -->
                                <div class="payment-option" data-method="bank">
                                    <input type="radio" name="payment_method" value="bank_transfer" id="bank">
                                    <label for="bank">
                                        <div class="method-icon bank">
                                            <i class="fas fa-university"></i>
                                        </div>
                                        <div class="method-info">
                                            <h6>Bank Transfer</h6>
                                            <p>Direct bank transfer</p>
                                        </div>
                                    </label>
                                </div>

                                <!-- Cash on Delivery -->
                                <div class="payment-option" data-method="cash">
                                    <input type="radio" name="payment_method" value="cash_on_delivery" id="cash">
                                    <label for="cash">
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

                            <!-- Card Details (shown when card is selected) -->
                            <div id="card-details" class="card-details">
                                <h5><i class="fas fa-credit-card me-2"></i>Card Details</h5>
                                <div class="row">
                                    <div class="col-12">
                                        <div class="form-group">
                                            <label for="card_name">Name on Card</label>
                                            <input type="text" class="form-control" id="card_name" placeholder="John Doe">
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="form-group">
                                            <label for="card_number">Card Number</label>
                                            <input type="text" class="form-control" id="card_number" placeholder="1234 5678 9012 3456">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="expiry">Expiry Date</label>
                                            <input type="text" class="form-control" id="expiry" placeholder="MM/YY">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="cvv">CVV</label>
                                            <input type="text" class="form-control" id="cvv" placeholder="123">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="payment-submit">
                                <button type="submit" class="btn btn-primary btn-lg w-100" id="pay-btn">
                                    <i class="fas fa-lock me-2"></i>Complete Payment - Rs.<?php echo number_format($total_amount, 2); ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Order Summary -->
            <div class="col-lg-4">
                <div class="order-summary-card">
                    <div class="summary-header">
                        <h4>Order Summary</h4>
                    </div>
                    
                    <div class="summary-items">
                        <?php foreach ($payment_items as $item): ?>
                            <div class="summary-item">
                                <div class="item-image">
                                    <img src="<?php echo htmlspecialchars($item['image'] ?? 'assets/images/default-product.jpg'); ?>" 
                                         alt="<?php echo htmlspecialchars($item['name']); ?>">
                                </div>
                                <div class="item-details">
                                    <h6><?php echo htmlspecialchars($item['name']); ?></h6>
                                    <p><?php echo htmlspecialchars($item['brand']); ?></p>
                                    <div class="item-meta">
                                        <span class="quantity">Qty: <?php echo $item['quantity']; ?></span>
                                        <span class="price">Rs.<?php echo number_format($item['price'], 2); ?></span>
                                    </div>
                                </div>
                                <div class="item-total">
                                    Rs.<?php echo number_format($item['subtotal'], 2); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="summary-totals">
                        <div class="total-row">
                            <span>Subtotal:</span>
                            <span>Rs.<?php echo number_format($total_amount, 2); ?></span>
                        </div>
                        <div class="total-row">
                            <span>Shipping:</span>
                            <span>Free</span>
                        </div>
                        <div class="total-row final-total">
                            <span>Total:</span>
                            <span>Rs.<?php echo number_format($total_amount, 2); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
/* Payment Styles */
.page-header-modern {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 60px 0;
    margin-bottom: 40px;
}

.header-badge {
    background: rgba(255, 255, 255, 0.2);
    padding: 8px 16px;
    border-radius: 20px;
    display: inline-block;
    margin-bottom: 15px;
    font-size: 0.9rem;
}

.page-title {
    font-size: 3rem;
    font-weight: 700;
    margin-bottom: 10px;
}

.page-subtitle {
    font-size: 1.1rem;
    opacity: 0.9;
    margin: 0;
}

.btn-glass {
    background: rgba(255, 255, 255, 0.2);
    border: 1px solid rgba(255, 255, 255, 0.3);
    color: white;
    backdrop-filter: blur(10px);
}

.payment-content {
    padding: 40px 0 80px;
}

.payment-card, .order-summary-card {
    background: white;
    border-radius: 15px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    padding: 30px;
    margin-bottom: 30px;
}

.section-header {
    margin-bottom: 25px;
    padding-bottom: 15px;
    border-bottom: 2px solid #f1f5f9;
}

.section-title {
    color: #1e293b;
    font-size: 1.4rem;
    font-weight: 600;
    margin: 0;
}

.section-content {
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
    transition: border-color 0.3s ease;
}

.form-control:focus {
    outline: none;
    border-color: #667eea;
}

/* Order Summary */
.summary-header h4 {
    color: #1e293b;
    font-weight: 600;
    margin-bottom: 20px;
}

.summary-items {
    margin-bottom: 25px;
}

.summary-item {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 15px 0;
    border-bottom: 1px solid #f1f5f9;
}

.item-image {
    width: 60px;
    height: 60px;
    border-radius: 8px;
    overflow: hidden;
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

.item-meta {
    display: flex;
    justify-content: space-between;
    font-size: 0.8rem;
    color: #64748b;
}

.item-total {
    font-weight: 600;
    color: #1e293b;
}

.summary-totals {
    padding-top: 20px;
    border-top: 2px solid #f1f5f9;
}

.total-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
    color: #64748b;
}

.total-row.final-total {
    font-size: 1.2rem;
    font-weight: 700;
    color: #1e293b;
    padding-top: 15px;
    border-top: 1px solid #e5e7eb;
    margin-top: 15px;
}

.payment-submit {
    margin-top: 30px;
}

.btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    padding: 15px 30px;
    border-radius: 10px;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
}

@media (max-width: 768px) {
    .page-title {
        font-size: 2.5rem;
    }
    
    .payment-methods {
        grid-template-columns: 1fr;
    }
    
    .payment-card, .order-summary-card {
        padding: 20px;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Payment method selection
    const paymentOptions = document.querySelectorAll('.payment-option');
    const cardDetails = document.getElementById('card-details');
    
    paymentOptions.forEach(option => {
        option.addEventListener('click', function() {
            // Remove selected class from all options
            paymentOptions.forEach(opt => opt.classList.remove('selected'));
            
            // Add selected class to clicked option
            this.classList.add('selected');
            
            // Check the radio button
            const radio = this.querySelector('input[type="radio"]');
            if (radio) {
                radio.checked = true;
            }
            
            // Show/hide card details
            if (this.dataset.method === 'card') {
                cardDetails.classList.add('active');
            } else {
                cardDetails.classList.remove('active');
            }
        });
    });
    
    // Card validation functions
    function validateCardNumber(number) {
        const cleaned = number.replace(/\s+/g, '').replace(/[^0-9]/gi, '');
        return cleaned.length >= 13 && cleaned.length <= 19;
    }
    
    function validateExpiryDate(expiry) {
        const pattern = /^(0[1-9]|1[0-2])\/([0-9]{2})$/;
        if (!pattern.test(expiry)) return false;
        
        const [month, year] = expiry.split('/');
        const now = new Date();
        const expiryDate = new Date(2000 + parseInt(year), parseInt(month) - 1);
        return expiryDate > now;
    }
    
    function validateCVV(cvv) {
        return /^[0-9]{3,4}$/.test(cvv);
    }
    
    function validateCardName(name) {
        return name.trim().length >= 2 && /^[a-zA-Z\s]+$/.test(name);
    }
    
    // Card input formatting
    document.getElementById('card_number').addEventListener('input', function(e) {
        let value = e.target.value.replace(/\s+/g, '').replace(/[^0-9]/gi, '');
        let formattedValue = value.match(/.{1,4}/g)?.join(' ') || value;
        e.target.value = formattedValue;
    });
    
    document.getElementById('expiry').addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, '');
        if (value.length >= 2) {
            value = value.substring(0,2) + '/' + value.substring(2,4);
        }
        e.target.value = value;
    });
    
    document.getElementById('cvv').addEventListener('input', function(e) {
        e.target.value = e.target.value.replace(/\D/g, '').substring(0,4);
    });
    
    // Form submission
    const form = document.getElementById('payment-form');
    const payBtn = document.getElementById('pay-btn');
    
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const selectedMethod = document.querySelector('input[name="payment_method"]:checked');
        if (!selectedMethod) {
            alert('Please select a payment method.');
            return;
        }
        
        // Validate card details if card payment is selected
        if (selectedMethod.value === 'credit_debit_card') {
            const cardName = document.getElementById('card_name').value;
            const cardNumber = document.getElementById('card_number').value;
            const expiry = document.getElementById('expiry').value;
            const cvv = document.getElementById('cvv').value;
            
            if (!validateCardName(cardName)) {
                alert('Please enter a valid cardholder name.');
                return;
            }
            
            if (!validateCardNumber(cardNumber)) {
                alert('Please enter a valid card number (13-19 digits).');
                return;
            }
            
            if (!validateExpiryDate(expiry)) {
                alert('Please enter a valid expiry date (MM/YY) in the future.');
                return;
            }
            
            if (!validateCVV(cvv)) {
                alert('Please enter a valid CVV (3-4 digits).');
                return;
            }
        }
        
        // Disable button and show loading
        payBtn.disabled = true;
        payBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';
        
        // Submit form via AJAX
        const formData = new FormData(form);
        
        fetch('payment.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                // Redirect based on payment type
                if (formData.get('cart_id')) {
                    window.location.href = 'cart.php';
                } else {
                    window.location.href = 'user/bookings.php';
                }
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            alert('An error occurred. Please try again.');
            console.error('Error:', error);
        })
        .finally(() => {
            // Re-enable button
            payBtn.disabled = false;
            payBtn.innerHTML = '<i class="fas fa-lock me-2"></i>Complete Payment - Rs.<?php echo number_format($total_amount, 2); ?>';
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