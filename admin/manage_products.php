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

// Include database connection
require_once '../config/db_connect.php';

// Handle product deletion - FIXED VERSION
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_product']) && isset($_POST['product_id'])) {
    $product_id = intval($_POST['product_id']);
    
    if ($product_id > 0) {
        try {
            // Start transaction
            $conn->autocommit(FALSE);
            
            // Get product image first for deletion
            $get_image_sql = "SELECT image FROM pet_food_and_accessories WHERE productID = ?";
            $get_image_stmt = $conn->prepare($get_image_sql);
            $get_image_stmt->bind_param("i", $product_id);
            $get_image_stmt->execute();
            $image_result = $get_image_stmt->get_result();
            $product_data = $image_result->fetch_assoc();
            $get_image_stmt->close();
            
            if ($product_data) {
                // Remove from cart_items first (if exists)
                $remove_cart_sql = "DELETE FROM cart_items WHERE productID = ?";
                $remove_cart_stmt = $conn->prepare($remove_cart_sql);
                if ($remove_cart_stmt) {
                    $remove_cart_stmt->bind_param("i", $product_id);
                    $remove_cart_stmt->execute();
                    $remove_cart_stmt->close();
                }
                
                // Remove from order_items (if table exists)
                $table_check_sql = "SHOW TABLES LIKE 'order_items'";
                $table_check_result = $conn->query($table_check_sql);
                if ($table_check_result && $table_check_result->num_rows > 0) {
                    $remove_order_sql = "DELETE FROM order_items WHERE productID = ?";
                    $remove_order_stmt = $conn->prepare($remove_order_sql);
                    if ($remove_order_stmt) {
                        $remove_order_stmt->bind_param("i", $product_id);
                        $remove_order_stmt->execute();
                        $remove_order_stmt->close();
                    }
                }
                
                // Delete the product
                $delete_product_sql = "DELETE FROM pet_food_and_accessories WHERE productID = ?";
                $delete_product_stmt = $conn->prepare($delete_product_sql);
                $delete_product_stmt->bind_param("i", $product_id);
                
                if ($delete_product_stmt->execute()) {
                    if ($delete_product_stmt->affected_rows > 0) {
                        // Product deleted successfully
                        
                        // Delete image file if exists
                        if (!empty($product_data['image'])) {
                            $image_path = '../assets/images/products/' . $product_data['image'];
                            if (file_exists($image_path)) {
                                unlink($image_path);
                            }
                        }
                        
                        // Commit transaction
                        $conn->commit();
                        $_SESSION['success_message'] = "Product deleted successfully!";
                        
                    } else {
                        throw new Exception("Product not found or already deleted.");
                    }
                } else {
                    throw new Exception("Failed to delete product: " . $conn->error);
                }
                
                $delete_product_stmt->close();
                
            } else {
                throw new Exception("Product not found.");
            }
            
        } catch (Exception $e) {
            // Rollback transaction
            $conn->rollback();
            $_SESSION['error_message'] = "Error deleting product: " . $e->getMessage();
        }
        
        // Re-enable autocommit
        $conn->autocommit(TRUE);
    } else {
        $_SESSION['error_message'] = "Invalid product ID.";
    }
    
    // Redirect to prevent form resubmission
    header("Location: manage_products.php");
    exit();
}

// Handle search and filters
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';
$stock_filter = isset($_GET['stock_status']) ? $_GET['stock_status'] : '';
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'name';

// Build SQL query
$sql = "SELECT * FROM pet_food_and_accessories WHERE 1=1";
$params = [];
$param_types = "";

if (!empty($search_query)) {
    $sql .= " AND (name LIKE ? OR brand LIKE ? OR description LIKE ?)";
    $search_param = "%$search_query%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= "sss";
}

if (!empty($category_filter)) {
    $sql .= " AND category = ?";
    $params[] = $category_filter;
    $param_types .= "s";
}

if (!empty($stock_filter)) {
    switch ($stock_filter) {
        case 'in_stock':
            $sql .= " AND stock > 10";
            break;
        case 'low_stock':
            $sql .= " AND stock <= 10 AND stock > 0";
            break;
        case 'out_of_stock':
            $sql .= " AND stock = 0";
            break;
    }
}

