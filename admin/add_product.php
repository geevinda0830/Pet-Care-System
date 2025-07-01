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
    
    // Validation
    if (empty($name)) {
        $error = "Product name is required.";
    } elseif (empty($brand)) {
        $error = "Brand is required.";
    } elseif (empty($category)) {
        $error = "Category is required.";
    } elseif ($price <= 0) {
        $error = "Valid price is required.";
    } elseif (empty($weight)) {
        $error = "Weight is required.";
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
            mkdir($target_dir, 0777, true);
        }
        
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
                    $image = "product_" . time() . "." . $imageFileType;
                    $target_file = $target_dir . $image;
                    
                    // Try to upload file
                    if (!move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
                        $error = "Sorry, there was an error uploading your file.";
                        $image = ""; // Reset image name if upload fails
                    }
                }
            }
        }
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

// Include header
include '../includes/header.php';
?>

<style>
.add-product-section {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 100px 0 80px;
    position: relative;
    overflow: hidden;
}

.add-product-particles {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="20" cy="20" r="2" fill="rgba(255,255,255,0.1)"/><circle cx="80" cy="40" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="40" cy="80" r="1.5" fill="rgba(255,255,255,0.1)"/></svg>');
    animation: float 20s ease-in-out infinite;
}

.form-card {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    border-radius: 24px;
    box-shadow: 0 20px 40px rgba(102, 126, 234, 0.3);
    border: 1px solid rgba(255, 255, 255, 0.2);
    padding: 48px;
    margin-top: -80px;
    position: relative;
    z-index: 10;
}

.section-title {
    font-size: 3rem;
    font-weight: 800;
    color: white;
    margin-bottom: 16px;
    text-align: center;
}

.section-subtitle {
    font-size: 1.25rem;
    color: rgba(255, 255, 255, 0.9);
    text-align: center;
    margin-bottom: 0;
}

.form-group {
    margin-bottom: 24px;
}

.form-label {
    font-weight: 600;
    color: #374151;
    margin-bottom: 8px;
    display: block;
}

.form-control {
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    padding: 16px;
    font-size: 16px;
    transition: all 0.3s ease;
}

.form-control:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    outline: none;
}

.btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    padding: 16px 32px;
    border-radius: 12px;
    font-weight: 600;
    font-size: 16px;
    transition: all 0.3s ease;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
}

.btn-secondary {
    background: #6b7280;
    border: none;
    padding: 16px 32px;
    border-radius: 12px;
    font-weight: 600;
    font-size: 16px;
    color: white;
    text-decoration: none;
    display: inline-block;
    transition: all 0.3s ease;
}

.btn-secondary:hover {
    background: #4b5563;
    color: white;
    text-decoration: none;
}

.alert {
    border: none;
    border-radius: 12px;
    padding: 16px 20px;
    margin-bottom: 24px;
}

.alert-success {
    background: #ecfdf5;
    color: #065f46;
    border-left: 4px solid #10b981;
}

.alert-danger {
    background: #fef2f2;
    color: #991b1b;
    border-left: 4px solid #ef4444;
}

@keyframes float {
    0%, 100% { transform: translateY(0px) rotate(0deg); }
    50% { transform: translateY(-20px) rotate(180deg); }
}
</style>

<!-- Add Product Header -->
<section class="add-product-section">
    <div class="add-product-particles"></div>
    <div class="container">
        <div class="row">
            <div class="col-12">
                <h1 class="section-title">Add New Product</h1>
                <p class="section-subtitle">Add products to your pet store inventory</p>
            </div>
        </div>
    </div>
</section>

<!-- Add Product Form -->
<section class="py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="form-card">
                    
                    <!-- Success/Error Messages -->
                    <?php if (!empty($message)): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i><?php echo $message; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Product Form -->
                    <form method="POST" enctype="multipart/form-data">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="name" class="form-label">Product Name *</label>
                                    <input type="text" class="form-control" id="name" name="name" 
                                           value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" required>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="brand" class="form-label">Brand *</label>
                                    <input type="text" class="form-control" id="brand" name="brand" 
                                           value="<?php echo isset($_POST['brand']) ? htmlspecialchars($_POST['brand']) : ''; ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="category" class="form-label">Category *</label>
                                    <select class="form-control" id="category" name="category" required>
                                        <option value="">Select Category</option>
                                        <option value="Dog Food" <?php echo (isset($_POST['category']) && $_POST['category'] == 'Dog Food') ? 'selected' : ''; ?>>Dog Food</option>
                                        <option value="Cat Food" <?php echo (isset($_POST['category']) && $_POST['category'] == 'Cat Food') ? 'selected' : ''; ?>>Cat Food</option>
                                        <option value="Dog Accessories" <?php echo (isset($_POST['category']) && $_POST['category'] == 'Dog Accessories') ? 'selected' : ''; ?>>Dog Accessories</option>
                                        <option value="Cat Accessories" <?php echo (isset($_POST['category']) && $_POST['category'] == 'Cat Accessories') ? 'selected' : ''; ?>>Cat Accessories</option>
                                        <option value="Toys" <?php echo (isset($_POST['category']) && $_POST['category'] == 'Toys') ? 'selected' : ''; ?>>Toys</option>
                                        <option value="Healthcare" <?php echo (isset($_POST['category']) && $_POST['category'] == 'Healthcare') ? 'selected' : ''; ?>>Healthcare</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="price" class="form-label">Price ($) *</label>
                                    <input type="number" step="0.01" class="form-control" id="price" name="price" 
                                           value="<?php echo isset($_POST['price']) ? htmlspecialchars($_POST['price']) : ''; ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="weight" class="form-label">Weight *</label>
                                    <input type="text" class="form-control" id="weight" name="weight" 
                                           placeholder="e.g., 2kg, 500g" 
                                           value="<?php echo isset($_POST['weight']) ? htmlspecialchars($_POST['weight']) : ''; ?>" required>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="stock" class="form-label">Stock Quantity *</label>
                                    <input type="number" class="form-control" id="stock" name="stock" 
                                           value="<?php echo isset($_POST['stock']) ? htmlspecialchars($_POST['stock']) : ''; ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="image" class="form-label">Product Image</label>
                            <input type="file" class="form-control" id="image" name="image" accept="image/*">
                            <small class="text-muted">Supported formats: JPG, JPEG, PNG, GIF. Max size: 5MB</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="description" class="form-label">Description *</label>
                            <textarea class="form-control" id="description" name="description" rows="4" 
                                      required><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                        </div>
                        
                        <div class="d-flex gap-3 justify-content-end">
                            <a href="manage_products.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Back to Products
                            </a>
                            <button type="submit" name="add_product" class="btn btn-primary">
                                <i class="fas fa-plus me-2"></i>Add Product
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include '../includes/footer.php'; ?>