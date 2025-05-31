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

<!-- Modern Dashboard Header -->
<section class="dashboard-header-section">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <div class="welcome-content">
                    <div class="welcome-greeting">
                        <span class="greeting-time">Good <?php echo (date('H') < 12) ? 'Morning' : ((date('H') < 18) ? 'Afternoon' : 'Evening'); ?>! 👋</span>
                        <h1 class="welcome-title">Welcome back, <span class="text-gradient"><?php echo htmlspecialchars(explode(' ', $user['fullName'])[0]); ?></span></h1>
                        <p class="welcome-subtitle">Manage your pets, bookings, and orders from your personalized dashboard</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="quick-actions-widget">
                    <h6>Quick Actions</h6>
                    <div class="quick-action-buttons">
                        <a href="../pet_sitters.php" class="quick-action-btn">
                            <i class="fas fa-search"></i>
                            Find Sitter
                        </a>
                        <a href="../shop.php" class="quick-action-btn">
                            <i class="fas fa-shopping-cart"></i>
                            Shop Now
                        </a>
                        <a href="add_pet.php" class="quick-action-btn">
                            <i class="fas fa-plus"></i>
                            Add Pet
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Dashboard Stats Cards -->
<section class="dashboard-stats-section">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-3 col-md-6">
                <div class="stat-card primary">
                    <div class="stat-icon">
                        <i class="fas fa-paw"></i>
                    </div>
                    <div class="stat-content">
                        <h3 class="stat-number"><?php echo $pet_count; ?></h3>
                        <p class="stat-label">My Pets</p>
                        <div class="stat-trend">
                            <a href="pets.php" class="stat-link">Manage Pets <i class="fas fa-arrow-right"></i></a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <div class="stat-card success">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-content">
                        <h3 class="stat-number"><?php echo $booking_count; ?></h3>
                        <p class="stat-label">Active Bookings</p>
                        <div class="stat-trend">
                            <a href="bookings.php" class="stat-link">View Bookings <i class="fas fa-arrow-right"></i></a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <div class="stat-card warning">
                    <div class="stat-icon">
                        <i class="fas fa-shopping-bag"></i>
                    </div>
                    <div class="stat-content">
                        <h3 class="stat-number"><?php echo $order_count; ?></h3>
                        <p class="stat-label">Total Orders</p>
                        <div class="stat-trend">
                            <a href="orders.php" class="stat-link">View Orders <i class="fas fa-arrow-right"></i></a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <div class="stat-card info">
                    <div class="stat-icon">
                        <i class="fas fa-heart"></i>
                    </div>
                    <div class="stat-content">
                        <h3 class="stat-number">4.8</h3>
                        <p class="stat-label">Satisfaction</p>
                        <div class="stat-trend">
                            <span class="stat-link">Excellent Rating</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Dashboard Content -->
