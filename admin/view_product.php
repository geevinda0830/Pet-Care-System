<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

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

// Check if product ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "Invalid product ID.";
    header("Location: manage_products.php");
    exit();
}

// Include database connection
require_once '../config/db_connect.php';

$product_id = intval($_GET['id']);

// Fetch product details
$product_sql = "SELECT * FROM pet_food_and_accessories WHERE productID = ?";
$product_stmt = $conn->prepare($product_sql);

if (!$product_stmt) {
    die("Database error: " . $conn->error);
}

$product_stmt->bind_param("i", $product_id);
$product_stmt->execute();
$product_result = $product_stmt->get_result();

if ($product_result->num_rows === 0) {
    $product_stmt->close();
    $conn->close();
    $_SESSION['error_message'] = "Product not found.";
    header("Location: manage_products.php");
    exit();
}

$product = $product_result->fetch_assoc();
$product_stmt->close();

// Get product usage statistics
$stats = [];
try {
    // Get cart count
    $cart_sql = "SELECT COUNT(*) as cart_count FROM cart_items WHERE productID = ?";
    $cart_stmt = $conn->prepare($cart_sql);
    if ($cart_stmt) {
        $cart_stmt->bind_param("i", $product_id);
        $cart_stmt->execute();
        $cart_result = $cart_stmt->get_result();
        $stats['cart_count'] = $cart_result->fetch_assoc()['cart_count'] ?? 0;
        $cart_stmt->close();
    } else {
        $stats['cart_count'] = 0;
    }
    
    // Get order count (if order_items table exists)
    $order_sql = "SELECT COUNT(*) as order_count FROM order_items WHERE productID = ?";
    $order_stmt = $conn->prepare($order_sql);
    if ($order_stmt) {
        $order_stmt->bind_param("i", $product_id);
        $order_stmt->execute();
        $order_result = $order_stmt->get_result();
        $stats['order_count'] = $order_result->fetch_assoc()['order_count'] ?? 0;
        $order_stmt->close();
    } else {
        $stats['order_count'] = 0;
    }
} catch (Exception $e) {
    // Tables might not exist, set defaults
    $stats['cart_count'] = 0;
    $stats['order_count'] = 0;
}

// Include header
include_once '../includes/header.php';
?>

<style>
.admin-view-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 60px 0 40px;
    position: relative;
    overflow: hidden;
}

.admin-view-particles {
    position: absolute;
    width: 100%;
    height: 100%;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="20" cy="20" r="2" fill="rgba(255,255,255,0.1)"/><circle cx="80" cy="40" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="40" cy="80" r="1.5" fill="rgba(255,255,255,0.1)"/></svg>');
    animation: float 20s infinite linear;
}

@keyframes float {
    0% { transform: translateY(0px) rotate(0deg); }
    100% { transform: translateY(-100px) rotate(360deg); }
}

.admin-header-content {
    position: relative;
    z-index: 2;
}

.admin-badge {
    display: inline-flex;
    align-items: center;
    background: rgba(255, 255, 255, 0.2);
    padding: 8px 16px;
    border-radius: 50px;
    margin-bottom: 16px;
    backdrop-filter: blur(10px);
    font-size: 0.9rem;
    font-weight: 600;
}

.admin-title {
    font-size: 2.5rem;
    font-weight: 800;
    margin-bottom: 10px;
}

.product-id-badge {
    background: rgba(255, 255, 255, 0.3);
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 0.9rem;
    font-weight: 600;
    display: inline-block;
}

