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
    
    // Categories that don't need weight
    $weight_optional_categories = ['Toys', 'Accessories', 'Dog Accessories', 'Cat Accessories'];
    
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
    
    // Weight validation - only required for certain categories
    if (empty($weight) && !in_array($category, $weight_optional_categories)) {
        $errors[] = "Weight is required for this category.";
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
                if (!mkdir($upload_dir, 0777, true)) {
                    $errors[] = "Failed to create upload directory.";
                } else {
                    chmod($upload_dir, 0777); // Ensure write permissions
                }
            } else {
                // Ensure directory is writable
                if (!is_writable($upload_dir)) {
                    chmod($upload_dir, 0777);
                }
            }
            
            if (empty($errors)) {
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
                    $errors[] = "Failed to upload image. Check directory permissions.";
                }
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
    
    // Set default weight for categories that don't need it
    if (in_array($category, $weight_optional_categories) && empty($weight)) {
        $weight = "N/A";
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
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Product - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body style="font-family: 'Inter', sans-serif; background: #f8f9fa;">

<?php include '../includes/header.php'; ?>

<style>
/* Modern Admin Styles */
.admin-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 60px 0 40px;
    position: relative;
    overflow: hidden;
}

.admin-header::before {
    content: '';
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
    position: relative;
    z-index: 2;
}

.product-id-badge {
    background: rgba(255, 255, 255, 0.3);
    padding: 6px 12px;
    border-radius: 15px;
    font-size: 0.85rem;
    font-weight: 600;
    display: inline-block;
    margin-bottom: 15px;
}

.admin-subtitle {
    font-size: 1.1rem;
    opacity: 0.9;
    margin-bottom: 0;
    position: relative;
    z-index: 2;
}

.content-section {
    padding: 0;
    margin-top: -30px;
    position: relative;
    z-index: 2;
}

.modern-card {
    background: white;
    border-radius: 20px;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
    border: 1px solid rgba(0, 0, 0, 0.05);
    overflow: hidden;
    margin-bottom: 30px;
}

.card-header-modern {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    padding: 25px 35px;
    border-bottom: 1px solid rgba(0, 0, 0, 0.1);
}

.card-header-modern h4 {
    margin: 0;
    font-weight: 700;
    color: #1e293b;
    display: flex;
    align-items: center;
    gap: 10px;
}

.card-body-modern {
    padding: 35px;
}

.form-group-modern {
    margin-bottom: 25px;
}

.form-label-modern {
    font-weight: 600;
    color: #374151;
    margin-bottom: 8px;
    display: block;
    font-size: 0.95rem;
}

.required-indicator {
    color: #ef4444;
    margin-left: 4px;
}

.form-control-modern {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    font-size: 1rem;
    transition: all 0.3s ease;
    background: #fafafa;
}

.form-control-modern:focus {
    outline: none;
    border-color: #667eea;
    background: white;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    transform: translateY(-1px);
}

.form-select-modern {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    font-size: 1rem;
    transition: all 0.3s ease;
    background: #fafafa;
    appearance: none;
    background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6,9 12,15 18,9'%3e%3c/polyline%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right 12px center;
    background-size: 20px;
}