// Add sorting
switch ($sort_by) {
    case 'name':
        $sql .= " ORDER BY name ASC";
        break;
    case 'price_low':
        $sql .= " ORDER BY price ASC";
        break;
    case 'price_high':
        $sql .= " ORDER BY price DESC";
        break;
    case 'stock_low':
        $sql .= " ORDER BY stock ASC";
        break;
    case 'stock_high':
        $sql .= " ORDER BY stock DESC";
        break;
    case 'newest':
        $sql .= " ORDER BY productID DESC";
        break;
    default:
        $sql .= " ORDER BY name ASC";
}

// Execute query
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$products = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get total counts for dashboard stats
$total_products_sql = "SELECT COUNT(*) as total FROM pet_food_and_accessories";
$total_result = $conn->query($total_products_sql);
$total_products = $total_result->fetch_assoc()['total'];

$low_stock_sql = "SELECT COUNT(*) as low_stock FROM pet_food_and_accessories WHERE stock <= 10 AND stock > 0";
$low_stock_result = $conn->query($low_stock_sql);
$low_stock_count = $low_stock_result->fetch_assoc()['low_stock'];

$out_of_stock_sql = "SELECT COUNT(*) as out_of_stock FROM pet_food_and_accessories WHERE stock = 0";
$out_of_stock_result = $conn->query($out_of_stock_sql);
$out_of_stock_count = $out_of_stock_result->fetch_assoc()['out_of_stock'];

// Include header
include_once '../includes/header.php';
?>

<style>
.manage-products-section {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    position: relative;
    overflow: hidden;
    padding: 80px 0 60px;
}

