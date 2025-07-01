<?php
// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require_once 'config/db_connect.php';

// Initialize variables
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category = isset($_GET['category']) ? $_GET['category'] : '';
$min_price = isset($_GET['min_price']) ? (float)$_GET['min_price'] : 0;
$max_price = isset($_GET['max_price']) ? (float)$_GET['max_price'] : 99999;
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'name';

// Build SQL query
$sql = "SELECT * FROM pet_food_and_accessories WHERE 1=1";
$params = [];
$types = "";

// Add search condition
if (!empty($search)) {
    $sql .= " AND (name LIKE ? OR brand LIKE ? OR category LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
    $types .= "sss";
}

// Add category filter
if (!empty($category)) {
    $sql .= " AND category = ?";
    $params[] = $category;
    $types .= "s";
}

// Add price range filter
$sql .= " AND price BETWEEN ? AND ?";
$params[] = $min_price;
$params[] = $max_price;
$types .= "dd";

// Add sorting
switch ($sort_by) {
    case 'price_low':
        $sql .= " ORDER BY price ASC";
        break;
    case 'price_high':
        $sql .= " ORDER BY price DESC";
        break;
    case 'rating':
        $sql .= " ORDER BY rating DESC";
        break;
    default:
        $sql .= " ORDER BY name ASC";
        break;
}

// Execute query
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$products = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get categories for filter dropdown
$categories_sql = "SELECT DISTINCT category FROM pet_food_and_accessories ORDER BY category";
$categories_result = $conn->query($categories_sql);
$categories = $categories_result->fetch_all(MYSQLI_ASSOC);

// Include header
include_once 'includes/header.php';
?>

<style>
/* Enhanced Product Card Styles */
.product-card-modern {
    background: white;
    border-radius: 20px;
    box-shadow: 0 8px 25px rgba(0,0,0,0.08);
    transition: all 0.3s ease;
    overflow: hidden;
    position: relative;
    height: 100%;
    display: flex;
    flex-direction: column;
}

.product-card-modern:hover {
    transform: translateY(-8px);
    box-shadow: 0 20px 40px rgba(0,0,0,0.15);
}

.product-image-container {
    position: relative;
    height: 250px;
    overflow: hidden;
    background: #f8f9fa;
}

.product-image {
    width: 100%;
    height: 100%;
    object-fit: contain;
    transition: transform 0.3s ease;
    padding: 20px;
}

.product-card-modern:hover .product-image {
    transform: scale(1.05);
}

.product-badge {
    position: absolute;
    top: 15px;
    right: 15px;
    background: #667eea;
    color: white;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    z-index: 2;
}

.product-content {
    padding: 25px;
    flex: 1;
    display: flex;
    flex-direction: column;
}

.product-brand {
    color: #9ca3af;
    font-size: 0.9rem;
    margin-bottom: 8px;
    font-weight: 500;
}