.form-select-modern:focus {
    outline: none;
    border-color: #667eea;
    background-color: white;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.form-hint {
    font-size: 0.85rem;
    color: #6b7280;
    margin-top: 6px;
    display: flex;
    align-items: center;
    gap: 5px;
}

.weight-optional {
    color: #10b981 !important;
}

.btn-modern {
    padding: 14px 28px;
    border-radius: 12px;
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

.btn-success-modern {
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
}

.btn-success-modern:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 30px rgba(16, 185, 129, 0.4);
    color: white;
}

.btn-secondary-modern {
    background: #f8f9fa;
    color: #6c757d;
    border: 2px solid #e5e7eb;
}

.btn-secondary-modern:hover {
    background: #e9ecef;
    color: #495057;
    text-decoration: none;
    transform: translateY(-1px);
}

.alert-modern {
    border-radius: 12px;
    border: none;
    padding: 16px 20px;
    margin-bottom: 25px;
}

.alert-danger-modern {
    background: linear-gradient(135deg, #fee2e2, #fecaca);
    color: #991b1b;
}

.current-image-section {
    background: #f8f9ff;
    border: 2px solid #e0e7ff;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
}

.current-image {
    max-width: 200px;
    max-height: 200px;
    border-radius: 12px;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
}

.image-upload-area {
    border: 2px dashed #d1d5db;
    border-radius: 12px;
    padding: 30px;
    text-align: center;
    transition: all 0.3s ease;
    background: #fafafa;
}

.image-upload-area:hover {
    border-color: #667eea;
    background: #f8f9ff;
}

.upload-icon {
    font-size: 3rem;
    color: #9ca3af;
    margin-bottom: 15px;
}

.upload-text {
    color: #374151;
    font-weight: 600;
    margin-bottom: 5px;
}

.upload-hint {
    color: #6b7280;
    font-size: 0.9rem;
}

.image-preview {
    max-width: 200px;
    max-height: 200px;
    border-radius: 12px;
    margin-top: 15px;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
}

.breadcrumb-modern {
    background: none;
    padding: 0;
    margin-bottom: 20px;
}

.breadcrumb-modern .breadcrumb-item {
    color: rgba(255, 255, 255, 0.8);
}

.breadcrumb-modern .breadcrumb-item.active {
    color: white;
    font-weight: 600;
}

.breadcrumb-modern .breadcrumb-item a {
    color: rgba(255, 255, 255, 0.9);
    text-decoration: none;
}

.breadcrumb-modern .breadcrumb-item a:hover {
    color: white;
}

.remove-image-checkbox {
    background: #fee2e2;
    border: 2px solid #fecaca;
    border-radius: 8px;
    padding: 10px 15px;
    margin-top: 15px;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    color: #991b1b;
    font-weight: 600;
}

@media (max-width: 768px) {
    .admin-title {
        font-size: 2rem;
    }
    
    .card-body-modern {
        padding: 25px;
    }
    
    .btn-modern {
        width: 100%;
        justify-content: center;
        margin-bottom: 10px;
    }
}
</style>

<!-- Admin Header -->
<section class="admin-header">
    <div class="container">
        <div class="admin-badge">
            <i class="fas fa-edit me-2"></i>
            Product Management
        </div>
        <h1 class="admin-title">Edit Product</h1>
        <div class="product-id-badge">Product ID: #<?php echo $product['productID']; ?></div>
        <p class="admin-subtitle">Update product information and details</p>
        
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb breadcrumb-modern">
                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="manage_products.php">Products</a></li>
                <li class="breadcrumb-item active">Edit Product</li>
            </ol>
        </nav>
    </div>
</section>

<!-- Content Section -->
<section class="content-section">
    <div class="container">
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-modern alert-danger-modern">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>Please fix the following errors:</strong>
                <ul class="mb-0 mt-2">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <div class="modern-card">
            <div class="card-header-modern">
                <h4><i class="fas fa-info-circle"></i> Product Information</h4>
            </div>
            
            <div class="card-body-modern">
                <form action="<?php echo 'edit_product.php?id=' . $product_id; ?>" method="post" enctype="multipart/form-data" id="editProductForm">
                    
                    <!-- Basic Information -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group-modern">
                                <label class="form-label-modern">
                                    Product Name
                                    <span class="required-indicator">*</span>
                                </label>
                                <input type="text" class="form-control-modern" name="name" id="name" 
                                       value="<?php echo htmlspecialchars($product['name']); ?>" 
                                       placeholder="Enter product name" maxlength="100" required>
                                <div class="form-hint">
                                    <i class="fas fa-info-circle"></i>
                                    Choose a descriptive and unique product name
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-group-modern">
                                <label class="form-label-modern">
                                    Brand
                                    <span class="required-indicator">*</span>
                                </label>
                                <input type="text" class="form-control-modern" name="brand" id="brand" 
                                       value="<?php echo htmlspecialchars($product['brand']); ?>" 
                                       placeholder="Enter brand name" maxlength="50" required>
                                <div class="form-hint">
                                    <i class="fas fa-info-circle"></i>
                                    Product manufacturer or brand name
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Category and Price -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group-modern">
                                <label class="form-label-modern">
                                    Category
                                    <span class="required-indicator">*</span>
                                </label>
                                <select class="form-select-modern" name="category" id="category" required onchange="toggleWeightField()">
                                    <option value="">Select Category</option>
                                    <option value="Dog Food" <?php echo ($product['category'] === 'Dog Food') ? 'selected' : ''; ?>>Dog Food</option>
                                    <option value="Cat Food" <?php echo ($product['category'] === 'Cat Food') ? 'selected' : ''; ?>>Cat Food</option>
                                    <option value="Bird Food" <?php echo ($product['category'] === 'Bird Food') ? 'selected' : ''; ?>>Bird Food</option>
                                    <option value="Fish Food" <?php echo ($product['category'] === 'Fish Food') ? 'selected' : ''; ?>>Fish Food</option>
                                    <option value="Toys" <?php echo ($product['category'] === 'Toys') ? 'selected' : ''; ?>>Toys</option>
                                    <option value="Accessories" <?php echo ($product['category'] === 'Accessories') ? 'selected' : ''; ?>>Accessories</option>
                                    <option value="Dog Accessories" <?php echo ($product['category'] === 'Dog Accessories') ? 'selected' : ''; ?>>Dog Accessories</option>
                                    <option value="Cat Accessories" <?php echo ($product['category'] === 'Cat Accessories') ? 'selected' : ''; ?>>Cat Accessories</option>
                                    <option value="Medicine" <?php echo ($product['category'] === 'Medicine') ? 'selected' : ''; ?>>Medicine</option>
                                    <option value="Healthcare" <?php echo ($product['category'] === 'Healthcare') ? 'selected' : ''; ?>>Healthcare</option>
                                    <option value="Grooming" <?php echo ($product['category'] === 'Grooming') ? 'selected' : ''; ?>>Grooming</option>
                                </select>
                                <div class="form-hint">
                                    <i class="fas fa-info-circle"></i>
                                    Select the most appropriate category
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-group-modern">
                                <label class="form-label-modern">
                                    Price (Rs.)
                                    <span class="required-indicator">*</span>
                                </label>
                                <input type="number" step="0.01" class="form-control-modern" name="price" id="price" 
                                       value="<?php echo htmlspecialchars($product['price']); ?>" 
                                       placeholder="0.00" min="0.01" required>
                                <div class="form-hint">
                                    <i class="fas fa-info-circle"></i>
                                    Enter price in Sri Lankan Rupees
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Weight and Stock -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group-modern">
                                <label class="form-label-modern">
                                    Weight
                                    <span id="weight-required" class="required-indicator">*</span>
                                </label>
                                <input type="text" class="form-control-modern" name="weight" id="weight" 
                                       value="<?php echo htmlspecialchars($product['weight']); ?>" 
                                       placeholder="e.g., 1kg, 500g, 2.5kg">
                                <div class="form-hint" id="weight-hint">
                                    <i class="fas fa-info-circle"></i>
                                    Include unit of measurement (kg, g, lbs, etc.)
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-group-modern">
                                <label class="form-label-modern">
                                    Stock Quantity
                                    <span class="required-indicator">*</span>
                                </label>
                                <input type="number" class="form-control-modern" name="stock" id="stock" 
                                       value="<?php echo htmlspecialchars($product['stock']); ?>" 
                                       placeholder="Available quantity" min="0" required>
                                <div class="form-hint">
                                    <i class="fas fa-info-circle"></i>
                                    Current stock available for sale
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Image Management -->
                    <div class="form-group-modern">
                        <label class="form-label-modern">
                            Product Image
                        </label>
                        
                        <?php if ($product['image']): ?>
                            <div class="current-image-section">
                                <div class="row align-items-center">
                                    <div class="col-md-4">
                                        <img src="../assets/images/products/<?php echo htmlspecialchars($product['image']); ?>" 
                                             alt="Current product image" class="current-image">
                                    </div>
                                    <div class="col-md-8">
                                        <h6 class="mb-2"><i class="fas fa-image me-2"></i>Current Image</h6>
                                        <p class="text-muted mb-3">This is the current product image. You can replace it by uploading a new image below.</p>
                                        
                                        <div class="remove-image-checkbox">
                                            <input type="checkbox" name="remove_image" value="1" id="removeImage">
                                            <label for="removeImage">
                                                <i class="fas fa-trash me-1"></i>
                                                Remove current image
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="image-upload-area" onclick="document.getElementById('image').click()">
                            <div class="upload-icon">
                                <i class="fas fa-cloud-upload-alt"></i>
                            </div>
                            <div class="upload-text">
                                <?php echo $product['image'] ? 'Upload new image (optional)' : 'Click to upload product image'; ?>
                            </div>
                            <div class="upload-hint">Supported formats: JPEG, PNG, GIF (Max: 5MB)</div>
                            <input type="file" class="d-none" name="image" id="image" accept="image/*">
                        </div>
                        <div id="imagePreview" class="text-center"></div>
                    </div>
                    
                    <!-- Description -->
                    <div class="form-group-modern">
                        <label class="form-label-modern">
                            Product Description
                            <span class="required-indicator">*</span>
                        </label>
                        <textarea class="form-control-modern" name="description" id="description" rows="4" 
                                  placeholder="Provide detailed information about the product..." required><?php echo htmlspecialchars($product['description']); ?></textarea>
                        <div class="form-hint">
                            <i class="fas fa-info-circle"></i>
                            Include features, benefits, and usage instructions
                        </div>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="text-end mt-4">
                        <a href="manage_products.php" class="btn-modern btn-secondary-modern me-3">
                            <i class="fas fa-arrow-left"></i>
                            Back to Products
                        </a>
                        <button type="submit" name="update_product" class="btn-modern btn-success-modern">
                            <i class="fas fa-save"></i>
                            Update Product
                        </button>
                    </div>
                    
                </form>
            </div>
        </div>
    </div>
</section>

<script>
function toggleWeightField() {
    const category = document.getElementById('category').value;
    const weightField = document.getElementById('weight');
    const weightRequired = document.getElementById('weight-required');
    const weightHint = document.getElementById('weight-hint');
    
    const weightOptionalCategories = ['Toys', 'Accessories', 'Dog Accessories', 'Cat Accessories'];
    
    if (weightOptionalCategories.includes(category)) {
        weightField.required = false;
        weightRequired.style.display = 'none';
        weightHint.innerHTML = '<i class="fas fa-check-circle" style="color: #10b981;"></i> Weight is optional for this category';
        weightHint.classList.add('weight-optional');
        if (weightField.placeholder.includes('required')) {
            weightField.placeholder = 'Optional - leave blank or enter N/A';
        }
    } else {
        weightField.required = true;
        weightRequired.style.display = 'inline';
        weightHint.innerHTML = '<i class="fas fa-info-circle"></i> Include unit of measurement (kg, g, lbs, etc.)';
        weightHint.classList.remove('weight-optional');
        weightField.placeholder = 'e.g., 1kg, 500g, 2.5kg';
    }
}

// Image preview functionality
document.getElementById('image').addEventListener('change', function(e) {
    const file = e.target.files[0];
    const preview = document.getElementById('imagePreview');
    
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.innerHTML = `
                <div class="mt-3">
                    <h6 class="mb-2"><i class="fas fa-eye me-2"></i>New Image Preview</h6>
                    <img src="${e.target.result}" alt="Preview" class="image-preview">
                    <p class="mt-2 text-muted">${file.name}</p>
                </div>
            `;
        };
        reader.readAsDataURL(file);
    } else {
        preview.innerHTML = '';
    }
});

// Initialize weight field state on page load
document.addEventListener('DOMContentLoaded', function() {
    toggleWeightField();
});

// Drag and drop functionality
const uploadArea = document.querySelector('.image-upload-area');

uploadArea.addEventListener('dragover', (e) => {
    e.preventDefault();
    uploadArea.classList.add('dragover');
});

uploadArea.addEventListener('dragleave', () => {
    uploadArea.classList.remove('dragover');
});

uploadArea.addEventListener('drop', (e) => {
    e.preventDefault();
    uploadArea.classList.remove('dragover');
    
    const files = e.dataTransfer.files;
    if (files.length > 0) {
        document.getElementById('image').files = files;
        document.getElementById('image').dispatchEvent(new Event('change'));
    }
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>