.manage-products-particles {
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

.manage-products-header {
    position: relative;
    z-index: 2;
}

.manage-products-badge {
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

.manage-products-title {
    font-size: 3rem;
    font-weight: 800;
    line-height: 1.2;
    margin-bottom: 16px;
}

.manage-products-subtitle {
    font-size: 1.2rem;
    opacity: 0.9;
    line-height: 1.6;
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

.manage-products-content {
    padding: 80px 0;
    background: #f8f9fa;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 40px;
}

.stat-card {
    background: white;
    padding: 25px;
    border-radius: 15px;
    text-align: center;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.15);
}

.stat-number {
    font-size: 2.5rem;
    font-weight: 800;
    margin-bottom: 8px;
}

.stat-number.total { color: #667eea; }
.stat-number.low { color: #f59e0b; }
.stat-number.out { color: #ef4444; }

.stat-label {
    color: #6b7280;
    font-weight: 600;
}

.filters-section {
    background: white;
    padding: 30px;
    border-radius: 15px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    margin-bottom: 30px;
}

.filters-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    align-items: end;
}

.filter-group label {
    display: block;
    font-weight: 600;
    color: #374151;
    margin-bottom: 8px;
}

.filter-control {
    width: 100%;
    padding: 10px 12px;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    font-size: 0.95rem;
    transition: all 0.3s ease;
}

.filter-control:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.btn-filter {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    border: none;
    padding: 12px 20px;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-filter:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
}

.products-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 25px;
}

.product-card-admin {
    background: white;
    border-radius: 20px;
    padding: 25px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.product-card-admin:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 40px rgba(0,0,0,0.15);
}

.product-badge-admin {
    position: absolute;
    top: 15px;
    right: 15px;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
}

.badge-in-stock {
    background: #d4edda;
    color: #155724;
}

.badge-low-stock {
    background: #fff3cd;
    color: #856404;
}

.badge-out-stock {
    background: #f8d7da;
    color: #721c24;
}

.product-image-admin {
    width: 100%;
    height: 200px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f8f9fa;
    border-radius: 15px;
    margin-bottom: 20px;
    overflow: hidden;
}

.product-image-admin img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
    border-radius: 10px;
}

.image-placeholder {
    color: #cbd5e0;
    font-size: 3rem;
}

.product-content-admin {
    
}

.product-name-admin {
    font-size: 1.3rem;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 8px;
    line-height: 1.3;
}

.product-brand-admin {
    color: #64748b;
    font-size: 0.95rem;
    margin-bottom: 16px;
    font-weight: 500;
}

.product-meta-admin {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    margin-bottom: 16px;
}

.meta-item-admin {
    text-align: center;
    padding: 12px;
    background: #f8f9ff;
    border-radius: 8px;
}

.meta-label-admin {
    color: #64748b;
    font-size: 0.8rem;
    font-weight: 600;
    display: block;
    margin-bottom: 4px;
}

.meta-value-admin {
    color: #1e293b;
    font-weight: 700;
    font-size: 1rem;
}

.stock-low { color: #f59e0b !important; }
.stock-out { color: #ef4444 !important; }

.product-description-admin {
    color: #6b7280;
    font-size: 0.9rem;
    line-height: 1.5;
    margin-bottom: 20px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.product-actions-admin {
    display: flex;
    gap: 8px;
}

.btn-admin {
    flex: 1;
    padding: 10px 16px;
    border-radius: 8px;
    font-size: 0.85rem;
    font-weight: 600;
    transition: all 0.3s ease;
    text-decoration: none;
    text-align: center;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    cursor: pointer;
    border: none;
}

.btn-edit {
    background: #3b82f6;
    color: white;
}

.btn-edit:hover {
    background: #2563eb;
    color: white;
    text-decoration: none;
}

.btn-delete {
    background: #ef4444;
    color: white;
}

.btn-delete:hover {
    background: #dc2626;
    color: white;
}

.btn-view {
    background: #10b981;
    color: white;
}

.btn-view:hover {
    background: #059669;
    color: white;
    text-decoration: none;
}

.add-product-card {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    border-radius: 20px;
    padding: 40px 24px;
    min-height: 400px;
    transition: all 0.3s ease;
    text-decoration: none;
}

.add-product-card:hover {
    color: white;
    transform: translateY(-4px);
    box-shadow: 0 20px 40px rgba(102, 126, 234, 0.3);
    text-decoration: none;
}

.add-icon {
    width: 80px;
    height: 80px;
    border: 3px dashed rgba(255, 255, 255, 0.5);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 24px;
    font-size: 2rem;
}

.add-title {
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 8px;
}

.add-subtitle {
    opacity: 0.8;
    line-height: 1.5;
}

.alert-modern {
    border: none;
    border-radius: 15px;
    padding: 20px 25px;
    margin-bottom: 25px;
    font-weight: 500;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
    gap: 10px;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border-left: 4px solid #28a745;
}

.alert-danger {
    background: #f8d7da;
    color: #721c24;
    border-left: 4px solid #dc3545;
}

.no-products {
    text-align: center;
    padding: 60px 20px;
    color: #6b7280;
}

.no-products i {
    font-size: 4rem;
    margin-bottom: 20px;
    color: #cbd5e0;
}

@media (max-width: 768px) {
    .manage-products-title {
        font-size: 2rem;
    }
    
    .products-grid {
        grid-template-columns: 1fr;
    }
    
    .filters-grid {
        grid-template-columns: 1fr;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .product-actions-admin {
        flex-direction: column;
        gap: 10px;
    }
}
</style>

<!-- Manage Products Header -->
<section class="manage-products-section">
    <div class="manage-products-particles"></div>
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <div class="manage-products-header">
                    <div class="manage-products-badge">
                        <i class="fas fa-boxes me-2"></i>
                        Product Management
                    </div>
                    <h1 class="manage-products-title">Manage Products</h1>
                    <p class="manage-products-subtitle">Add, edit, and manage your product inventory</p>
                </div>
            </div>
            <div class="col-lg-4 text-lg-end">
                <a href="add_product.php" class="btn-glass">
                    <i class="fas fa-plus me-2"></i>Add New Product
                </a>
                <a href="dashboard.php" class="btn-glass">
                    <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                </a>
            </div>
        </div>
    </div>
</section>

<!-- Manage Products Content -->
<section class="manage-products-content">
    <div class="container">
        
        <!-- Success/Error Messages -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-modern">
                <i class="fas fa-check-circle"></i>
                <span>
                    <?php 
                    echo $_SESSION['success_message']; 
                    unset($_SESSION['success_message']);
                    ?>
                </span>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-modern">
                <i class="fas fa-exclamation-triangle"></i>
                <span>
                    <?php 
                    echo $_SESSION['error_message']; 
                    unset($_SESSION['error_message']);
                    ?>
                </span>
            </div>
        <?php endif; ?>
        
        <!-- Dashboard Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number total"><?php echo $total_products; ?></div>
                <div class="stat-label">Total Products</div>
            </div>
            <div class="stat-card">
                <div class="stat-number low"><?php echo $low_stock_count; ?></div>
                <div class="stat-label">Low Stock</div>
            </div>
            <div class="stat-card">
                <div class="stat-number out"><?php echo $out_of_stock_count; ?></div>
                <div class="stat-label">Out of Stock</div>
            </div>
            <div class="stat-card">
                <div class="stat-number total"><?php echo count($products); ?></div>
                <div class="stat-label">Filtered Results</div>
            </div>
        </div>
        
        <!-- Filters Section -->
        <div class="filters-section">
            <form method="GET" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <div class="filters-grid">
                    <div class="filter-group">
                        <label>Search Products</label>
                        <input type="text" name="search" class="filter-control" 
                               value="<?php echo htmlspecialchars($search_query); ?>" 
                               placeholder="Search by name, brand, or description...">
                    </div>
                    
                    <div class="filter-group">
                        <label>Category</label>
                        <select name="category" class="filter-control">
                            <option value="">All Categories</option>
                            <option value="Dog Food" <?php echo ($category_filter === 'Dog Food') ? 'selected' : ''; ?>>Dog Food</option>
                            <option value="Cat Food" <?php echo ($category_filter === 'Cat Food') ? 'selected' : ''; ?>>Cat Food</option>
                            <option value="Bird Food" <?php echo ($category_filter === 'Bird Food') ? 'selected' : ''; ?>>Bird Food</option>
                            <option value="Fish Food" <?php echo ($category_filter === 'Fish Food') ? 'selected' : ''; ?>>Fish Food</option>
                            <option value="Toys" <?php echo ($category_filter === 'Toys') ? 'selected' : ''; ?>>Toys</option>
                            <option value="Accessories" <?php echo ($category_filter === 'Accessories') ? 'selected' : ''; ?>>Accessories</option>
                            <option value="Medicine" <?php echo ($category_filter === 'Medicine') ? 'selected' : ''; ?>>Medicine</option>
                            <option value="Grooming" <?php echo ($category_filter === 'Grooming') ? 'selected' : ''; ?>>Grooming</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>Stock Status</label>
                        <select name="stock_status" class="filter-control">
                            <option value="">All Stock Levels</option>
                            <option value="in_stock" <?php echo ($stock_filter === 'in_stock') ? 'selected' : ''; ?>>In Stock</option>
                            <option value="low_stock" <?php echo ($stock_filter === 'low_stock') ? 'selected' : ''; ?>>Low Stock</option>
                            <option value="out_of_stock" <?php echo ($stock_filter === 'out_of_stock') ? 'selected' : ''; ?>>Out of Stock</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>Sort By</label>
                        <select name="sort_by" class="filter-control">
                            <option value="name" <?php echo ($sort_by === 'name') ? 'selected' : ''; ?>>Name (A-Z)</option>
                            <option value="price_low" <?php echo ($sort_by === 'price_low') ? 'selected' : ''; ?>>Price (Low to High)</option>
                            <option value="price_high" <?php echo ($sort_by === 'price_high') ? 'selected' : ''; ?>>Price (High to Low)</option>
                            <option value="stock_low" <?php echo ($sort_by === 'stock_low') ? 'selected' : ''; ?>>Stock (Low to High)</option>
                            <option value="stock_high" <?php echo ($sort_by === 'stock_high') ? 'selected' : ''; ?>>Stock (High to Low)</option>
                            <option value="newest" <?php echo ($sort_by === 'newest') ? 'selected' : ''; ?>>Newest First</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <button type="submit" class="btn-filter">
                            <i class="fas fa-search me-2"></i>Filter
                        </button>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Products Grid -->
        <?php if (empty($products)): ?>
            <div class="no-products">
                <i class="fas fa-box-open"></i>
                <h4>No products found</h4>
                <p>Try adjusting your search criteria or add some products to get started.</p>
                <a href="add_product.php" class="btn btn-primary">Add Your First Product</a>
            </div>
        <?php else: ?>
            <div class="products-grid">
                <!-- Add Product Card -->
                <a href="add_product.php" class="add-product-card">
                    <div class="add-icon">
                        <i class="fas fa-plus"></i>
                    </div>
                    <div class="add-title">Add New Product</div>
                    <div class="add-subtitle">Click here to add a new product to your inventory</div>
                </a>

                <!-- Product Cards -->
                <?php foreach ($products as $product): ?>
                    <div class="product-card-admin">
                        <?php
                        // Determine stock status
                        $stock_status = '';
                        $badge_class = '';
                        if ($product['stock'] == 0) {
                            $stock_status = 'Out of Stock';
                            $badge_class = 'badge-out-stock';
                        } elseif ($product['stock'] <= 10) {
                            $stock_status = 'Low Stock';
                            $badge_class = 'badge-low-stock';
                        } else {
                            $stock_status = 'In Stock';
                            $badge_class = 'badge-in-stock';
                        }
                        ?>
                        
                        <div class="product-badge-admin <?php echo $badge_class; ?>">
                            <?php echo $stock_status; ?>
                        </div>
                        
                        <div class="product-image-admin">
                            <?php if (!empty($product['image']) && file_exists('../assets/images/products/' . $product['image'])): ?>
                                <img src="../assets/images/products/<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                            <?php else: ?>
                                <div class="image-placeholder">
                                    <i class="fas fa-image"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="product-content-admin">
                            <h5 class="product-name-admin"><?php echo htmlspecialchars($product['name']); ?></h5>
                            <div class="product-brand-admin"><?php echo htmlspecialchars($product['brand']); ?></div>
                            
                            <div class="product-meta-admin">
                                <div class="meta-item-admin">
                                    <span class="meta-label-admin">Price</span>
                                    <span class="meta-value-admin">Rs.<?php echo number_format($product['price'], 2); ?></span>
                                </div>
                                <div class="meta-item-admin">
                                    <span class="meta-label-admin">Stock</span>
                                    <span class="meta-value-admin <?php echo $product['stock'] <= 10 ? ($product['stock'] == 0 ? 'stock-out' : 'stock-low') : ''; ?>">
                                        <?php echo $product['stock']; ?>
                                    </span>
                                </div>
                            </div>
                            
                            <?php if (!empty($product['description'])): ?>
                                <div class="product-description-admin">
                                    <?php echo htmlspecialchars($product['description']); ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="product-actions-admin">
                                <a href="edit_product.php?id=<?php echo $product['productID']; ?>" class="btn-admin btn-edit">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                <a href="view_product.php?id=<?php echo $product['productID']; ?>" class="btn-admin btn-view">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                <button type="button" class="btn-admin btn-delete" onclick="deleteProduct(<?php echo $product['productID']; ?>)">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php include_once '../includes/footer.php'; ?>

<script>
// Auto-hide alerts after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert-modern');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            alert.style.opacity = '0';
            setTimeout(function() {
                alert.remove();
            }, 500);
        }, 5000);
    });
});

