<?php
// Start session if not already started
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

$user_id = $_SESSION['user_id'];

// Get pet owner information
$user_sql = "SELECT * FROM pet_owner WHERE userID = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user = $user_result->fetch_assoc();
$user_stmt->close();

// Get pet count
$pet_sql = "SELECT COUNT(*) as pet_count FROM pet_profile WHERE userID = ?";
$pet_stmt = $conn->prepare($pet_sql);
$pet_stmt->bind_param("i", $user_id);
$pet_stmt->execute();
$pet_result = $pet_stmt->get_result();
$pet_count = $pet_result->fetch_assoc()['pet_count'];
$pet_stmt->close();

// Get active bookings count
$booking_sql = "SELECT COUNT(*) as booking_count FROM booking WHERE userID = ? AND status IN ('Pending', 'Confirmed')";
$booking_stmt = $conn->prepare($booking_sql);
$booking_stmt->bind_param("i", $user_id);
$booking_stmt->execute();
$booking_result = $booking_stmt->get_result();
$booking_count = $booking_result->fetch_assoc()['booking_count'];
$booking_stmt->close();

// Get orders count
$order_sql = "SELECT COUNT(*) as order_count FROM `order` WHERE userID = ?";
$order_stmt = $conn->prepare($order_sql);
$order_stmt->bind_param("i", $user_id);
$order_stmt->execute();
$order_result = $order_stmt->get_result();
$order_count = $order_result->fetch_assoc()['order_count'];
$order_stmt->close();

// Get recent bookings
$recent_bookings_sql = "SELECT b.*, p.petName, ps.fullName as sitterName 
                        FROM booking b 
                        JOIN pet_profile p ON b.petID = p.petID 
                        JOIN pet_sitter ps ON b.sitterID = ps.userID 
                        WHERE b.userID = ? 
                        ORDER BY b.created_at DESC LIMIT 3";
$recent_bookings_stmt = $conn->prepare($recent_bookings_sql);
$recent_bookings_stmt->bind_param("i", $user_id);
$recent_bookings_stmt->execute();
$recent_bookings_result = $recent_bookings_stmt->get_result();
$recent_bookings = [];
while ($row = $recent_bookings_result->fetch_assoc()) {
    $recent_bookings[] = $row;
}
$recent_bookings_stmt->close();

// Get recent orders
$recent_orders_sql = "SELECT o.*, 
                      (SELECT COUNT(*) FROM cart_items ci JOIN cart c ON ci.cartID = c.cartID WHERE c.orderID = o.orderID) as item_count 
                      FROM `order` o 
                      WHERE o.userID = ? 
                      ORDER BY o.date DESC, o.time DESC LIMIT 3";
$recent_orders_stmt = $conn->prepare($recent_orders_sql);
$recent_orders_stmt->bind_param("i", $user_id);
$recent_orders_stmt->execute();
$recent_orders_result = $recent_orders_stmt->get_result();
$recent_orders = [];
while ($row = $recent_orders_result->fetch_assoc()) {
    $recent_orders[] = $row;
}
$recent_orders_stmt->close();

// Include header
include_once '../includes/header.php';
?>

