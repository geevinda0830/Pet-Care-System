<?php
// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if product ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: shop.php");
    exit();
}

// Include database connection
require_once 'config/db_connect.php';

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
    header("Location: shop.php");
    exit();
}

$product = $product_result->fetch_assoc();
$product_stmt->close();

// Get related products (same category, limit 4)
$related_products = [];
if (!empty($product['category'])) {
    $related_sql = "SELECT * FROM pet_food_and_accessories WHERE category = ? AND productID != ? LIMIT 4";
    $related_stmt = $conn->prepare($related_sql);
    if ($related_stmt) {
        $related_stmt->bind_param("si", $product['category'], $product_id);
        $related_stmt->execute();
        $related_result = $related_stmt->get_result();
        $related_products = $related_result->fetch_all(MYSQLI_ASSOC);
        $related_stmt->close();
    }
}

// Include header
include_once 'includes/header.php';
?>

<style>
.product-details-hero {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 60px 0 40px;
    margin-bottom: 0;
}

.product-details-container {
    padding: 40px 0;
    background: #f8f9fa;
}

.product-detail-card {
    background: white;
    border-radius: 20px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    overflow: hidden;
    margin-bottom: 30px;
}

.product-image-section {
    padding: 40px;
    background: #f8f9fa;
    text-align: center;
    border-right: 1px solid #e9ecef;
}

.product-image {
    max-width: 100%;
    max-height: 400px;
    object-fit: contain;
    border-radius: 15px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
}

.image-placeholder {
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

.product-info-section {
    padding: 40px;
}

.product-category {
    color: #667eea;
    font-weight: 600;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: 10px;
}

.product-title {
    font-size: 2.5rem;
    font-weight: 800;
    color: #2d3748;
    margin-bottom: 15px;
    line-height: 1.2;
}

.product-brand {
    color: #718096;
    font-size: 1.2rem;
    margin-bottom: 20px;
}

.product-price {
    font-size: 2.5rem;
    font-weight: 700;
    color: #667eea;
    margin-bottom: 25px;
}

.stock-badge {
    display: inline-block;
    padding: 10px 20px;
    border-radius: 25px;
    font-size: 1rem;
    font-weight: 600;
    margin-bottom: 25px;
}

.stock-in {
    background: #d4edda;
    color: #155724;
}

.stock-low {
    background: #fff3cd;
    color: #856404;
}

.stock-out {
    background: #f8d7da;
    color: #721c24;
}

.product-description {
    font-size: 1.1rem;
    line-height: 1.8;
    color: #4a5568;
    margin-bottom: 30px;
    padding: 25px;
    background: #f8f9fa;
    border-radius: 15px;
}

.product-specs {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 15px;
    padding: 25px;
    margin-bottom: 30px;
}

.spec-row {
    display: flex;
    justify-content: space-between;
    padding: 15px 0;
    border-bottom: 1px solid #f1f5f9;
}

.spec-row:last-child {
    border-bottom: none;
}

.spec-label {
    font-weight: 600;
    color: #2d3748;
    flex: 1;
}

.spec-value {
    color: #718096;
    flex: 1;
    text-align: right;
}

.add-to-cart-section {
    display: flex;
    align-items: center;
    gap: 20px;
    margin-bottom: 30px;
    flex-wrap: wrap;
}

.quantity-control {
    display: flex;
    align-items: center;
    border: 2px solid #e2e8f0;
    border-radius: 10px;
    overflow: hidden;
    background: white;
}

.qty-btn {
    background: #f7fafc;
    border: none;
    width: 45px;
    height: 45px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 1.2rem;
}

.qty-btn:hover {
    background: #667eea;
    color: white;
}

.qty-input {
    border: none;
    width: 70px;
    height: 45px;
    text-align: center;
    font-weight: 600;
    font-size: 1.1rem;
}

.btn-add-to-cart {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    border: none;
    padding: 15px 30px;
    border-radius: 10px;
    font-weight: 600;
    font-size: 1.1rem;
    transition: all 0.3s ease;
    cursor: pointer;
    min-width: 200px;
}

.btn-add-to-cart:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
    color: white;
}