// Delete product function - FIXED VERSION
function deleteProduct(productId) {
    if (confirm('Are you sure you want to delete this product?\n\nThis action will:\n• Remove the product from the database\n• Delete the product image\n• Remove it from customer carts\n\nThis cannot be undone!')) {
        
        // Show loading state
        const button = event.target;
        const originalText = button.innerHTML;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';
        button.disabled = true;
        
        // Create and submit form
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = window.location.href;
        form.style.display = 'none';
        
        // Add delete flag
        const deleteInput = document.createElement('input');
        deleteInput.type = 'hidden';
        deleteInput.name = 'delete_product';
        deleteInput.value = '1';
        
        // Add product ID
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'product_id';
        idInput.value = productId;
        
        form.appendChild(deleteInput);
        form.appendChild(idInput);
        document.body.appendChild(form);
        
        // Submit form
        form.submit();
    }
}

// Enhanced search functionality
function setupEnhancedFeatures() {
    // Auto-submit search after typing stops
    const searchInput = document.querySelector('input[name="search"]');
    if (searchInput) {
        let searchTimeout;
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            // Uncomment the next 3 lines if you want auto-search
            // searchTimeout = setTimeout(() => {
            //     this.form.submit();
            // }, 1000);
        });
    }
}

// Initialize enhanced features
document.addEventListener('DOMContentLoaded', function() {
    setupEnhancedFeatures();
});
</script>

<?php
// Close database connection
$conn->close();
?>