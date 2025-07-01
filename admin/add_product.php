<?php
// Enable error reporting to see what's wrong
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Check if user is logged in as admin (adjust this based on your session structure)
if (!isset($_SESSION['user_id'])) {
    die("Please login first. <a href='../login.php'>Login Here</a>");
}

// Database connection - adjust the path if needed
$servername = "localhost";
$username = "root";        // Change if different
$password = "";            // Change if you have a password
$dbname = "pet_care";      // Change to your database name

$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$message = "";
$error = "";

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_product'])) {
    
    // Get form data
    $name = $_POST['name'];
    $brand = $_POST['brand'];
    $category = $_POST['category'];
    $price = $_POST['price'];
    $weight = $_POST['weight'];
    $stock = $_POST['stock'];
    $description = $_POST['description'];
    $userID = $_SESSION['user_id'];
    
    // Handle image upload
    $image = "";
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $target_dir = "../assets/images/products/";
        
        // Create directory if it doesn't exist
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $imageFileType = strtolower(pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION));
        $image = "product_" . time() . "." . $imageFileType;
        $target_file = $target_dir . $image;
        
        // Check if image file is valid
        $check = getimagesize($_FILES["image"]["tmp_name"]);
        if($check === false) {
            $error = "File is not an image.";
        } else {
            // Try to upload file
            if (!move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
                $error = "Sorry, there was an error uploading your file.";
                $image = ""; // Reset image name if upload fails
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

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Product - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; }
        .container { max-width: 800px; margin: 50px auto; }
        .card { border-radius: 15px; box-shadow: 0 0 20px rgba(0,0,0,0.1); }
        .card-header { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            color: white; 
            border-radius: 15px 15px 0 0; 
        }
        .form-control, .form-select { border-radius: 8px; margin-bottom: 15px; }
        .btn-primary { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            border: none; 
            border-radius: 8px; 
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0">🛍️ Add New Product</h3>
            </div>
            <div class="card-body p-4">
                
                <!-- Success Message -->
                <?php if (!empty($message)): ?>
                    <div class="alert alert-success">
                        ✅ <?php echo $message; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Error Message -->
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger">
                        ❌ <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Product Form -->
                <form method="POST" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label">Product Name *</label>
                            <input type="text" name="name" class="form-control" 
                                   value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" 
                                   placeholder="Enter product name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Brand *</label>
                            <input type="text" name="brand" class="form-control" 
                                   value="<?php echo isset($_POST['brand']) ? htmlspecialchars($_POST['brand']) : ''; ?>" 
                                   placeholder="Enter brand name" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label">Category *</label>
                            <select name="category" class="form-select" required>
                                <option value="">Select Category</option>
                                <option value="Dog Food">Dog Food</option>
                                <option value="Cat Food">Cat Food</option>
                                <option value="Bird Food">Bird Food</option>
                                <option value="Fish Food">Fish Food</option>
                                <option value="Toys">Toys</option>
                                <option value="Accessories">Accessories</option>
                                <option value="Medicine">Medicine</option>
                                <option value="Grooming">Grooming</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Weight/Size *</label>
                            <input type="text" name="weight" class="form-control" 
                                   value="<?php echo isset($_POST['weight']) ? htmlspecialchars($_POST['weight']) : ''; ?>" 
                                   placeholder="e.g., 5kg, 500g, Large" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label">Price (Rs.) *</label>
                            <input type="number" name="price" class="form-control" step="0.01" 
                                   value="<?php echo isset($_POST['price']) ? $_POST['price'] : ''; ?>" 
                                   placeholder="Enter price" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Stock Quantity *</label>
                            <input type="number" name="stock" class="form-control" 
                                   value="<?php echo isset($_POST['stock']) ? $_POST['stock'] : ''; ?>" 
                                   placeholder="Enter stock quantity" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description *</label>
                        <textarea name="description" class="form-control" rows="4" 
                                  placeholder="Enter product description" required><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Product Image</label>
                        <input type="file" name="image" class="form-control" accept="image/*">
                        <small class="text-muted">Optional: Upload product image (JPEG, PNG, GIF)</small>
                    </div>
                    
                    <div class="d-flex gap-3">
                        <button type="submit" name="add_product" class="btn btn-primary px-4">
                            ➕ Add Product
                        </button>
                        <a href="dashboard.php" class="btn btn-secondary px-4">
                            ⬅️ Back to Dashboard
                        </a>
                    </div>
                </form>
                
                <!-- Debug Info -->
                <div class="mt-4 p-3 bg-light rounded">
                    <h6>🔍 Debug Info:</h6>
                    <small>
                        Session User ID: <?php echo isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'Not set'; ?><br>
                        Upload Directory: ../assets/images/products/<br>
                        Directory Exists: <?php echo is_dir('../assets/images/products/') ? 'Yes' : 'No'; ?><br>
                        Directory Writable: <?php echo is_writable('../assets/images/products/') ? 'Yes' : 'No'; ?>
                    </small>
                </div>
            </div>
        </div>
    </div>
</body>
</html>