.btn-add-to-cart:disabled {
    background: #cbd5e0;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

.back-link {
    display: inline-flex;
    align-items: center;
    color: #667eea;
    text-decoration: none;
    font-weight: 600;
    margin-bottom: 20px;
    transition: all 0.3s ease;
}

.back-link:hover {
    color: #5a67d8;
    transform: translateX(-5px);
}

.related-products {
    margin-top: 60px;
}

.section-heading {
    font-size: 2rem;
    font-weight: 800;
    text-align: center;
    margin-bottom: 40px;
    color: #2d3748;
}

.related-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 25px;
}

.related-item {
    background: white;
    border-radius: 15px;
    padding: 20px;
    text-align: center;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
    text-decoration: none;
    color: inherit;
}

.related-item:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.15);
    text-decoration: none;
    color: inherit;
}

.related-image {
    width: 100%;
    height: 150px;
    object-fit: contain;
    margin-bottom: 15px;
    border-radius: 10px;
}

.related-name {
    font-weight: 600;
    margin-bottom: 8px;
    color: #2d3748;
}

.related-price {
    color: #667eea;
    font-weight: 700;
    font-size: 1.1rem;
}

@media (max-width: 768px) {
    .product-title {
        font-size: 2rem;
    }
    
    .product-price {
        font-size: 2rem;
    }
    
    .add-to-cart-section {
        flex-direction: column;
        align-items: stretch;
    }
    
    .btn-add-to-cart {
        min-width: auto;
        width: 100%;
    }
    
    .product-image-section,
    .product-info-section {
        padding: 25px;
    }
}
</style>

<!-- Product Details Hero -->
<section class="product-details-hero">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <a href="shop.php" class="back-link">
                    <i class="fas fa-arrow-left me-2"></i> Back to Shop
                </a>
                <h1 class="mb-0">Product Details</h1>
            </div>
        </div>
    </div>
</section>

<!-- Product Details Content -->
<section class="product-details-container">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <div class="product-detail-card">
                    <div class="row">
                        <!-- Product Image -->
                        <div class="col-lg-6">
                            <div class="product-image-section">
                                <?php if (!empty($product['image']) && file_exists('assets/images/products/' . $product['image'])): ?>
                                    <img src="assets/images/products/<?php echo htmlspecialchars($product['image']); ?>" 
                                         alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                         class="product-image">
                                <?php else: ?>
                                    <div class="image-placeholder">
                                        <i class="fas fa-image"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Product Information -->
                        <div class="col-lg-6">
                            <div class="product-info-section">
                                <div class="product-category"><?php echo htmlspecialchars($product['category']); ?></div>
                                <h1 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h1>
                                <p class="product-brand">by <?php echo htmlspecialchars($product['brand']); ?></p>
                                
                                <div class="product-price">Rs.<?php echo number_format($product['price'], 2); ?></div>
                                
                                <!-- Stock Status -->
                                <div class="stock-badge <?php 
                                    $stock = intval($product['stock']);
                                    if ($stock == 0) echo 'stock-out';
                                    elseif ($stock <= 10) echo 'stock-low';
                                    else echo 'stock-in';
                                ?>">
                                    <?php 
                                    if ($stock == 0) echo 'Out of Stock';
                                    elseif ($stock <= 10) echo "Low Stock ({$stock} left)";
                                    else echo "In Stock ({$stock} available)";
                                    ?>
                                </div>
                                
                                <!-- Add to Cart Section -->
                                <?php if ($stock > 0): ?>
                                    <div class="add-to-cart-section">
                                        <div class="quantity-control">
                                            <button type="button" class="qty-btn" onclick="decreaseQty()">
                                                <i class="fas fa-minus"></i>
                                            </button>
                                            <input type="number" class="qty-input" id="quantity" value="1" min="1" max="<?php echo $stock; ?>">
                                            <button type="button" class="qty-btn" onclick="increaseQty()">
                                                <i class="fas fa-plus"></i>
                                            </button>
                                        </div>
                                        
                                        <button class="btn-add-to-cart" onclick="addToCart(<?php echo $product['productID']; ?>)">
                                            <i class="fas fa-shopping-cart me-2"></i>Add to Cart
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <button class="btn-add-to-cart" disabled>
                                        <i class="fas fa-times me-2"></i>Out of Stock
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Product Description -->
                <?php if (!empty($product['description'])): ?>
                    <div class="product-detail-card">
                        <div class="product-description">
                            <h4 style="margin-bottom: 20px; color: #2d3748;">
                                <i class="fas fa-info-circle me-2"></i>Product Description
                            </h4>
                            <p><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Product Specifications -->
                <div class="product-detail-card">
                    <div class="product-specs">
                        <h4 style="margin-bottom: 20px; color: #2d3748;">
                            <i class="fas fa-list me-2"></i>Product Specifications
                        </h4>
                        
                        <div class="spec-row">
                            <div class="spec-label">Product ID:</div>
                            <div class="spec-value">#<?php echo $product['productID']; ?></div>
                        </div>
                        
                        <div class="spec-row">
                            <div class="spec-label">Brand:</div>
                            <div class="spec-value"><?php echo htmlspecialchars($product['brand']); ?></div>
                        </div>
                        
                        <div class="spec-row">
                            <div class="spec-label">Category:</div>
                            <div class="spec-value"><?php echo htmlspecialchars($product['category']); ?></div>
                        </div>
                        
                        <div class="spec-row">
                            <div class="spec-label">Weight:</div>
                            <div class="spec-value"><?php echo htmlspecialchars($product['weight']); ?></div>
                        </div>
                        
                        <div class="spec-row">
                            <div class="spec-label">Stock Quantity:</div>
                            <div class="spec-value"><?php echo $product['stock']; ?> units</div>
                        </div>
                    </div>
                </div>
                
                <!-- Related Products -->
                <?php if (!empty($related_products)): ?>
                    <div class="related-products">
                        <h2 class="section-heading">Related Products</h2>
                        <div class="related-grid">
                            <?php foreach ($related_products as $related): ?>
                                <a href="product_details.php?id=<?php echo $related['productID']; ?>" class="related-item">
                                    <?php if (!empty($related['image']) && file_exists('assets/images/products/' . $related['image'])): ?>
                                        <img src="assets/images/products/<?php echo htmlspecialchars($related['image']); ?>" 
                                             alt="<?php echo htmlspecialchars($related['name']); ?>" 
                                             class="related-image">
                                    <?php else: ?>
                                        <div style="height: 150px; background: #f8f9fa; border-radius: 10px; display: flex; align-items: center; justify-content: center; margin-bottom: 15px; color: #cbd5e0; font-size: 2rem;">
                                            <i class="fas fa-image"></i>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <h5 class="related-name"><?php echo htmlspecialchars($related['name']); ?></h5>
                                    <div class="related-price">Rs.<?php echo number_format($related['price'], 2); ?></div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<script>
