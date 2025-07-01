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

// Handle product deletion
if (isset($_POST['delete_product']) && isset($_POST['product_id'])) {
    $product_id = $_POST['product_id'];
    
    // First check if product is in any orders or cart items
    $check_sql = "SELECT 
                    (SELECT COUNT(*) FROM cart_items WHERE productID = ?) as cart_count,
                    (SELECT COUNT(*) FROM order_items WHERE productID = ?) as order_count";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ii", $product_id, $product_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    $check_data = $check_result->fetch_assoc();
    
    if ($check_data['cart_count'] > 0 || $check_data['order_count'] > 0) {
        $_SESSION['error_message'] = "Cannot delete product. It's currently in customer carts or orders.";
    } else {
        // Delete the product
        $delete_sql = "DELETE FROM pet_food_and_accessories WHERE productID = ?";
        $delete_stmt = $conn->prepare($delete_sql);
        $delete_stmt->bind_param("i", $product_id);
        
        if ($delete_stmt->execute()) {
            $_SESSION['success_message'] = "Product deleted successfully.";
        } else {
            $_SESSION['error_message'] = "Error deleting product. Please try again.";
        }
        $delete_stmt->close();
    }
    $check_stmt->close();
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

if ($stock_filter === 'low_stock') {
    $sql .= " AND stock <= 10";
} elseif ($stock_filter === 'out_of_stock') {
    $sql .= " AND stock = 0";
} elseif ($stock_filter === 'in_stock') {
    $sql .= " AND stock > 10";
}

// Add sorting
switch ($sort_by) {
    case 'price_low':
        $sql .= " ORDER BY price ASC";
        break;
    case 'price_high':
        $sql .= " ORDER BY price DESC";
        break;
    case 'stock':
        $sql .= " ORDER BY stock ASC";
        break;
    case 'date_added':
        $sql .= " ORDER BY created_at DESC";
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

$products = [];
while ($row = $result->fetch_assoc()) {
    $products[] = $row;
}

$stmt->close();

// Get statistics
$stats_sql = "SELECT 
                COUNT(*) as total_products,
                COUNT(CASE WHEN stock <= 10 THEN 1 END) as low_stock,
                COUNT(CASE WHEN stock = 0 THEN 1 END) as out_of_stock,
                AVG(price) as avg_price,
                SUM(stock) as total_stock
              FROM pet_food_and_accessories";
$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();

// Include header
include_once '../includes/header.php';
?>

<style>
.products-management-section {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    position: relative;
    overflow: hidden;
    padding: 80px 0 60px;
}

.products-particles {
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

.products-header-content {
    position: relative;
    z-index: 2;
}

.products-badge {
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

.products-title {
    font-size: 3rem;
    font-weight: 800;
    line-height: 1.2;
    margin-bottom: 16px;
}

.products-subtitle {
    font-size: 1.2rem;
    opacity: 0.9;
    line-height: 1.6;
}

.products-actions {
    position: relative;
    z-index: 2;
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
}

.btn-glass:hover {
    background: rgba(255, 255, 255, 0.2);
    color: white;
    transform: translateY(-2px);
}

.products-content {
    padding: 80px 0;
    background: #f8f9ff;
}

.stats-cards-row {
    margin-bottom: 40px;
}

.stat-card-mini {
    background: white;
    border-radius: 16px;
    padding: 24px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(0, 0, 0, 0.05);
    transition: transform 0.3s ease;
    text-align: center;
}

.stat-card-mini:hover {
    transform: translateY(-4px);
}

.stat-icon-mini {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 16px;
    font-size: 1.5rem;
}

.stat-icon-mini.products { background: linear-gradient(135deg, #667eea, #764ba2); color: white; }
.stat-icon-mini.low-stock { background: linear-gradient(135deg, #f59e0b, #d97706); color: white; }
.stat-icon-mini.out-stock { background: linear-gradient(135deg, #ef4444, #dc2626); color: white; }
.stat-icon-mini.avg-price { background: linear-gradient(135deg, #10b981, #059669); color: white; }

.stat-number-mini {
    font-size: 2rem;
    font-weight: 800;
    color: #1e293b;
    display: block;
    margin-bottom: 4px;
}

.stat-label-mini {
    color: #64748b;
    font-weight: 600;
    font-size: 0.9rem;
}

.filter-card-modern {
    background: white;
    border-radius: 20px;
    padding: 32px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(0, 0, 0, 0.05);
    margin-bottom: 40px;
}

.filter-label {
    color: #374151;
    font-weight: 600;
    margin-bottom: 8px;
    display: block;
}

.search-group {
    position: relative;
}

.search-icon {
    position: absolute;
    left: 16px;
    top: 50%;
    transform: translateY(-50%);
    color: #9ca3af;
}

.search-input {
    padding-left: 48px;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    padding-top: 12px;
    padding-bottom: 12px;
    transition: all 0.3s ease;
}

.search-input:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.modern-select {
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    padding: 12px 16px;
    transition: all 0.3s ease;
}

.modern-select:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.filter-btn {
    height: 48px;
    width: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.products-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 24px;
    margin-bottom: 40px;
}

.product-card-admin {
    background: white;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(0, 0, 0, 0.05);
    transition: all 0.3s ease;
    position: relative;
}

.product-card-admin:hover {
    transform: translateY(-4px);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.12);
}

.product-image-admin {
    height: 200px;
    overflow: hidden;
    position: relative;
}

.product-image-admin img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.product-card-admin:hover .product-image-admin img {
    transform: scale(1.05);
}

.product-badge-admin {
    position: absolute;
    top: 12px;
    right: 12px;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    color: white;
}

.badge-low-stock { background: #f59e0b; }
.badge-out-stock { background: #ef4444; }
.badge-in-stock { background: #10b981; }

.product-content-admin {
    padding: 24px;
}

.product-name-admin {
    font-size: 1.2rem;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 8px;
    line-height: 1.3;
}

.product-brand-admin {
    color: #64748b;
    font-size: 0.9rem;
    margin-bottom: 12px;
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
}

.btn-edit {
    background: #3b82f6;
    color: white;
    border: none;
}

.btn-edit:hover {
    background: #2563eb;
    color: white;
}

.btn-delete {
    background: #ef4444;
    color: white;
    border: none;
}

.btn-delete:hover {
    background: #dc2626;
    color: white;
}

.btn-view {
    background: #10b981;
    color: white;
    border: none;
}

.btn-view:hover {
    background: #059669;
    color: white;
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

.no-products-found {
    text-align: center;
    padding: 80px 20px;
    color: #6b7280;
}

.no-products-found i {
    font-size: 4rem;
    margin-bottom: 24px;
    color: #d1d5db;
}

.no-products-found h4 {
    color: #374151;
    margin-bottom: 16px;
}

.alert-modern {
    border: none;
    border-radius: 16px;
    padding: 20px 24px;
    margin-bottom: 32px;
    border-left: 4px solid;
}

.alert-success {
    background: #ecfdf5;
    border-left-color: #10b981;
    color: #065f46;
}

.alert-danger {
    background: #fef2f2;
    border-left-color: #ef4444;
    color: #991b1b;
}

@media (max-width: 768px) {
    .products-title {
        font-size: 2rem;
    }
    
    .products-grid {
        grid-template-columns: 1fr;
    }
    
    .filter-card-modern {
        padding: 24px;
    }
    
    .product-actions-admin {
        flex-direction: column;
    }
    
    .stats-cards-row .col-md-3 {
        margin-bottom: 16px;
    }
}
</style>

<!-- Modern Products Management Header -->
<section class="products-management-section">
    <div class="products-particles"></div>
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <div class="products-header-content">
                    <div class="products-badge">
                        <i class="fas fa-box me-2"></i>
                        Product Management System
                    </div>
                    <h1 class="products-title">Manage Products</h1>
                    <p class="products-subtitle">Efficiently manage your pet store inventory with advanced controls and insights.</p>
                </div>
            </div>
            <div class="col-lg-4 text-lg-end">
                <div class="products-actions">
                    <a href="add_product.php" class="btn btn-glass">
                        <i class="fas fa-plus me-2"></i>Add Product
                    </a>
                    <a href="dashboard.php" class="btn btn-glass">
                        <i class="fas fa-arrow-left me-2"></i>Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Products Management Content -->
<section class="products-content">
    <div class="container">
        <!-- Success/Error Messages -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-modern">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo htmlspecialchars($_SESSION['success_message']); ?>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-modern">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?php echo htmlspecialchars($_SESSION['error_message']); ?>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="row stats-cards-row">
            <div class="col-md-3">
                <div class="stat-card-mini">
                    <div class="stat-icon-mini products">
                        <i class="fas fa-box"></i>
                    </div>
                    <span class="stat-number-mini"><?php echo $stats['total_products']; ?></span>
                    <div class="stat-label-mini">Total Products</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card-mini">
                    <div class="stat-icon-mini low-stock">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <span class="stat-number-mini"><?php echo $stats['low_stock']; ?></span>
                    <div class="stat-label-mini">Low Stock</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card-mini">
                    <div class="stat-icon-mini out-stock">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <span class="stat-number-mini"><?php echo $stats['out_of_stock']; ?></span>
                    <div class="stat-label-mini">Out of Stock</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card-mini">
                    <div class="stat-icon-mini avg-price">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <span class="stat-number-mini">Rs. <?php echo number_format($stats['avg_price'], 0); ?></span>
                    <div class="stat-label-mini">Avg. Price</div>
                </div>
            </div>
        </div>

        <!-- Search and Filter -->
        <div class="filter-card-modern">
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="get" class="row g-3">
                <div class="col-md-3">
                    <label class="filter-label">Search Products</label>
                    <div class="search-group">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" class="form-control search-input" name="search" placeholder="Search products..." value="<?php echo htmlspecialchars($search_query); ?>">
                    </div>
                </div>
                
                <div class="col-md-2">
                    <label class="filter-label">Category</label>
                    <select class="form-select modern-select" name="category">
                        <option value="">All Categories</option>
                        <option value="Dog Food" <?php echo ($category_filter === 'Dog Food') ? 'selected' : ''; ?>>Dog Food</option>
                        <option value="Cat Food" <?php echo ($category_filter === 'Cat Food') ? 'selected' : ''; ?>>Cat Food</option>
                        <option value="Bird Food" <?php echo ($category_filter === 'Bird Food') ? 'selected' : ''; ?>>Bird Food</option>
                        <option value="Fish Food" <?php echo ($category_filter === 'Fish Food') ? 'selected' : ''; ?>>Fish Food</option>
                        <option value="Toys" <?php echo ($category_filter === 'Toys') ? 'selected' : ''; ?>>Toys</option>
                        <option value="Accessories" <?php echo ($category_filter === 'Accessories') ? 'selected' : ''; ?>>Accessories</option>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="filter-label">Stock Status</label>
                    <select class="form-select modern-select" name="stock_status">
                        <option value="">All Stock</option>
                        <option value="in_stock" <?php echo ($stock_filter === 'in_stock') ? 'selected' : ''; ?>>In Stock</option>
                        <option value="low_stock" <?php echo ($stock_filter === 'low_stock') ? 'selected' : ''; ?>>Low Stock</option>
                        <option value="out_of_stock" <?php echo ($stock_filter === 'out_of_stock') ? 'selected' : ''; ?>>Out of Stock</option>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="filter-label">Sort By</label>
                    <select class="form-select modern-select" name="sort_by">
                        <option value="name" <?php echo ($sort_by === 'name') ? 'selected' : ''; ?>>Name</option>
                        <option value="price_low" <?php echo ($sort_by === 'price_low') ? 'selected' : ''; ?>>Price: Low to High</option>
                        <option value="price_high" <?php echo ($sort_by === 'price_high') ? 'selected' : ''; ?>>Price: High to Low</option>
                        <option value="stock" <?php echo ($sort_by === 'stock') ? 'selected' : ''; ?>>Stock Level</option>
                        <option value="date_added" <?php echo ($sort_by === 'date_added') ? 'selected' : ''; ?>>Recently Added</option>
                    </select>
                </div>
                
                <div class="col-md-1">
                    <label class="filter-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary filter-btn">
                        <i class="fas fa-filter"></i>
                    </button>
                </div>
                
                <div class="col-md-2">
                    <label class="filter-label">&nbsp;</label>
                    <a href="manage_products.php" class="btn btn-outline-secondary w-100">
                        <i class="fas fa-undo me-2"></i>Reset
                    </a>
                </div>
            </form>
        </div>

        <!-- Products Grid -->
        <?php if (empty($products)): ?>
            <div class="no-products-found">
                <i class="fas fa-search"></i>
                <h4>No Products Found</h4>
                <p>No products match your current filters. Try adjusting your search criteria.</p>
                <a href="manage_products.php" class="btn btn-primary">Reset Filters</a>
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
                            <?php if (!empty($product['image'])): ?>
                                <img src="../assets/images/products/<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                            <?php else: ?>
                                <img src="../assets/images/placeholder-product.jpg" alt="No Image">
                            <?php endif; ?>
                        </div>
                        
                        <div class="product-content-admin">
                            <h5 class="product-name-admin"><?php echo htmlspecialchars($product['name']); ?></h5>
                            <div class="product-brand-admin"><?php echo htmlspecialchars($product['brand']); ?></div>
                            
                            <div class="product-meta-admin">
                                <div class="meta-item-admin">
                                    <span class="meta-label-admin">Price</span>
                                    <span class="meta-value-admin">Rs. <?php echo number_format($product['price'], 2); ?></span>
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
                                <a href="../product_details.php?id=<?php echo $product['productID']; ?>" class="btn-admin btn-view" target="_blank">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" style="display: inline; flex: 1;">
                                    <input type="hidden" name="product_id" value="<?php echo $product['productID']; ?>">
                                    <button type="submit" name="delete_product" class="btn-admin btn-delete w-100" onclick="return confirm('Are you sure you want to delete this product? This action cannot be undone.')">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </form>
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
</script>