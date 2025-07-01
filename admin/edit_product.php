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

// Check if product ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "Invalid product ID.";
    header("Location: manage_products.php");
    exit();
}

// Include database connection
require_once '../config/db_connect.php';

$product_id = $_GET['id'];
$errors = [];
$success_message = '';

// Fetch product details
$product_sql = "SELECT * FROM pet_food_and_accessories WHERE productID = ?";
$product_stmt = $conn->prepare($product_sql);
$product_stmt->bind_param("i", $product_id);
$product_stmt->execute();
$product_result = $product_stmt->get_result();

if ($product_result->num_rows === 0) {
    $_SESSION['error_message'] = "Product not found.";
    header("Location: manage_products.php");
    exit();
}

$product = $product_result->fetch_assoc();
$product_stmt->close();

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_product'])) {
    // Sanitize and validate inputs
    $name = trim($_POST['name']);
    $brand = trim($_POST['brand']);
    $category = trim($_POST['category']);
    $price = trim($_POST['price']);
    $weight = trim($_POST['weight']);
    $stock = trim($_POST['stock']);
    $description = trim($_POST['description']);
    
    // Validation
    if (empty($name)) {
        $errors[] = "Product name is required.";
    }
    
    if (empty($brand)) {
        $errors[] = "Brand is required.";
    }
    
    if (empty($category)) {
        $errors[] = "Category is required.";
    }
    
    if (empty($price) || !is_numeric($price) || $price <= 0) {
        $errors[] = "Valid price is required.";
    }
    
    if (empty($weight)) {
        $errors[] = "Weight is required.";
    }
    
    if (empty($stock) || !is_numeric($stock) || $stock < 0) {
        $errors[] = "Valid stock quantity is required.";
    }
    
    if (empty($description)) {
        $errors[] = "Product description is required.";
    }
    
    // Handle image upload
    $image_name = $product['image']; // Keep existing image by default
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        if (!in_array($_FILES['image']['type'], $allowed_types)) {
            $errors[] = "Only JPEG, PNG, and GIF images are allowed.";
        }
        
        if ($_FILES['image']['size'] > $max_size) {
            $errors[] = "Image file size must be less than 5MB.";
        }
        
        if (empty($errors)) {
            // Create uploads directory if it doesn't exist
            $upload_dir = '../assets/images/products/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Generate unique filename
            $file_extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $new_image_name = 'product_' . time() . '_' . uniqid() . '.' . $file_extension;
            $upload_path = $upload_dir . $new_image_name;
            
            if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                // Delete old image if it exists
                if ($product['image'] && file_exists($upload_dir . $product['image'])) {
                    unlink($upload_dir . $product['image']);
                }
                $image_name = $new_image_name;
            } else {
                $errors[] = "Failed to upload image. Please try again.";
            }
        }
    }
    
    // Handle remove image
    if (isset($_POST['remove_image']) && $_POST['remove_image'] == '1') {
        // Delete existing image
        if ($product['image'] && file_exists('../assets/images/products/' . $product['image'])) {
            unlink('../assets/images/products/' . $product['image']);
        }
        $image_name = '';
    }
    
    // If no errors, update product in database
    if (empty($errors)) {
        $update_sql = "UPDATE pet_food_and_accessories 
                       SET name = ?, brand = ?, category = ?, price = ?, weight = ?, stock = ?, description = ?, image = ?
                       WHERE productID = ?";
        
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("sssdsissi", $name, $brand, $category, $price, $weight, $stock, $description, $image_name, $product_id);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Product updated successfully!";
            header("Location: manage_products.php");
            exit();
        } else {
            $errors[] = "Error updating product. Please try again.";
        }
        
        $stmt->close();
    }
    
    // If there were errors, reload the product data with submitted values
    if (!empty($errors)) {
        $product['name'] = $name;
        $product['brand'] = $brand;
        $product['category'] = $category;
        $product['price'] = $price;
        $product['weight'] = $weight;
        $product['stock'] = $stock;
        $product['description'] = $description;
    }
}

// Include header
include_once '../includes/header.php';
?>

<style>
.edit-product-section {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    position: relative;
    overflow: hidden;
    padding: 80px 0 60px;
}

