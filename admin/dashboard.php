<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in as admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    $_SESSION['error_message'] = "You must be logged in as an administrator to access this page.";
    header("Location: ../login.php");
    exit();
}

// Include database connection
require_once '../config/db_connect.php';

// Get essential statistics only
$stats = [];

// Total users
$users_sql = "SELECT 
                (SELECT COUNT(*) FROM pet_owner) as pet_owners,
                (SELECT COUNT(*) FROM pet_sitter) as pet_sitters";
$users_result = $conn->query($users_sql);
$users_data = $users_result->fetch_assoc();
$stats['total_users'] = $users_data['pet_owners'] + $users_data['pet_sitters'];
$stats['pet_owners'] = $users_data['pet_owners'];
$stats['pet_sitters'] = $users_data['pet_sitters'];

// Pending sitters
$pending_sql = "SELECT COUNT(*) as pending FROM pet_sitter WHERE approval_status = 'pending'";
$pending_result = $conn->query($pending_sql);
$stats['pending_sitters'] = $pending_result->fetch_assoc()['pending'];

// Total products
$products_sql = "SELECT COUNT(*) as total FROM pet_food_and_accessories";
$products_result = $conn->query($products_sql);
$stats['total_products'] = $products_result->fetch_assoc()['total'];

// Low stock products
$low_stock_sql = "SELECT COUNT(*) as low_stock FROM pet_food_and_accessories WHERE stock <= 10 AND stock > 0";
$low_stock_result = $conn->query($low_stock_sql);
$stats['low_stock'] = $low_stock_result->fetch_assoc()['low_stock'];

// Include header
include_once '../includes/header.php';
?>

<style>
/* Clean Admin Dashboard Styles */
.admin-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 60px 0;
    text-align: center;
}

.admin-title {
    font-size: 2.5rem;
    font-weight: 700;
    margin-bottom: 15px;
}

.admin-subtitle {
    font-size: 1.1rem;
    opacity: 0.9;
    margin-bottom: 0;
}

.dashboard-content {
    padding: 60px 0;
    background: #f8f9fa;
}

/* Statistics Cards */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 30px;
    margin-bottom: 50px;
}

.stat-card {
    background: white;
    padding: 30px;
    border-radius: 15px;
    text-align: center;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
    border-left: 4px solid #667eea;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
}

.stat-icon {
    width: 70px;
    height: 70px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
    color: white;
    font-size: 1.8rem;
}

.stat-number {
    font-size: 2.5rem;
    font-weight: 800;
    color: #1e293b;
    margin-bottom: 10px;
    display: block;
}

.stat-label {
    color: #64748b;
    font-weight: 600;
    font-size: 1.1rem;
    margin-bottom: 10px;
}

.stat-details {
    color: #9ca3af;
    font-size: 0.9rem;
}

.pending-badge {
    background: #fef3c7;
    color: #92400e;
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    margin-top: 10px;
    display: inline-block;
}

/* Quick Actions */
.quick-actions {
    background: white;
    padding: 40px;
    border-radius: 20px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    margin-bottom: 40px;
}

.section-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 30px;
    text-align: center;
}

.actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
}

.action-card {
    background: #f8f9fa;
    padding: 25px;
    border-radius: 15px;
    text-align: center;
    text-decoration: none;
    color: #1e293b;
    transition: all 0.3s ease;
    border: 2px solid transparent;
}

.action-card:hover {
    background: #667eea;
    color: white;
    text-decoration: none;
    transform: translateY(-3px);
    border-color: #667eea;
}

.action-icon {
    font-size: 2rem;
    margin-bottom: 15px;
    color: #667eea;
}

.action-card:hover .action-icon {
    color: white;
}

.action-title {
    font-weight: 600;
    font-size: 1.1rem;
    margin-bottom: 8px;
}

.action-description {
    font-size: 0.9rem;
    opacity: 0.8;
}