<!-- Dashboard Header -->
<div class="container-fluid bg-light py-4">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="display-5 mb-2">Welcome, <?php echo htmlspecialchars($user['fullName']); ?>!</h1>
                <p class="lead">Manage your pets, bookings, and orders from your dashboard.</p>
            </div>
            <div class="col-md-4 text-md-end">
                <a href="profile.php" class="btn btn-primary">
                    <i class="fas fa-user-edit me-1"></i> Edit Profile
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Dashboard Content -->
<div class="container py-5">
    <!-- Statistics Cards -->
    <div class="row mb-5">
        <div class="col-md-3">
            <div class="dashboard-stat bg-primary text-white">
                <div class="icon">
                    <i class="fas fa-paw"></i>
                </div>
                <h3><?php echo $pet_count; ?></h3>
                <p>Pets</p>
                <a href="pets.php" class="stretched-link"></a>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="dashboard-stat bg-success text-white">
                <div class="icon">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <h3><?php echo $booking_count; ?></h3>
                <p>Active Bookings</p>
                <a href="bookings.php" class="stretched-link"></a>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="dashboard-stat bg-warning text-white">
                <div class="icon">
                    <i class="fas fa-shopping-bag"></i>
                </div>
                <h3><?php echo $order_count; ?></h3>
                <p>Orders</p>
                <a href="orders.php" class="stretched-link"></a>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="dashboard-stat bg-info text-white">
                <div class="icon">
                    <i class="fas fa-star"></i>
                </div>
                <h3><i class="fas fa-plus"></i></h3>
                <p>Add a Pet</p>
                <a href="add_pet.php" class="stretched-link"></a>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Recent Bookings -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Recent Bookings</h5>
                    <a href="bookings.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_bookings)): ?>
                        <p class="text-muted">No recent bookings found.</p>
                    <?php else: ?>
                        <?php foreach ($recent_bookings as $booking): ?>
                            <div class="booking-card mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h6><?php echo htmlspecialchars($booking['petName']); ?> with <?php echo htmlspecialchars($booking['sitterName']); ?></h6>
                                    <?php
                                    $status_class = '';
                                    switch ($booking['status']) {
                                        case 'Pending':
                                            $status_class = 'bg-warning';
                                            break;
                                        case 'Confirmed':
                                            $status_class = 'bg-success';
                                            break;
                                        case 'Cancelled':
                                            $status_class = 'bg-danger';
                                            break;
                                        case 'Completed':
                                            $status_class = 'bg-info';
                                            break;
                                    }
                                    ?>
                                    <span class="badge status-badge <?php echo $status_class; ?>"><?php echo $booking['status']; ?></span>
                                </div>
                                <p class="mb-1 small">
                                    <i class="fas fa-calendar me-2"></i>
                                    <?php echo date('M d, Y', strtotime($booking['checkInDate'])); ?> at 
                                    <?php echo date('h:i A', strtotime($booking['checkInTime'])); ?> - 
                                    <?php echo date('M d, Y', strtotime($booking['checkOutDate'])); ?> at 
                                    <?php echo date('h:i A', strtotime($booking['checkOutTime'])); ?>
                                </p>
                                <div class="d-flex justify-content-between align-items-center mt-2">
                                    <small class="text-muted">Booked on <?php echo date('M d, Y', strtotime($booking['created_at'])); ?></small>
                                    <a href="booking_details.php?id=<?php echo $booking['bookingID']; ?>" class="btn btn-sm btn-outline-secondary">Details</a>
                                </div>
                            </div>
                            <?php if ($booking !== end($recent_bookings)): ?>
                                <hr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Recent Orders -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Recent Orders</h5>
                    <a href="orders.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_orders)): ?>
                        <p class="text-muted">No recent orders found.</p>
                    <?php else: ?>
                        <?php foreach ($recent_orders as $order): ?>
                            <div class="booking-card mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h6>Order #<?php echo $order['orderID']; ?></h6>
                                    <?php
                                    $status_class = '';
                                    switch ($order['status']) {
                                        case 'Pending':
                                            $status_class = 'bg-warning';
                                            break;
                                        case 'Processing':
                                            $status_class = 'bg-primary';
                                            break;
                                        case 'Shipped':
                                            $status_class = 'bg-info';
                                            break;
                                        case 'Delivered':
                                            $status_class = 'bg-success';
                                            break;
                                        case 'Cancelled':
                                            $status_class = 'bg-danger';
                                            break;
                                    }
                                    ?>
                                    <span class="badge status-badge <?php echo $status_class; ?>"><?php echo $order['status']; ?></span>
                                </div>
                                <p class="mb-1 small"><i class="fas fa-shopping-cart me-2"></i> <?php echo $order['item_count']; ?> item(s)</p>
                                <p class="mb-1 small"><i class="fas fa-calendar me-2"></i> Ordered on <?php echo date('M d, Y', strtotime($order['date'])); ?></p>
                                <div class="d-flex justify-content-between align-items-center mt-2">
                                    <small class="text-muted">Delivery to: <?php echo substr(htmlspecialchars($order['address']), 0, 30) . '...'; ?></small>
                                    <a href="order_details.php?id=<?php echo $order['orderID']; ?>" class="btn btn-sm btn-outline-secondary">Details</a>
                                </div>
                            </div>
                            <?php if ($order !== end($recent_orders)): ?>
                                <hr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Quick Links -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Quick Links</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-3 mb-md-0">
                            <a href="../pet_sitters.php" class="btn btn-outline-primary w-100 py-3">
                                <i class="fas fa-search me-2"></i> Find a Pet Sitter
                            </a>
                        </div>
                        <div class="col-md-4 mb-3 mb-md-0">
                            <a href="../shop.php" class="btn btn-outline-success w-100 py-3">
                                <i class="fas fa-shopping-cart me-2"></i> Shop Pet Products
                            </a>
                        </div>
                        <div class="col-md-4">
                            <a href="../contact.php" class="btn btn-outline-info w-100 py-3">
                                <i class="fas fa-question-circle me-2"></i> Need Help?
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include_once '../includes/footer.php';

// Close database connection
$conn->close();
?>