<section class="dashboard-content-section">
    <div class="container">
        <div class="row g-4">
            <!-- Recent Bookings -->
            <div class="col-lg-6">
                <div class="dashboard-widget">
                    <div class="widget-header">
                        <div class="widget-title">
                            <i class="fas fa-calendar-alt widget-icon"></i>
                            <h5>Recent Bookings</h5>
                        </div>
                        <a href="bookings.php" class="view-all-link">View All</a>
                    </div>
                    
                    <div class="widget-content">
                        <?php if (empty($recent_bookings)): ?>
                            <div class="empty-state">
                                <div class="empty-icon">
                                    <i class="fas fa-calendar-plus"></i>
                                </div>
                                <h6>No Recent Bookings</h6>
                                <p>Book a pet sitter to get started</p>
                                <a href="../pet_sitters.php" class="btn btn-primary-gradient btn-sm">Find Pet Sitters</a>
                            </div>
                        <?php else: ?>
                            <?php foreach ($recent_bookings as $booking): ?>
                                <div class="booking-item">
                                    <div class="booking-info">
                                        <div class="booking-header">
                                            <h6 class="booking-pet"><?php echo htmlspecialchars($booking['petName']); ?></h6>
                                            <span class="booking-status status-<?php echo strtolower($booking['status']); ?>">
                                                <?php echo $booking['status']; ?>
                                            </span>
                                        </div>
                                        <p class="booking-sitter">with <?php echo htmlspecialchars($booking['sitterName']); ?></p>
                                        <div class="booking-date">
                                            <i class="fas fa-calendar me-2"></i>
                                            <?php echo date('M d, Y', strtotime($booking['checkInDate'])); ?> - 
                                            <?php echo date('M d, Y', strtotime($booking['checkOutDate'])); ?>
                                        </div>
                                    </div>
                                    <div class="booking-actions">
                                        <a href="booking_details.php?id=<?php echo $booking['bookingID']; ?>" class="btn btn-outline-primary btn-sm">
                                            View Details
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Recent Orders -->
            <div class="col-lg-6">
                <div class="dashboard-widget">
                    <div class="widget-header">
                        <div class="widget-title">
                            <i class="fas fa-box widget-icon"></i>
                            <h5>Recent Orders</h5>
                        </div>
                        <a href="orders.php" class="view-all-link">View All</a>
                    </div>
                    
                    <div class="widget-content">
                        <?php if (empty($recent_orders)): ?>
                            <div class="empty-state">
                                <div class="empty-icon">
                                    <i class="fas fa-shopping-cart"></i>
                                </div>
                                <h6>No Recent Orders</h6>
                                <p>Shop for your pets to get started</p>
                                <a href="../shop.php" class="btn btn-primary-gradient btn-sm">Browse Products</a>
                            </div>
                        <?php else: ?>
                            <?php foreach ($recent_orders as $order): ?>
                                <div class="order-item">
                                    <div class="order-info">
                                        <div class="order-header">
                                            <h6 class="order-number">Order #<?php echo $order['orderID']; ?></h6>
                                            <span class="order-status status-<?php echo strtolower($order['status']); ?>">
                                                <?php echo $order['status']; ?>
                                            </span>
                                        </div>
                                        <p class="order-items"><?php echo $order['item_count']; ?> item(s)</p>
                                        <div class="order-date">
                                            <i class="fas fa-clock me-2"></i>
                                            <?php echo date('M d, Y', strtotime($order['date'])); ?>
                                        </div>
                                    </div>
                                    <div class="order-actions">
                                        <a href="order_details.php?id=<?php echo $order['orderID']; ?>" class="btn btn-outline-primary btn-sm">
                                            View Details
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Additional Dashboard Widgets -->
        <div class="row g-4 mt-4">
            <!-- Pet Care Tips -->
            <div class="col-lg-8">
                <div class="dashboard-widget tips-widget">
                    <div class="widget-header">
                        <div class="widget-title">
                            <i class="fas fa-lightbulb widget-icon"></i>
                            <h5>Pet Care Tips</h5>
                        </div>
                    </div>
                    
                    <div class="widget-content">
                        <div class="tips-grid">
                            <div class="tip-card">
                                <div class="tip-icon">🐕</div>
                                <h6>Daily Exercise</h6>
                                <p>Ensure your dog gets at least 30 minutes of exercise daily for optimal health.</p>
                            </div>
                            <div class="tip-card">
                                <div class="tip-icon">🥘</div>
                                <h6>Proper Nutrition</h6>
                                <p>Feed your pet high-quality food appropriate for their age and size.</p>
                            </div>
                            <div class="tip-card">
                                <div class="tip-icon">🏥</div>
                                <h6>Regular Checkups</h6>
                                <p>Schedule annual vet visits to keep your pet healthy and happy.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Quick Stats -->
            <div class="col-lg-4">
                <div class="dashboard-widget profile-widget">
                    <div class="widget-header">
                        <div class="widget-title">
                            <i class="fas fa-user widget-icon"></i>
                            <h5>Profile Status</h5>
                        </div>
                    </div>
                    
                    <div class="widget-content">
                        <div class="profile-completion">
                            <div class="completion-circle">
                                <div class="circle-progress" data-percentage="85">
                                    <span class="percentage">85%</span>
                                </div>
                            </div>
                            <div class="completion-info">
                                <h6>Profile Complete</h6>
                                <p>Add more details to improve your experience</p>
                                <a href="profile.php" class="btn btn-outline-primary btn-sm">Update Profile</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
.dashboard-header-section {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 60px 0;
}

.greeting-time {
    font-size: 1.1rem;
    opacity: 0.9;
    margin-bottom: 8px;
    display: block;
}

.welcome-title {
    font-size: 2.8rem;
    font-weight: 800;
    margin-bottom: 16px;
    line-height: 1.2;
}

.welcome-subtitle {
    font-size: 1.1rem;
    opacity: 0.9;
    margin-bottom: 0;
}