.edit-product-particles {
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

.edit-product-header-content {
    position: relative;
    z-index: 2;
}

.edit-product-badge {
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

.edit-product-title {
    font-size: 3rem;
    font-weight: 800;
    line-height: 1.2;
    margin-bottom: 16px;
}

.edit-product-subtitle {
    font-size: 1.2rem;
    opacity: 0.9;
    line-height: 1.6;
}

.edit-product-actions {
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
    text-decoration: none;
}

.btn-glass:hover {
    background: rgba(255, 255, 255, 0.2);
    color: white;
    transform: translateY(-2px);
}

.edit-product-content {
    padding: 80px 0;
    background: #f8f9fa;
}

.product-form-card {
    background: white;
    border-radius: 20px;
    box-shadow: 0 15px 35px rgba(0,0,0,0.1);
    overflow: hidden;
    margin-bottom: 30px;
}

.form-header {
    background: linear-gradient(135deg, #f8f9ff, #e8f4f8);
    padding: 32px;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.form-header h4 {
    color: #1e293b;
    font-weight: 700;
    margin: 0;
}

.product-id-badge {
    background: #667eea;
    color: white;
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 0.9rem;
    font-weight: 600;
}

.form-body {
    padding: 40px;
}

.form-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 24px;
    margin-bottom: 24px;
}

.form-group {
    margin-bottom: 24px;
}

.form-label {
    display: block;
    font-weight: 600;
    color: #374151;
    margin-bottom: 8px;
    font-size: 0.95rem;
}

.required {
    color: #ef4444;
}

.form-control-modern {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    font-size: 1rem;
    transition: all 0.3s ease;
    background: #ffffff;
}

.form-control-modern:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.form-select-modern {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    font-size: 1rem;
    transition: all 0.3s ease;
    background: #ffffff;
    cursor: pointer;
}

.form-select-modern:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.form-textarea-modern {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    font-size: 1rem;
    transition: all 0.3s ease;
    background: #ffffff;
    resize: vertical;
    min-height: 120px;
}

.form-textarea-modern:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.current-image-section {
    margin-bottom: 20px;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 12px;
}

.current-image {
    max-width: 200px;
    max-height: 200px;
    object-fit: contain;
    border-radius: 8px;
    margin-bottom: 15px;
    display: block;
}

.image-actions {
    display: flex;
    gap: 10px;
}

.btn-change-image, .btn-remove-image {
    padding: 8px 16px;
    border-radius: 8px;
    font-size: 0.9rem;
    font-weight: 600;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-change-image {
    background: #3b82f6;
    color: white;
}

.btn-change-image:hover {
    background: #2563eb;
}

.btn-remove-image {
    background: #ef4444;
    color: white;
}

.btn-remove-image:hover {
    background: #dc2626;
}

.image-upload-area {
    border: 2px dashed #d1d5db;
    border-radius: 12px;
    padding: 40px 20px;
    text-align: center;
    background: #fafafa;
    transition: all 0.3s ease;
}

.image-upload-area:hover {
    border-color: #667eea;
    background: #f0f4ff;
}

.form-actions {
    display: flex;
    gap: 16px;
    justify-content: flex-end;
    margin-top: 40px;
    padding-top: 32px;
    border-top: 1px solid #e5e7eb;
}

.btn-primary-modern {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    border: none;
    padding: 14px 32px;
    border-radius: 12px;
    font-weight: 600;
    font-size: 1rem;
    transition: all 0.3s ease;
    min-width: 160px;
}

.btn-primary-modern:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
    color: white;
}

.btn-secondary-modern {
    background: #f8f9fa;
    color: #6c757d;
    border: 2px solid #e5e7eb;
    padding: 12px 32px;
    border-radius: 12px;
    font-weight: 600;
    font-size: 1rem;
    transition: all 0.3s ease;
    text-decoration: none;
    min-width: 160px;
    text-align: center;
}

.btn-secondary-modern:hover {
    background: #e9ecef;
    color: #495057;
    border-color: #d1d5db;
}

.alert-modern {
    border: none;
    border-radius: 16px;
    padding: 20px 24px;
    margin-bottom: 32px;
    border-left: 4px solid;
}

.alert-danger {
    background: #fef2f2;
    border-left-color: #ef4444;
    color: #991b1b;
}

.error-list {
    margin: 0;
    padding-left: 20px;
}

.error-list li {
    margin-bottom: 8px;
}

.character-count {
    text-align: right;
    font-size: 0.8rem;
    color: #6b7280;
    margin-top: 4px;
}

.form-hint {
    font-size: 0.85rem;
    color: #6b7280;
    margin-top: 4px;
    line-height: 1.4;
}

@media (max-width: 768px) {
    .edit-product-title {
        font-size: 2rem;
    }
    
    .form-body {
        padding: 24px;
    }
    
    .form-header {
        padding: 24px;
        flex-direction: column;
        gap: 16px;
        text-align: center;
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .image-upload-area {
        padding: 24px 16px;
    }
}
</style>

<!-- Modern Edit Product Header -->
<section class="edit-product-section">
    <div class="edit-product-particles"></div>
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <div class="edit-product-header-content">
                    <div class="edit-product-badge">
                        <i class="fas fa-edit me-2"></i>
                        Edit Product
                    </div>
                    <h1 class="edit-product-title">Edit Product</h1>
                    <p class="edit-product-subtitle">Update product information, pricing, and inventory details.</p>
                </div>
            </div>
            <div class="col-lg-4 text-lg-end">
                <div class="edit-product-actions">
                    <a href="manage_products.php" class="btn-glass">
                        <i class="fas fa-arrow-left me-2"></i>Back to Products
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Edit Product Content -->
<section class="edit-product-content">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <!-- Error Messages -->
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger alert-modern">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Please fix the following errors:</strong>
                        <ul class="error-list">
                            <?php foreach($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <!-- Product Form -->
                <div class="product-form-card">
                    <div class="form-header">
                        <h4><i class="fas fa-edit me-2"></i>Edit Product Information</h4>
                        <div class="product-id-badge">ID: <?php echo $product_id; ?></div>
                    </div>
                    
                    <div class="form-body">
                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . '?id=' . $product_id; ?>" method="post" enctype="multipart/form-data" id="editProductForm">
                            <!-- Basic Information -->
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Product Name <span class="required">*</span></label>
                                    <input type="text" class="form-control-modern" name="name" id="productName" 
                                           value="<?php echo htmlspecialchars($product['name']); ?>" 
                                           placeholder="Enter product name" maxlength="100" required>
                                    <div class="form-hint">Maximum 100 characters</div>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Brand <span class="required">*</span></label>
                                    <input type="text" class="form-control-modern" name="brand" id="productBrand"
                                           value="<?php echo htmlspecialchars($product['brand']); ?>" 
                                           placeholder="Enter brand name" maxlength="50" required>
                                    <div class="form-hint">Product manufacturer or brand name</div>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Category <span class="required">*</span></label>
                                    <select class="form-select-modern" name="category" required>
                                        <option value="">Select Category</option>
                                        <option value="Dog Food" <?php echo ($product['category'] === 'Dog Food') ? 'selected' : ''; ?>>Dog Food</option>
                                        <option value="Cat Food" <?php echo ($product['category'] === 'Cat Food') ? 'selected' : ''; ?>>Cat Food</option>
                                        <option value="Bird Food" <?php echo ($product['category'] === 'Bird Food') ? 'selected' : ''; ?>>Bird Food</option>
                                        <option value="Fish Food" <?php echo ($product['category'] === 'Fish Food') ? 'selected' : ''; ?>>Fish Food</option>
                                        <option value="Toys" <?php echo ($product['category'] === 'Toys') ? 'selected' : ''; ?>>Toys</option>
                                        <option value="Accessories" <?php echo ($product['category'] === 'Accessories') ? 'selected' : ''; ?>>Accessories</option>
                                        <option value="Medicine" <?php echo ($product['category'] === 'Medicine') ? 'selected' : ''; ?>>Medicine</option>
                                        <option value="Grooming" <?php echo ($product['category'] === 'Grooming') ? 'selected' : ''; ?>>Grooming</option>
                                    </select>
                                    <div class="form-hint">Select the appropriate product category</div>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Price (Rs.) <span class="required">*</span></label>
                                    <input type="number" class="form-control-modern" name="price" id="productPrice"
                                           value="<?php echo htmlspecialchars($product['price']); ?>" 
                                           placeholder="0.00" step="0.01" min="0.01" required>
                                    <div class="form-hint">Enter price in Sri Lankan Rupees</div>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Weight <span class="required">*</span></label>
                                    <input type="text" class="form-control-modern" name="weight" id="productWeight"
                                           value="<?php echo htmlspecialchars($product['weight']); ?>" 
                                           placeholder="e.g., 1kg, 500g, 2.5kg" required>
                                    <div class="form-hint">Include unit of measurement (kg, g, lbs, etc.)</div>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Stock Quantity <span class="required">*</span></label>
                                    <input type="number" class="form-control-modern" name="stock" id="productStock"
                                           value="<?php echo htmlspecialchars($product['stock']); ?>" 
                                           placeholder="0" min="0" required>
                                    <div class="form-hint">Available quantity in inventory</div>
                                </div>
                            </div>

                            <!-- Product Description -->
                            <div class="form-group">
                                <label class="form-label">Product Description <span class="required">*</span></label>
                                <textarea class="form-textarea-modern" name="description" id="productDescription" 
                                          placeholder="Enter detailed product description..." maxlength="1000" required><?php echo htmlspecialchars($product['description']); ?></textarea>
                                <div class="character-count">
                                    <span id="descriptionCount">0</span>/1000 characters
                                </div>
                            </div>

                            <!-- Image Management -->
                            <div class="form-group">
                                <label class="form-label">Product Image</label>
                                
                                <?php if (!empty($product['image'])): ?>
                                    <div class="current-image-section" id="currentImageSection">
                                        <h6>Current Image:</h6>
                                        <img src="../assets/images/products/<?php echo htmlspecialchars($product['image']); ?>" 
                                             alt="Current Product Image" class="current-image">
                                        <div class="image-actions">
                                            <button type="button" class="btn-change-image" id="changeImageBtn">
                                                <i class="fas fa-camera me-1"></i> Change Image
                                            </button>
                                            <button type="button" class="btn-remove-image" id="removeImageBtn">
                                                <i class="fas fa-trash me-1"></i> Remove Image
                                            </button>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="image-upload-area" id="imageUploadArea" <?php echo !empty($product['image']) ? 'style="display: none;"' : ''; ?>>
                                    <i class="fas fa-cloud-upload-alt" style="font-size: 3rem; color: #cbd5e0; margin-bottom: 16px;"></i>
                                    <h5 style="color: #4a5568; margin-bottom: 8px;">Upload Product Image</h5>
                                    <p style="color: #718096; margin-bottom: 16px;">Drag and drop or click to select</p>
                                    <input type="file" name="image" id="imageInput" accept="image/*" style="display: none;">
                                    <button type="button" class="btn-primary-modern" onclick="document.getElementById('imageInput').click();">
                                        <i class="fas fa-upload me-2"></i>Choose Image
                                    </button>
                                    <div class="form-hint" style="margin-top: 12px;">
                                        Supported formats: JPG, PNG, GIF. Maximum size: 5MB
                                    </div>
                                </div>
                                
                                <!-- Hidden input for remove image flag -->
                                <input type="hidden" name="remove_image" id="removeImageFlag" value="0">
                            </div>

                            <!-- Form Actions -->
                            <div class="form-actions">
                                <a href="manage_products.php" class="btn-secondary-modern">
                                    <i class="fas fa-times me-2"></i>Cancel
                                </a>
                                <button type="submit" name="update_product" class="btn-primary-modern">
                                    <i class="fas fa-save me-2"></i>Update Product
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
// Character count for description
document.getElementById('productDescription').addEventListener('input', function() {
    const count = this.value.length;
    document.getElementById('descriptionCount').textContent = count;
    
    if (count > 1000) {
        this.value = this.value.substring(0, 1000);
        document.getElementById('descriptionCount').textContent = 1000;
    }
});

// Initialize character count
document.addEventListener('DOMContentLoaded', function() {
    const description = document.getElementById('productDescription');
    document.getElementById('descriptionCount').textContent = description.value.length;
});

// Image management functions
document.getElementById('changeImageBtn')?.addEventListener('click', function() {
    document.getElementById('imageUploadArea').style.display = 'block';
    document.getElementById('imageInput').click();
});

document.getElementById('removeImageBtn')?.addEventListener('click', function() {
    if (confirm('Are you sure you want to remove the current image?')) {
        document.getElementById('currentImageSection').style.display = 'none';
        document.getElementById('imageUploadArea').style.display = 'block';
        document.getElementById('removeImageFlag').value = '1';
    }
});

// Image preview functionality
document.getElementById('imageInput').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        // Validate file size (5MB)
        if (file.size > 5 * 1024 * 1024) {
            alert('File size must be less than 5MB');
            this.value = '';
            return;
        }
        
        // Validate file type
        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        if (!allowedTypes.includes(file.type)) {
            alert('Only JPEG, PNG, and GIF images are allowed');
            this.value = '';
            return;
        }
        
        // Show preview (optional)
        const reader = new FileReader();
        reader.onload = function(e) {
            // You can add image preview functionality here if needed
            console.log('Image selected:', file.name);
        };
        reader.readAsDataURL(file);
    }
});

// Form validation
document.getElementById('editProductForm').addEventListener('submit', function(e) {
    const requiredFields = ['name', 'brand', 'category', 'price', 'weight', 'stock', 'description'];
    let isValid = true;
    
    requiredFields.forEach(function(field) {
        const input = document.getElementsByName(field)[0];
        if (!input.value.trim()) {
            isValid = false;
            input.style.borderColor = '#ef4444';
        } else {
            input.style.borderColor = '#e5e7eb';
        }
    });
    
    if (!isValid) {
        e.preventDefault();
        alert('Please fill all required fields');
    }
});
</script>

<?php
// Include footer
include_once '../includes/footer.php';

// Close database connection
$conn->close();
?>