.product-title {
    font-size: 1.2rem;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 12px;
    line-height: 1.4;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.product-weight {
    font-size: 0.8rem;
    color: #6b7280;
    background: #f1f5f9;
    padding: 4px 8px;
    border-radius: 6px;
    display: inline-block;
    margin-bottom: 15px;
}

.product-price {
    font-size: 1.5rem;
    font-weight: 800;
    color: #667eea;
    margin-bottom: 20px;
}

.product-stock {
    font-size: 0.85rem;
    margin-bottom: 15px;
}

.stock-high { color: #10b981; }
.stock-medium { color: #f59e0b; }
.stock-low { color: #ef4444; }

/* Enhanced Button Styles */
.product-actions {
    display: flex;
    gap: 10px;
    margin-top: auto;
}

.btn-details {
    flex: 1;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    color: #495057;
    border: 2px solid #dee2e6;
    padding: 12px 20px;
    border-radius: 12px;
    font-weight: 600;
    text-decoration: none;
    text-align: center;
    transition: all 0.3s ease;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.btn-details:hover {
    background: linear-gradient(135deg, #e9ecef 0%, #dee2e6 100%);
    color: #343a40;
    border-color: #adb5bd;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    text-decoration: none;
}

.btn-add-to-cart {
    flex: 1;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    padding: 12px 20px;
    border-radius: 12px;
    font-weight: 600;
    transition: all 0.3s ease;
    font-size: 0.9rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.btn-add-to-cart:hover {
    background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
}

.btn-add-to-cart:disabled {
    background: #9ca3af;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

/* Filter Section Styles */
.filters-section {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 60px 0;
    color: white;
}

.filter-card {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    border-radius: 20px;
    padding: 40px;
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.filter-title {
    font-size: 2rem;
    font-weight: 700;
    margin-bottom: 30px;
    text-align: center;
}

.filter-group {
    margin-bottom: 25px;
    width: 100%;
}

.filter-label {
    display: block;
    font-weight: 600;
    margin-bottom: 10px;
    color: rgba(255, 255, 255, 0.9);
    font-size: 0.9rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.form-control-modern {
    background: rgba(255, 255, 255, 0.1);
    border: 2px solid rgba(255, 255, 255, 0.2);
    border-radius: 12px;
    padding: 12px 16px;
    color: white;
    font-size: 0.95rem;
    transition: all 0.3s ease;
    width: 100%;
    box-sizing: border-box;
}

.form-control-modern:focus {
    background: rgba(255, 255, 255, 0.2);
    border-color: rgba(255, 255, 255, 0.5);
    outline: none;
    box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.1);
    color: white;
}

.form-control-modern::placeholder {
    color: rgba(255, 255, 255, 0.7);
}

.form-control-modern option {
    background: #667eea;
    color: white;
    padding: 8px;
}

.filter-btn {
    background: rgba(255, 255, 255, 0.2);
    border: 2px solid rgba(255, 255, 255, 0.3);
    color: white;
    padding: 12px 24px;
    border-radius: 12px;
    font-weight: 600;
    transition: all 0.3s ease;
    width: 100%;
    min-height: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
    white-space: nowrap;
}

.filter-btn:hover {
    background: rgba(255, 255, 255, 0.3);
    border-color: rgba(255, 255, 255, 0.5);
    color: white;
    transform: translateY(-2px);
}

/* Responsive adjustments for filters */
@media (max-width: 1199px) {
    .filter-card {
        padding: 30px;
    }
    
    .filter-title {
        font-size: 1.7rem;
    }
}

@media (max-width: 991px) {
    .filter-card {
        padding: 25px;
    }
    
    .filter-title {
        font-size: 1.5rem;
        margin-bottom: 25px;
    }
    
    .filter-group {
        margin-bottom: 20px;
    }
}

@media (max-width: 767px) {
    .filters-section {
        padding: 40px 0;
    }
    
    .filter-card {
        padding: 20px;
        margin: 0 10px;
    }
    
    .filter-title {
        font-size: 1.3rem;
        margin-bottom: 20px;
    }
    
    .form-control-modern {
        padding: 10px 14px;
        font-size: 0.9rem;
    }
    
    .filter-btn {
        padding: 10px 20px;
        font-size: 0.9rem;
    }
    
    .filter-label {
        font-size: 0.85rem;
        margin-bottom: 8px;
    }
}

@media (max-width: 575px) {
    .filter-card {
        padding: 15px;
        margin: 0 5px;
    }
    
    .filter-title {
        font-size: 1.2rem;
        line-height: 1.3;
    }
    
    .form-control-modern {
        padding: 8px 12px;
        font-size: 0.85rem;
    }
    
    .filter-btn {
        padding: 8px 16px;
        font-size: 0.85rem;
        min-height: 42px;
    }
}

/* Products Section */
.products-section {
    padding: 80px 0;
    background: #f8f9fa;
}

.products-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 40px;
    flex-wrap: wrap;
    gap: 20px;
}

.products-header h3 {
    font-size: 2.5rem;
    font-weight: 800;
    color: #1e293b;
    margin: 0;
}

.product-count {
    color: #64748b;
    font-weight: 500;
    font-size: 1.1rem;
}

.reset-link {
    color: #667eea;
    text-decoration: none;
    font-weight: 600;
    padding: 8px 16px;
    border-radius: 8px;
    transition: all 0.3s ease;
}

.reset-link:hover {
    background: #667eea;
    color: white;
    text-decoration: none;
}

.no-products-card {
    text-align: center;
    padding: 80px 40px;
    background: white;
    border-radius: 20px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}

.no-products-icon {
    font-size: 4rem;
    color: #9ca3af;
    margin-bottom: 24px;
}

.no-products-card h4 {
    color: #374151;
    margin-bottom: 16px;
    font-size: 1.5rem;
}

.no-products-card p {
    color: #6b7280;
    margin-bottom: 32px;
    font-size: 1.1rem;
}

/* Responsive Design */
@media (max-width: 1199px) {
    .products-header h3 {
        font-size: 2.2rem;
    }
}

@media (max-width: 991px) {
    .products-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
    
    .products-header h3 {
        font-size: 2rem;
    }
    
    .product-actions {
        flex-direction: column;
        gap: 8px;
    }
    
    .btn-details,
    .btn-add-to-cart {
        width: 100%;
        margin: 0;
    }
}

@media (max-width: 767px) {
    .products-header h3 {
        font-size: 1.8rem;
    }
    
    .product-content {
        padding: 20px;
    }
    
    .product-title {
        font-size: 1.1rem;
    }
    
    .product-price {
        font-size: 1.3rem;
    }
    
    .btn-details,
    .btn-add-to-cart {
        padding: 10px 16px;
        font-size: 0.85rem;
    }
    
    .no-products-card {
        padding: 60px 30px;
    }
    
    .no-products-card h4 {
        font-size: 1.3rem;
    }
}

@media (max-width: 575px) {
    .products-header h3 {
        font-size: 1.6rem;
    }
    
    .product-content {
        padding: 15px;
    }
    
    .product-title {
        font-size: 1rem;
        -webkit-line-clamp: 3;
    }
    
    .product-price {
        font-size: 1.2rem;
        margin-bottom: 15px;
    }
    
    .product-brand,
    .product-weight,
    .product-stock {
        font-size: 0.8rem;
    }
    
    .btn-details,
    .btn-add-to-cart {
        padding: 8px 14px;
        font-size: 0.8rem;
        gap: 6px;
    }
    
    .no-products-card {
        padding: 40px 20px;
    }
    
    .no-products-icon {
        font-size: 3rem;
    }
}
</style>

<!-- Modern Filters Section -->
<section class="filters-section">
    <div class="container">
        <div class="filter-card">
            <h2 class="filter-title">🐾 Find Perfect Products for Your Pet</h2>
            
            <form method="GET" action="shop.php">
                <div class="row g-4">
                    <!-- Search Field -->
                    <div class="col-xl-3 col-lg-4 col-md-6 col-sm-12">
                        <div class="filter-group">
                            <label class="filter-label">Search Products</label>
                            <input type="text" class="form-control-modern" name="search" 
                                   value="<?php echo htmlspecialchars($search); ?>" 
                                   placeholder="Search by name, brand...">
                        </div>
                    </div>
                    
                    <!-- Category Filter -->
                    <div class="col-xl-2 col-lg-3 col-md-6 col-sm-12">
                        <div class="filter-group">
                            <label class="filter-label">Category</label>
                            <select class="form-control-modern" name="category">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat['category']); ?>" 
                                            <?php echo ($category === $cat['category']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['category']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Price Range -->
                    <div class="col-xl-2 col-lg-2 col-md-3 col-sm-6">
                        <div class="filter-group">
                            <label class="filter-label">Min Price (Rs.)</label>
                            <input type="number" class="form-control-modern" name="min_price" 
                                   value="<?php echo $min_price; ?>" min="0" step="0.01" placeholder="0">
                        </div>
                    </div>
                    
                    <div class="col-xl-2 col-lg-2 col-md-3 col-sm-6">
                        <div class="filter-group">
                            <label class="filter-label">Max Price (Rs.)</label>
                            <input type="number" class="form-control-modern" name="max_price" 
                                   value="<?php echo $max_price; ?>" min="0" step="0.01" placeholder="99999">
                        </div>
                    </div>
                    
                    <!-- Sort By -->
                    <div class="col-xl-2 col-lg-3 col-md-6 col-sm-8">
                        <div class="filter-group">
                            <label class="filter-label">Sort By</label>
                            <select class="form-control-modern" name="sort_by">
                                <option value="name" <?php echo ($sort_by === 'name') ? 'selected' : ''; ?>>Name A-Z</option>
                                <option value="price_low" <?php echo ($sort_by === 'price_low') ? 'selected' : ''; ?>>Price Low-High</option>
                                <option value="price_high" <?php echo ($sort_by === 'price_high') ? 'selected' : ''; ?>>Price High-Low</option>
                                <option value="rating" <?php echo ($sort_by === 'rating') ? 'selected' : ''; ?>>Top Rated</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Filter Button -->
                    <div class="col-xl-1 col-lg-2 col-md-6 col-sm-4">
                        <div class="filter-group">
                            <label class="filter-label d-none d-sm-block">&nbsp;</label>
                            <button type="submit" class="filter-btn">
                                <i class="fas fa-search me-2 d-none d-md-inline"></i>
                                <span class="d-md-none">Filter</span>
                                <span class="d-none d-md-inline"></span>
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</section>

<!-- Modern Products Section -->
<section class="products-section">
    <div class="container">
        <?php if (empty($products)): ?>
            <div class="no-products-card">
                <div class="no-products-icon">
                    <i class="fas fa-search"></i>
                </div>
                <h4>No Products Found</h4>
                <p>Try adjusting your search criteria or check back later for new products.</p>
                <a href="shop.php" class="btn btn-primary btn-lg">Reset Filters</a>
            </div>
        <?php else: ?>
            <div class="products-header">
                <h3>Products <span class="product-count">(<?php echo count($products); ?> items)</span></h3>
                <a href="shop.php" class="reset-link">Reset All Filters</a>
            </div>
            
            <div class="row g-4">
                <?php foreach ($products as $product): ?>
                    <div class="col-xl-3 col-lg-4 col-md-6">
                        <div class="product-card-modern">
                            <?php if ($product['stock'] <= 5 && $product['stock'] > 0): ?>
                                <div class="product-badge urgent">⚡ Low Stock</div>
                            <?php elseif ($product['stock'] == 0): ?>
                                <div class="product-badge" style="background: #ef4444;">❌ Out of Stock</div>
                            <?php elseif (rand(1, 3) === 1): ?>
                                <div class="product-badge hot">🔥 Popular</div>
                            <?php endif; ?>
                            
                            <div class="product-image-container">
                                <?php if (!empty($product['image']) && file_exists('assets/images/products/' . $product['image'])): ?>
                                    <img src="assets/images/products/<?php echo htmlspecialchars($product['image']); ?>" 
                                         alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                         class="product-image">
                                <?php else: ?>
                                    <div class="product-image" style="display: flex; align-items: center; justify-content: center; background: #f1f5f9; color: #9ca3af; font-size: 3rem;">
                                        🐾
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="product-content">
                                <div class="product-brand"><?php echo htmlspecialchars($product['brand']); ?></div>
                                <h5 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h5>
                                
                                <?php if (!empty($product['weight'])): ?>
                                    <div class="product-weight"><?php echo htmlspecialchars($product['weight']); ?></div>
                                <?php endif; ?>
                                
                                <div class="product-price">Rs. <?php echo number_format($product['price'], 2); ?></div>
                                
                                <div class="product-stock">
                                    <?php if ($product['stock'] > 10): ?>
                                        <span class="stock-high">✅ In Stock (<?php echo $product['stock']; ?>)</span>
                                    <?php elseif ($product['stock'] > 0): ?>
                                        <span class="stock-medium">⚠️ Limited Stock (<?php echo $product['stock']; ?>)</span>
                                    <?php else: ?>
                                        <span class="stock-low">❌ Out of Stock</span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="product-actions">
                                    <!-- DETAILS BUTTON - This is the fix! -->
                                    <a href="product_details.php?id=<?php echo $product['productID']; ?>" class="btn-details">
                                        <i class="fas fa-info-circle"></i>
                                        Details
                                    </a>
                                    
                                    <!-- ADD TO CART BUTTON -->
                                    <?php if ($product['stock'] > 0): ?>
                                        <button class="btn-add-to-cart" onclick="addToCart(<?php echo $product['productID']; ?>)">
                                            <i class="fas fa-shopping-cart"></i>
                                            Add to Cart
                                        </button>
                                    <?php else: ?>
                                        <button class="btn-add-to-cart" disabled>
                                            <i class="fas fa-times"></i>
                                            Out of Stock
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<script>
// Add to cart function with fallback
function addToCart(productId) {
    <?php if (isset($_SESSION['user_id']) || isset($_SESSION['userID'])): ?>
        // Show loading state
        const button = event.target;
        const originalText = button.innerHTML;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
        button.disabled = true;
        
        // First, try the main cart_process.php
        const formData = new FormData();
        formData.append('product_id', productId);
        formData.append('quantity', 1);
        formData.append('action', 'add');
        
        fetch('cart_process.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showMessage('Product added to cart successfully!', 'success');
                
                // Update cart count if element exists
                const cartCountElement = document.querySelector('.cart-count');
                if (cartCountElement && data.cart_count) {
                    cartCountElement.textContent = data.cart_count;
                }
            } else {
                showMessage(data.message || 'Failed to add product to cart', 'error');
            }
        })
        .catch(error => {
            console.error('Primary cart method failed, trying backup...', error);
            
            // Fallback to backup ajax method
            const backupFormData = new FormData();
            backupFormData.append('product_id', productId);
            backupFormData.append('quantity', 1);
            
            fetch('ajax/add_to_cart.php', {
                method: 'POST',
                body: backupFormData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showMessage('Product added to cart successfully!', 'success');
                    
                    // Update cart count if element exists
                    const cartCountElement = document.querySelector('.cart-count');
                    if (cartCountElement && data.cart_count) {
                        cartCountElement.textContent = data.cart_count;
                    }
                } else {
                    showMessage(data.message || 'Failed to add product to cart', 'error');
                }
            })
            .catch(backupError => {
                console.error('Both cart methods failed:', backupError);
                showMessage('Unable to add product to cart. Please try again later.', 'error');
            });
        })
        .finally(() => {
            // Restore button
            button.innerHTML = originalText;
            button.disabled = false;
        });
    <?php else: ?>
        showMessage('Please login to add items to cart', 'error');
        setTimeout(() => {
            window.location.href = 'login.php';
        }, 2000);
    <?php endif; ?>
}

// Show message function
function showMessage(message, type) {
    // Remove existing messages
    const existingMessages = document.querySelectorAll('.message-toast');
    existingMessages.forEach(msg => msg.remove());
    
    // Create message element
    const messageDiv = document.createElement('div');
    messageDiv.className = `message-toast alert alert-${type === 'success' ? 'success' : 'danger'}`;
    messageDiv.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        min-width: 300px;
        padding: 15px 20px;
        border-radius: 10px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        background: ${type === 'success' ? '#d4edda' : '#f8d7da'};
        color: ${type === 'success' ? '#155724' : '#721c24'};
        border: 1px solid ${type === 'success' ? '#c3e6cb' : '#f5c6cb'};
        animation: slideIn 0.3s ease;
    `;
    messageDiv.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'} me-2"></i>
        ${message}
    `;
    
    // Add to page
    document.body.appendChild(messageDiv);
    
    // Remove after 3 seconds
    setTimeout(() => {
        messageDiv.style.animation = 'slideOut 0.3s ease forwards';
        setTimeout(() => messageDiv.remove(), 300);
    }, 3000);
}

// Add animations
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    @keyframes slideOut {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
`;
document.head.appendChild(style);
</script>

<?php
// Include footer
include_once 'includes/footer.php';

// Close database connection
$conn->close();
?>