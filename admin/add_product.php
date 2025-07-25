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

$message = "";
$error = "";

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_product'])) {
    
    // Get and sanitize form data
    $name = trim($_POST['name']);
    $brand = trim($_POST['brand']);
    $category = trim($_POST['category']);
    $price = floatval($_POST['price']);
    $weight = trim($_POST['weight']);
    $stock = intval($_POST['stock']);
    $description = trim($_POST['description']);
    $userID = $_SESSION['user_id'];
    
    // Categories that don't need weight
    $weight_optional_categories = ['Toys', 'Accessories', 'Dog Accessories', 'Cat Accessories'];
    
    // Validation
    if (empty($name)) {
        $error = "Product name is required.";
    } elseif (empty($brand)) {
        $error = "Brand is required.";
    } elseif (empty($category)) {
        $error = "Category is required.";
    } elseif ($price <= 0) {
        $error = "Valid price is required.";
    } elseif (empty($weight) && !in_array($category, $weight_optional_categories)) {
        $error = "Weight is required for this category.";
    } elseif ($stock < 0) {
        $error = "Valid stock quantity is required.";
    } elseif (empty($description)) {
        $error = "Description is required.";
    }
    
    // Handle image upload
    $image = "";
    if (empty($error) && isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $target_dir = "../assets/images/products/";
        
        // Create directory if it doesn't exist
        if (!file_exists($target_dir)) {
            if (!mkdir($target_dir, 0777, true)) {
                $error = "Failed to create upload directory.";
            } else {
                chmod($target_dir, 0777); // Ensure write permissions
            }
        } else {
            // Ensure directory is writable
            if (!is_writable($target_dir)) {
                chmod($target_dir, 0777);
            }
        }
        
        if (empty($error)) {
            $imageFileType = strtolower(pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION));
            $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (!in_array($imageFileType, $allowed_types)) {
                $error = "Only JPG, JPEG, PNG & GIF files are allowed.";
            } else {
                // Check file size (5MB max)
                if ($_FILES["image"]["size"] > 5000000) {
                    $error = "File is too large. Maximum size is 5MB.";
                } else {
                    // Check if image file is valid
                    $check = getimagesize($_FILES["image"]["tmp_name"]);
                    if($check === false) {
                        $error = "File is not an image.";
                    } else {
                        // Generate unique filename
                        $image = "product_" . time() . "_" . uniqid() . "." . $imageFileType;
                        $target_file = $target_dir . $image;
                        
                        // Try to upload file
                        if (!move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
                            $error = "Sorry, there was an error uploading your file. Check directory permissions.";
                            $image = ""; // Reset image name if upload fails
                        }
                    }
                }
            }
        }
    }
    
    // Set default weight for categories that don't need it
    if (in_array($category, $weight_optional_categories) && empty($weight)) {
        $weight = "N/A";
    }
    
    // Insert into database if no errors
    if (empty($error)) {
        $sql = "INSERT INTO pet_food_and_accessories (name, brand, category, price, weight, image, description, stock, userID) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        
        if ($stmt) {
            $stmt->bind_param("sssdsssii", $name, $brand, $category, $price, $weight, $image, $description, $stock, $userID);
            
            if ($stmt->execute()) {
                $message = "Product added successfully!";
                // Clear form data after successful submission
                $_POST = array();
            } else {
                $error = "Error: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $error = "Error preparing statement: " . $conn->error;
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Product - Admin Dashboard</title>
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

.btn-primary-modern {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
}

.btn-primary-modern:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
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
    display: flex;
    align-items: center;
    gap: 10px;
}

.alert-success-modern {
    background: linear-gradient(135deg, #d1fae5, #a7f3d0);
    color: #065f46;
}

.alert-danger-modern {
    background: linear-gradient(135deg, #fee2e2, #fecaca);
    color: #991b1b;
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

.image-upload-area.dragover {
    border-color: #667eea;
    background: #f0f4ff;
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
            <i class="fas fa-plus-circle me-2"></i>
            Product Management
        </div>
        <h1 class="admin-title">Add New Product</h1>
        <p class="admin-subtitle">Create a new product listing for your pet store</p>
        
        
    </div>
</section>

<!-- Content Section -->
<section class="content-section">
    <div class="container">
        
        <?php if (!empty($message)): ?>
            <div class="alert alert-modern alert-success-modern">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-modern alert-danger-modern">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <div class="modern-card">
            <div class="card-header-modern">
                <h4><i class="fas fa-info-circle"></i> Product Information</h4>
            </div>
            
            <div class="card-body-modern">
                <form action="add_product.php" method="POST" enctype="multipart/form-data" id="addProductForm">
                    
                    <!-- Basic Information -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group-modern">
                                <label class="form-label-modern">
                                    Product Name
                                    <span class="required-indicator">*</span>
                                </label>
                                <input type="text" class="form-control-modern" name="name" id="name" 
                                       value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" 
                                       placeholder="Enter product name" required>
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
                                       value="<?php echo isset($_POST['brand']) ? htmlspecialchars($_POST['brand']) : ''; ?>" 
                                       placeholder="Enter brand name" required>
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
                                    <option value="Dog Food" <?php echo (isset($_POST['category']) && $_POST['category'] == 'Dog Food') ? 'selected' : ''; ?>>Dog Food</option>
                                    <option value="Cat Food" <?php echo (isset($_POST['category']) && $_POST['category'] == 'Cat Food') ? 'selected' : ''; ?>>Cat Food</option>
                                    <option value="Bird Food" <?php echo (isset($_POST['category']) && $_POST['category'] == 'Bird Food') ? 'selected' : ''; ?>>Bird Food</option>
                                    <option value="Fish Food" <?php echo (isset($_POST['category']) && $_POST['category'] == 'Fish Food') ? 'selected' : ''; ?>>Fish Food</option>
                                    <option value="Dog Accessories" <?php echo (isset($_POST['category']) && $_POST['category'] == 'Dog Accessories') ? 'selected' : ''; ?>>Dog Accessories</option>
                                    <option value="Cat Accessories" <?php echo (isset($_POST['category']) && $_POST['category'] == 'Cat Accessories') ? 'selected' : ''; ?>>Cat Accessories</option>
                                    <option value="Toys" <?php echo (isset($_POST['category']) && $_POST['category'] == 'Toys') ? 'selected' : ''; ?>>Toys</option>
                                    <option value="Accessories" <?php echo (isset($_POST['category']) && $_POST['category'] == 'Accessories') ? 'selected' : ''; ?>>Accessories</option>
                                    <option value="Healthcare" <?php echo (isset($_POST['category']) && $_POST['category'] == 'Healthcare') ? 'selected' : ''; ?>>Healthcare</option>
                                    <option value="Medicine" <?php echo (isset($_POST['category']) && $_POST['category'] == 'Medicine') ? 'selected' : ''; ?>>Medicine</option>
                                    <option value="Grooming" <?php echo (isset($_POST['category']) && $_POST['category'] == 'Grooming') ? 'selected' : ''; ?>>Grooming</option>
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
                                       value="<?php echo isset($_POST['price']) ? htmlspecialchars($_POST['price']) : ''; ?>" 
                                       placeholder="0.00" required>
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
                                       value="<?php echo isset($_POST['weight']) ? htmlspecialchars($_POST['weight']) : ''; ?>" 
                                       placeholder="e.g., 2kg, 500g">
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
                                       value="<?php echo isset($_POST['stock']) ? htmlspecialchars($_POST['stock']) : ''; ?>" 
                                       placeholder="Available quantity" required>
                                <div class="form-hint">
                                    <i class="fas fa-info-circle"></i>
                                    Current stock available for sale
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Image Upload -->
                    <div class="form-group-modern">
                        <label class="form-label-modern">
                            Product Image
                        </label>
                        <div class="image-upload-area" onclick="document.getElementById('image').click()">
                            <div class="upload-icon">
                                <i class="fas fa-cloud-upload-alt"></i>
                            </div>
                            <div class="upload-text">Click to upload product image</div>
                            <div class="upload-hint">Supported formats: JPG, JPEG, PNG, GIF (Max: 5MB)</div>
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
                                  placeholder="Provide detailed information about the product..." required><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
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
                        <button type="submit" name="add_product" class="btn-modern btn-primary-modern">
                            <i class="fas fa-plus"></i>
                            Add Product
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
        weightField.placeholder = 'Optional - leave blank or enter N/A';
    } else {
        weightField.required = true;
        weightRequired.style.display = 'inline';
        weightHint.innerHTML = '<i class="fas fa-info-circle"></i> Include unit of measurement (kg, g, lbs, etc.)';
        weightHint.classList.remove('weight-optional');
        weightField.placeholder = 'e.g., 2kg, 500g';
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