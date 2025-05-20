<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in as pet sitter
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'pet_sitter') {
    $_SESSION['error_message'] = "You must be logged in as a pet sitter to access this page.";
    header("Location: ../login.php");
    exit();
}

// Include database connection
require_once '../config/db_connect.php';

$user_id = $_SESSION['user_id'];

// Initialize variables
$name = $type = $price = $description = '';
$errors = array();

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $name = trim($_POST['name']);
    $type = trim($_POST['type']);
    $price = $_POST['price'];
    $description = trim($_POST['description']);
    
    // Validate form data
    if (empty($name)) {
        $errors[] = "Service name is required";
    }
    
    if (empty($type)) {
        $errors[] = "Service type is required";
    }
    
    if (empty($price) || !is_numeric($price) || $price <= 0) {
        $errors[] = "Price must be a valid positive number";
    }
    
    if (empty($description)) {
        $errors[] = "Description is required";
    }
    
    // Process image upload if no errors
    $image_filename = null;
    if (empty($errors) && isset($_FILES['service_image']) && $_FILES['service_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $target_dir = "../assets/images/services/";
        
        // Create directory if it doesn't exist
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $file_extension = strtolower(pathinfo($_FILES["service_image"]["name"], PATHINFO_EXTENSION));
        $image_filename = "service_" . time() . "_" . uniqid() . "." . $file_extension;
        $target_file = $target_dir . $image_filename;
        $upload_ok = true;
        
        // Check if image file is a actual image
        $check = getimagesize($_FILES["service_image"]["tmp_name"]);
        if ($check === false) {
            $errors[] = "File is not an image.";
            $upload_ok = false;
        }
        
        // Check file size (limit to 5MB)
        if ($_FILES["service_image"]["size"] > 5000000) {
            $errors[] = "File is too large. Maximum file size is 5MB.";
            $upload_ok = false;
        }
        
        // Allow certain file formats
        if ($file_extension != "jpg" && $file_extension != "png" && $file_extension != "jpeg") {
            $errors[] = "Only JPG, JPEG, PNG files are allowed.";
            $upload_ok = false;
        }
        
        // If everything is ok, try to upload file
        if ($upload_ok) {
            if (!move_uploaded_file($_FILES["service_image"]["tmp_name"], $target_file)) {
                $errors[] = "There was an error uploading your file.";
                $image_filename = null;
            }
        }
    }
    
    // If no errors, insert service into database
    if (empty($errors)) {
        $sql = "INSERT INTO pet_service (name, type, price, image, description, userID) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssdssi", $name, $type, $price, $image_filename, $description, $user_id);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Service added successfully!";
            header("Location: services.php");
            exit();
        } else {
            $errors[] = "Error adding service: " . $conn->error;
        }
        
        $stmt->close();
    }
}

// Include header
include_once '../includes/header.php';
?>

<!-- Page Header -->
<div class="container-fluid bg-light py-4">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="display-5 mb-2">Add a New Service</h1>
                <p class="lead">Create a new pet sitting service to offer to pet owners.</p>
            </div>
            <div class="col-md-4 text-md-end">
                <a href="services.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-1"></i> Back to Services
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Add Service Form -->
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title mb-4">Service Information</h4>
                    
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach($errors as $error): ?>
                                    <li><?php echo $error; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="name" class="form-label">Service Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($name); ?>" required>
                            <div class="form-text">Choose a descriptive name for your service (e.g., "Professional Dog Walking", "In-Home Pet Sitting").</div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="type" class="form-label">Service Type <span class="text-danger">*</span></label>
                                <select class="form-select" id="type" name="type" required>
                                    <option value="" <?php echo empty($type) ? 'selected' : ''; ?>>-- Select Type --</option>
                                    <option value="Pet Sitting" <?php echo ($type === 'Pet Sitting') ? 'selected' : ''; ?>>Pet Sitting</option>
                                    <option value="Dog Walking" <?php echo ($type === 'Dog Walking') ? 'selected' : ''; ?>>Dog Walking</option>
                                    <option value="Pet Boarding" <?php echo ($type === 'Pet Boarding') ? 'selected' : ''; ?>>Pet Boarding</option>
                                    <option value="Pet Grooming" <?php echo ($type === 'Pet Grooming') ? 'selected' : ''; ?>>Pet Grooming</option>
                                    <option value="Pet Training" <?php echo ($type === 'Pet Training') ? 'selected' : ''; ?>>Pet Training</option>
                                    <option value="Medication Administration" <?php echo ($type === 'Medication Administration') ? 'selected' : ''; ?>>Medication Administration</option>
                                    <option value="Other" <?php echo ($type === 'Other') ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="price" class="form-label">Price Per Hour ($) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="price" name="price" min="0.01" step="0.01" value="<?php echo htmlspecialchars($price); ?>" required>
                                <div class="form-text">Set a competitive hourly rate for your service.</div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="description" name="description" rows="6" required><?php echo htmlspecialchars($description); ?></textarea>
                            <div class="form-text">Provide a detailed description of your service, including what pet owners can expect, any special skills or qualifications you have, and any additional information that might be relevant.</div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="service_image" class="form-label">Service Image</label>
                            <input type="file" class="form-control" id="service_image" name="service_image" accept="image/jpeg, image/png, image/jpg">
                            <div class="form-text">Upload an image that represents your service (JPG, JPEG, or PNG only, max 5MB). This will help pet owners visualize the service you're offering.</div>
                        </div>
                        
                        <div class="alert alert-info mb-4">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Tip:</strong> Detailed and accurate information will help pet owners make informed decisions about booking your services.
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg">Add Service</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include_once '../includes/footer.php';

// Close database connection
$conn->close();
?>