.btn-glass {
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    color: white;
    padding: 12px 24px;
    border-radius: 50px;
    backdrop-filter: blur(10px);
    transition: all 0.3s ease;
    margin: 0 8px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-glass:hover {
    background: rgba(255, 255, 255, 0.2);
    color: white;
    transform: translateY(-2px);
    text-decoration: none;
}

.admin-content {
    padding: 60px 0;
    background: #f8f9fa;
}

.admin-product-card {
    background: white;
    border-radius: 20px;
    box-shadow: 0 15px 35px rgba(0,0,0,0.1);
    overflow: hidden;
    margin-bottom: 30px;
}

.card-header-admin {
    background: linear-gradient(135deg, #f8f9ff, #e8f4f8);
    padding: 30px;
    border-bottom: 1px solid #e5e7eb;
}

.card-body-admin {
    padding: 40px;
}

.product-grid {
    display: grid;
    grid-template-columns: 350px 1fr;
    gap: 40px;
    margin-bottom: 40px;
}

.product-image-container {
    text-align: center;
}

.admin-product-image {
    max-width: 100%;
    max-height: 350px;
    object-fit: contain;
    border-radius: 15px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
    background: #f8f9fa;
    padding: 20px;
}

.admin-image-placeholder {
    width: 300px;
    height: 300px;
    background: #e9ecef;
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #6c757d;
    font-size: 4rem;
    margin: 0 auto;
}

.product-details-grid {
    display: grid;
    gap: 20px;
}

.detail-row {
    display: flex;
    padding: 15px 0;
    border-bottom: 1px solid #f1f5f9;
}

.detail-row:last-child {
    border-bottom: none;
}

.detail-label {
    font-weight: 700;
    color: #374151;
    min-width: 180px;
    flex-shrink: 0;
}

.detail-value {
    color: #6b7280;
    flex: 1;
}

.price-value {
    font-size: 1.5rem;
    font-weight: 800;
    color: #667eea;
}

.stock-status {
    display: inline-block;
    padding: 8px 16px;
    border-radius: 25px;
    font-size: 0.9rem;
    font-weight: 600;
}

.status-in-stock {
    background: #d4edda;
    color: #155724;
}

.status-low-stock {
    background: #fff3cd;
    color: #856404;
}

.status-out-of-stock {
    background: #f8d7da;
    color: #721c24;
}

.description-section {
    background: #f8f9fa;
    padding: 25px;
    border-radius: 15px;
    margin-bottom: 30px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    padding: 25px;
    border-radius: 15px;
    text-align: center;
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
}

.stat-number {
    font-size: 2.5rem;
    font-weight: 800;
    margin-bottom: 8px;
}

.stat-label {
    font-size: 0.9rem;
    opacity: 0.9;
}

.action-buttons {
    display: flex;
    gap: 15px;
    justify-content: center;
    flex-wrap: wrap;
}

.btn-admin-action {
    padding: 12px 24px;
    border-radius: 10px;
    font-weight: 600;
    font-size: 1rem;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    border: none;
    cursor: pointer;
}

.btn-primary-admin {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
}

.btn-primary-admin:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
    color: white;
    text-decoration: none;
}

.btn-secondary-admin {
    background: #f8f9fa;
    color: #6c757d;
    border: 2px solid #e5e7eb;
}

.btn-secondary-admin:hover {
    background: #e9ecef;
    color: #495057;
    text-decoration: none;
}

.btn-danger-admin {
    background: #ef4444;
    color: white;
}

.btn-danger-admin:hover {
    background: #dc2626;
    transform: translateY(-2px);
    box-shadow: 0 10px 30px rgba(239, 68, 68, 0.3);
    color: white;
}

@media (max-width: 768px) {
    .admin-title {
        font-size: 2rem;
    }
    
    .product-grid {
        grid-template-columns: 1fr;
        gap: 30px;
    }
    
    .card-body-admin {
        padding: 25px;
    }
    
    .action-buttons {
        flex-direction: column;
    }
    
    .btn-admin-action {
        width: 100%;
        justify-content: center;
    }
    
    .btn-glass {
        margin: 4px 0;
    }
}
</style>

<!-- Admin View Header -->
<section class="admin-view-header">
    <div class="admin-view-particles"></div>
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <div class="admin-header-content">
                    <div class="admin-badge">
                        <i class="fas fa-eye me-2"></i>
                        Product Details - Admin View
                    </div>
                    <h1 class="admin-title"><?php echo htmlspecialchars($product['name']); ?></h1>
                    <div class="product-id-badge">Product ID: #<?php echo $product['productID']; ?></div>
                </div>
            </div>
            <div class="col-lg-4 text-lg-end">
                <a href="manage_products.php" class="btn-glass">
                    <i class="fas fa-arrow-left"></i> Back to Products
                </a>
                <a href="edit_product.php?id=<?php echo $product['productID']; ?>" class="btn-glass">
                    <i class="fas fa-edit"></i> Edit Product
                </a>
            </div>
        </div>
    </div>
</section>

<!-- Admin Content -->
<section class="admin-content">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-12">
                <!-- Product Information Card -->
                <div class="admin-product-card">
                    <div class="card-header-admin">
                        <h4><i class="fas fa-info-circle me-2"></i>Product Information</h4>
                    </div>
                    
                    <div class="card-body-admin">
                        <!-- Product Grid -->
                        <div class="product-grid">
                            <!-- Product Image -->
                            <div class="product-image-container">
                                <?php if (!empty($product['image']) && file_exists('../assets/images/products/' . $product['image'])): ?>
                                    <img src="../assets/images/products/<?php echo htmlspecialchars($product['image']); ?>" 
                                         alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                         class="admin-product-image">
                                <?php else: ?>
                                    <div class="admin-image-placeholder">
                                        <i class="fas fa-image"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Product Details -->
                            <div class="product-details-grid">
                                <div class="detail-row">
                                    <div class="detail-label">Product Name:</div>
                                    <div class="detail-value"><?php echo htmlspecialchars($product['name']); ?></div>
                                </div>
                                
                                <div class="detail-row">
                                    <div class="detail-label">Brand:</div>
                                    <div class="detail-value"><?php echo htmlspecialchars($product['brand']); ?></div>
                                </div>
                                
                                <div class="detail-row">
                                    <div class="detail-label">Category:</div>
                                    <div class="detail-value"><?php echo htmlspecialchars($product['category']); ?></div>
                                </div>
                                
                                <div class="detail-row">
                                    <div class="detail-label">Price:</div>
                                    <div class="detail-value">
                                        <span class="price-value">Rs.<?php echo number_format($product['price'], 2); ?></span>
                                    </div>
                                </div>
                                
                                <div class="detail-row">
                                    <div class="detail-label">Weight:</div>
                                    <div class="detail-value"><?php echo htmlspecialchars($product['weight']); ?></div>
                                </div>
                                
                                <div class="detail-row">
                                    <div class="detail-label">Stock Status:</div>
                                    <div class="detail-value">
                                        <?php 
                                        $stock = intval($product['stock']);
                                        $status_class = '';
                                        $status_text = '';
                                        
                                        if ($stock == 0) {
                                            $status_class = 'status-out-of-stock';
                                            $status_text = 'Out of Stock';
                                        } elseif ($stock <= 10) {
                                            $status_class = 'status-low-stock';
                                            $status_text = "Low Stock ({$stock} left)";
                                        } else {
                                            $status_class = 'status-in-stock';
                                            $status_text = "In Stock ({$stock} available)";
                                        }
                                        ?>
                                        <span class="stock-status <?php echo $status_class; ?>">
                                            <?php echo $status_text; ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="detail-row">
                                    <div class="detail-label">Date Added:</div>
                                    <div class="detail-value">
                                        <?php 
                                        if (isset($product['created_at']) && !empty($product['created_at'])) {
                                            echo date('F j, Y g:i A', strtotime($product['created_at'])); 
                                        } else {
                                            echo 'Not available';
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Product Description -->
                        <?php if (!empty($product['description'])): ?>
                            <div class="description-section">
                                <h5><i class="fas fa-align-left me-2"></i>Product Description</h5>
                                <p style="margin: 15px 0 0 0; line-height: 1.6;"><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Product Usage Statistics -->
                        <div class="stats-grid">
                            <div class="stat-card">
                                <div class="stat-number"><?php echo $stats['cart_count']; ?></div>
                                <div class="stat-label">In Customer Carts</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-number"><?php echo $stats['order_count']; ?></div>
                                <div class="stat-label">Times Ordered</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-number"><?php echo $stock; ?></div>
                                <div class="stat-label">Current Stock</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-number">Rs.<?php echo number_format($product['price'] * $stock, 2); ?></div>
                                <div class="stat-label">Inventory Value</div>
                            </div>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="action-buttons">
                            <a href="manage_products.php" class="btn-admin-action btn-secondary-admin">
                                <i class="fas fa-list"></i> Back to Products
                            </a>
                            <a href="edit_product.php?id=<?php echo $product['productID']; ?>" class="btn-admin-action btn-primary-admin">
                                <i class="fas fa-edit"></i> Edit Product
                            </a>
                            <button class="btn-admin-action btn-danger-admin" onclick="deleteProduct(<?php echo $product['productID']; ?>)">
                                <i class="fas fa-trash"></i> Delete Product
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
// Delete product function
function deleteProduct(productId) {
    if (confirm('Are you sure you want to delete this product? This action cannot be undone.')) {
        // Show loading
        const button = event.target;
        const originalText = button.innerHTML;
        button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Deleting...';
        button.disabled = true;
        
        // Create form and submit
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'manage_products.php';
        
        const deleteInput = document.createElement('input');
        deleteInput.type = 'hidden';
        deleteInput.name = 'delete_product';
        deleteInput.value = '1';
        
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'product_id';
        idInput.value = productId;
        
        form.appendChild(deleteInput);
        form.appendChild(idInput);
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php
// Include footer
include_once '../includes/footer.php';

// Close database connection
$conn->close();
?>