/* Recent Activity */
.recent-activity {
    background: white;
    padding: 40px;
    border-radius: 20px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
}

.activity-tabs {
    display: flex;
    gap: 10px;
    margin-bottom: 30px;
    border-bottom: 2px solid #f1f5f9;
}

.tab-btn {
    background: none;
    border: none;
    padding: 12px 20px;
    font-weight: 600;
    color: #64748b;
    cursor: pointer;
    border-bottom: 2px solid transparent;
    transition: all 0.3s ease;
}

.tab-btn.active {
    color: #667eea;
    border-bottom-color: #667eea;
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

.activity-item {
    display: flex;
    align-items: center;
    padding: 15px;
    border-bottom: 1px solid #f1f5f9;
    transition: all 0.3s ease;
}

.activity-item:hover {
    background: #f8f9fa;
}

.activity-item:last-child {
    border-bottom: none;
}

.activity-avatar {
    width: 45px;
    height: 45px;
    background: #667eea;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    margin-right: 15px;
    font-weight: 600;
}

.activity-content {
    flex: 1;
}

.activity-title {
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 5px;
}

.activity-meta {
    color: #9ca3af;
    font-size: 0.9rem;
}

.empty-state {
    text-align: center;
    padding: 40px;
    color: #9ca3af;
}

.empty-icon {
    font-size: 3rem;
    margin-bottom: 15px;
    color: #d1d5db;
}

/* Responsive Design */
@media (max-width: 768px) {
    .admin-title {
        font-size: 2rem;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
        gap: 20px;
    }
    
    .actions-grid {
        grid-template-columns: 1fr;
    }
    
    .activity-tabs {
        flex-wrap: wrap;
    }
    
    .quick-actions,
    .recent-activity {
        padding: 25px;
    }
}
</style>

<!-- Clean Admin Header -->
<section class="admin-header">
    <div class="container">
        <h1 class="admin-title">
            <i class="fas fa-tachometer-alt me-3"></i>
            Admin Dashboard
        </h1>
        <p class="admin-subtitle">Manage your Pet Care & Sitting System</p>
    </div>
</section>

<!-- Dashboard Content -->
<section class="dashboard-content">
    <div class="container">
        
        <!-- Statistics Cards -->
        <div class="stats-grid">
            <!-- Total Users -->
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <span class="stat-number"><?php echo $stats['total_users']; ?></span>
                <div class="stat-label">Total Users</div>
                <div class="stat-details">
                    <?php echo $stats['pet_owners']; ?> Pet Owners<br>
                    <?php echo $stats['pet_sitters']; ?> Pet Sitters
                </div>
                <?php if ($stats['pending_sitters'] > 0): ?>
                    <div class="pending-badge">
                        <?php echo $stats['pending_sitters']; ?> Pending Approval
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Products -->
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-box"></i>
                </div>
                <span class="stat-number"><?php echo $stats['total_products']; ?></span>
                <div class="stat-label">Total Products</div>
                <div class="stat-details">
                    In Store Inventory
                </div>
                <?php if ($stats['low_stock'] > 0): ?>
                    <div class="pending-badge">
                        <?php echo $stats['low_stock']; ?> Low Stock
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- System Status -->
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-server"></i>
                </div>
                <span class="stat-number" style="color: #10b981;">Online</span>
                <div class="stat-label">System Status</div>
                <div class="stat-details">
                    All services running
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <h2 class="section-title">Quick Actions</h2>
            <div class="actions-grid">
                <a href="users.php" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="action-title">Manage Users</div>
                    <div class="action-description">View and manage pet owners & sitters</div>
                </a>
                
                <a href="manage_products.php" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-boxes"></i>
                    </div>
                    <div class="action-title">Manage Products</div>
                    <div class="action-description">Add, edit and manage store inventory</div>
                </a>
                
                <a href="pet_sitter_approval.php" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <div class="action-title">Approve Sitters</div>
                    <div class="action-description">Review pending sitter applications</div>
                </a>
                
                <a href="order_management.php" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="action-title">View Orders</div>
                    <div class="action-description">Monitor customer orders</div>
                </a>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="recent-activity">
            <h2 class="section-title">Recent Activity</h2>
            
            <div class="activity-tabs">
                <button class="tab-btn active" onclick="showTab('users')">New Users</button>
                <button class="tab-btn" onclick="showTab('sitters')">Pending Sitters</button>
            </div>
            
            <!-- New Users Tab -->
            <div id="users-tab" class="tab-content active">
                <?php
                $recent_users_sql = "SELECT * FROM (
                                      SELECT userID, 'Pet Owner' as type, fullName, email, created_at 
                                      FROM pet_owner 
                                      UNION ALL 
                                      SELECT userID, 'Pet Sitter' as type, fullName, email, created_at 
                                      FROM pet_sitter
                                    ) as users 
                                    ORDER BY created_at DESC LIMIT 5";
                $recent_users_result = $conn->query($recent_users_sql);
                
                if ($recent_users_result->num_rows > 0):
                    while ($user = $recent_users_result->fetch_assoc()):
                ?>
                    <div class="activity-item">
                        <div class="activity-avatar">
                            <?php echo strtoupper(substr($user['fullName'], 0, 1)); ?>
                        </div>
                        <div class="activity-content">
                            <div class="activity-title"><?php echo htmlspecialchars($user['fullName']); ?></div>
                            <div class="activity-meta">
                                <?php echo $user['type']; ?> • <?php echo htmlspecialchars($user['email']); ?> • 
                                <?php echo date('M j, Y', strtotime($user['created_at'])); ?>
                            </div>
                        </div>
                    </div>
                <?php 
                    endwhile;
                else:
                ?>
                    <div class="empty-state">
                        <div class="empty-icon"><i class="fas fa-users"></i></div>
                        <p>No recent users</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Pending Sitters Tab -->
            <div id="sitters-tab" class="tab-content">
                <?php
                $pending_sitters_sql = "SELECT userID, fullName, email, created_at 
                                       FROM pet_sitter 
                                       WHERE approval_status = 'pending' 
                                       ORDER BY created_at DESC LIMIT 5";
                $pending_sitters_result = $conn->query($pending_sitters_sql);
                
                if ($pending_sitters_result->num_rows > 0):
                    while ($sitter = $pending_sitters_result->fetch_assoc()):
                ?>
                    <div class="activity-item">
                        <div class="activity-avatar" style="background: #f59e0b;">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="activity-content">
                            <div class="activity-title"><?php echo htmlspecialchars($sitter['fullName']); ?></div>
                            <div class="activity-meta">
                                Pending Approval • <?php echo htmlspecialchars($sitter['email']); ?> • 
                                <?php echo date('M j, Y', strtotime($sitter['created_at'])); ?>
                            </div>
                        </div>
                        <a href="view_sitter.php?id=<?php echo $sitter['userID']; ?>" class="btn btn-sm btn-outline-primary">
                            Review
                        </a>
                    </div>
                <?php 
                    endwhile;
                else:
                ?>
                    <div class="empty-state">
                        <div class="empty-icon"><i class="fas fa-check-circle"></i></div>
                        <p>No pending approvals</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<script>
function showTab(tabName) {
    // Hide all tab contents
    const tabContents = document.querySelectorAll('.tab-content');
    tabContents.forEach(content => content.classList.remove('active'));
    
    // Remove active class from all buttons
    const tabBtns = document.querySelectorAll('.tab-btn');
    tabBtns.forEach(btn => btn.classList.remove('active'));
    
    // Show selected tab content
    document.getElementById(tabName + '-tab').classList.add('active');
    
    // Add active class to clicked button
    event.target.classList.add('active');
}
</script>

<?php
// Include footer
include_once '../includes/footer.php';

// Close database connection
$conn->close();
?>