.quick-actions-widget {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(20px);
    border-radius: 20px;
    padding: 24px;
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.quick-actions-widget h6 {
    color: white;
    margin-bottom: 16px;
    font-weight: 600;
}

.quick-action-buttons {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.quick-action-btn {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 12px;
    color: white;
    text-decoration: none;
    transition: all 0.3s ease;
    font-weight: 500;
}

.quick-action-btn:hover {
    background: rgba(255, 255, 255, 0.2);
    color: white;
    transform: translateX(4px);
}

.dashboard-stats-section {
    padding: 60px 0;
    background: #f8f9ff;
    margin-top: -30px;
    position: relative;
    z-index: 2;
}

.stat-card {
    background: white;
    border-radius: 20px;
    padding: 32px 24px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(0, 0, 0, 0.05);
    transition: all 0.3s ease;
    height: 100%;
    position: relative;
    overflow: hidden;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #667eea, #764ba2);
}

.stat-card.primary::before { background: linear-gradient(90deg, #667eea, #764ba2); }
.stat-card.success::before { background: linear-gradient(90deg, #10b981, #059669); }
.stat-card.warning::before { background: linear-gradient(90deg, #f59e0b, #d97706); }
.stat-card.info::before { background: linear-gradient(90deg, #06b6d4, #0891b2); }

.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    margin-bottom: 20px;
    background: linear-gradient(135deg, #f1f5f9, #e2e8f0);
    color: #667eea;
}

.stat-number {
    font-size: 2.5rem;
    font-weight: 800;
    color: #1e293b;
    margin-bottom: 8px;
}

.stat-label {
    color: #64748b;
    font-weight: 500;
    margin-bottom: 12px;
}

.stat-link {
    color: #667eea;
    text-decoration: none;
    font-weight: 500;
    font-size: 0.9rem;
    transition: color 0.3s ease;
}

.stat-link:hover {
    color: #764ba2;
}

.dashboard-content-section {
    padding: 80px 0;
    background: white;
}

.dashboard-widget {
    background: white;
    border-radius: 20px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(0, 0, 0, 0.05);
    height: 100%;
}

.widget-header {
    padding: 24px 24px 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.widget-title {
    display: flex;
    align-items: center;
    gap: 12px;
}

.widget-title h5 {
    color: #1e293b;
    font-weight: 600;
    margin: 0;
}

.widget-icon {
    color: #667eea;
}

.view-all-link {
    color: #667eea;
    text-decoration: none;
    font-weight: 500;
    font-size: 0.9rem;
}

.view-all-link:hover {
    color: #764ba2;
}

.widget-content {
    padding: 0 24px 24px;
}

.empty-state {
    text-align: center;
    padding: 40px 20px;
}

.empty-icon {
    font-size: 3rem;
    color: #9ca3af;
    margin-bottom: 16px;
}

.empty-state h6 {
    color: #374151;
    margin-bottom: 8px;
}

.empty-state p {
    color: #6b7280;
    margin-bottom: 20px;
}

.booking-item, .order-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 0;
    border-bottom: 1px solid #f1f5f9;
}

.booking-item:last-child, .order-item:last-child {
    border-bottom: none;
}

.booking-header, .order-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.booking-pet, .order-number {
    font-weight: 600;
    color: #1e293b;
    margin: 0;
    font-size: 1rem;
}

.booking-status, .order-status {
    padding: 4px 12px;
    border-radius: 50px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.status-pending { background: #fef3c7; color: #92400e; }
.status-confirmed { background: #dcfce7; color: #166534; }
.status-completed { background: #dbeafe; color: #1e40af; }
.status-cancelled { background: #fee2e2; color: #991b1b; }
.status-processing { background: #e0e7ff; color: #3730a3; }
.status-shipped { background: #cffafe; color: #0f766e; }
.status-delivered { background: #dcfce7; color: #166534; }

.booking-sitter, .order-items {
    color: #64748b;
    margin-bottom: 8px;
    font-size: 0.9rem;
}

.booking-date, .order-date {
    color: #9ca3af;
    font-size: 0.8rem;
}

.tips-widget {
    background: linear-gradient(135deg, #f8fafc, #f1f5f9);
    border: 1px solid #e2e8f0;
}

.tips-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
}

.tip-card {
    background: white;
    padding: 20px;
    border-radius: 12px;
    text-align: center;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
}

.tip-icon {
    font-size: 2rem;
    margin-bottom: 12px;
}

.tip-card h6 {
    color: #1e293b;
    margin-bottom: 8px;
    font-weight: 600;
}

.tip-card p {
    color: #64748b;
    font-size: 0.85rem;
    margin: 0;
    line-height: 1.4;
}

.profile-completion {
    display: flex;
    align-items: center;
    gap: 20px;
}

.completion-circle {
    position: relative;
    width: 80px;
    height: 80px;
}

.circle-progress {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: conic-gradient(#667eea 0deg 306deg, #e2e8f0 306deg 360deg);
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
}

.circle-progress::before {
    content: '';
    position: absolute;
    width: 60px;
    height: 60px;
    background: white;
    border-radius: 50%;
}

.percentage {
    position: relative;
    z-index: 2;
    font-weight: 700;
    color: #667eea;
}

.completion-info h6 {
    color: #1e293b;
    margin-bottom: 8px;
    font-weight: 600;
}

.completion-info p {
    color: #64748b;
    font-size: 0.85rem;
    margin-bottom: 16px;
}

@media (max-width: 991px) {
    .welcome-title {
        font-size: 2.2rem;
    }
    
    .quick-actions-widget {
        margin-top: 30px;
    }
    
    .tips-grid {
        grid-template-columns: 1fr;
    }
    
    .profile-completion {
        flex-direction: column;
        text-align: center;
    }
}

@media (max-width: 768px) {
    .booking-item, .order-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 12px;
    }
    
    .booking-actions, .order-actions {
        width: 100%;
    }
    
    .booking-actions .btn, .order-actions .btn {
        width: 100%;
    }
}
</style>

<?php
// Include footer
include_once '../includes/footer.php';

// Close database connection
$conn->close();
?>