// Quantity controls
function decreaseQty() {
    const input = document.getElementById('quantity');
    const current = parseInt(input.value);
    if (current > 1) {
        input.value = current - 1;
    }
}

function increaseQty() {
    const input = document.getElementById('quantity');
    const max = parseInt(input.getAttribute('max'));
    const current = parseInt(input.value);
    if (current < max) {
        input.value = current + 1;
    }
}

// Add to cart function
function addToCart(productId) {
    <?php if (isset($_SESSION['user_id'])): ?>
        const quantity = document.getElementById('quantity').value;
        
        // Show loading state
        const button = document.querySelector('.btn-add-to-cart');
        const originalText = button.innerHTML;
        button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Adding...';
        button.disabled = true;
        
        // Create form data
        const formData = new FormData();
        formData.append('action', 'add');
        formData.append('product_id', productId);
        formData.append('quantity', quantity);
        
        // Send request
        fetch('cart_process.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showMessage('Product added to cart successfully!', 'success');
            } else {
                showMessage(data.message || 'Failed to add product to cart', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showMessage('An error occurred while adding product to cart', 'error');
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
    `;
    messageDiv.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'} me-2"></i>
        ${message}
    `;
    
    // Add to page
    document.body.appendChild(messageDiv);
    
    // Remove after 3 seconds
    setTimeout(() => {
        messageDiv.remove();
    }, 3000);
}

// Input validation
document.getElementById('quantity').addEventListener('change', function() {
    const value = parseInt(this.value);
    const max = parseInt(this.getAttribute('max'));
    const min = parseInt(this.getAttribute('min'));
    
    if (value > max) this.value = max;
    if (value < min) this.value = min;
});
</script>

<?php
// Include footer
include_once 'includes/footer.php';

// Close database connection
$conn->close();
?>