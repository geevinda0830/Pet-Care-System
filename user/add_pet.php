<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in as pet owner
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'pet_owner') {
    $_SESSION['error_message'] = "You must be logged in as a pet owner to access this page.";
    header("Location: ../login.php");
    exit();
}

// Include database connection
require_once '../config/db_connect.php';

$user_id = $_SESSION['user_id'];

// Initialize variables
$petName = $age = $type = $sex = $breed = $color = '';
$errors = array();

// Improved image upload function
function uploadImage($file, $target_dir) {
    // Create directory if it doesn't exist
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    // Check if file was uploaded successfully
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        if ($file['error'] === UPLOAD_ERR_NO_FILE) {
            // No file was uploaded - this might be okay
            return null;
        }
        // Get error message based on error code
        $upload_errors = array(
            UPLOAD_ERR_INI_SIZE => "The uploaded file exceeds the upload_max_filesize directive in php.ini",
            UPLOAD_ERR_FORM_SIZE => "The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form",
            UPLOAD_ERR_PARTIAL => "The uploaded file was only partially uploaded",
            UPLOAD_ERR_NO_TMP_DIR => "Missing a temporary folder",
            UPLOAD_ERR_CANT_WRITE => "Failed to write file to disk",
            UPLOAD_ERR_EXTENSION => "A PHP extension stopped the file upload"
        );
        $error_message = isset($upload_errors[$file['error']]) ? $upload_errors[$file['error']] : "Unknown upload error";
        return [false, "Error uploading file: " . $error_message];
    }
    
    // Create unique filename
    $file_extension = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
    $new_filename = "pet_" . time() . "_" . uniqid() . "." . $file_extension;
    $target_file = $target_dir . $new_filename;
    
    // Check if image file is a actual image
    $check = getimagesize($file["tmp_name"]);
    if ($check === false) {
        return [false, "File is not an image."];
    }
    
    // Check file size (limit to 5MB)
    if ($file["size"] > 5000000) {
        return [false, "File is too large. Maximum file size is 5MB."];
    }
    
    // Allow certain file formats
    if ($file_extension != "jpg" && $file_extension != "png" && $file_extension != "jpeg") {
        return [false, "Only JPG, JPEG, PNG files are allowed."];
    }
    
    // Try to move the uploaded file
    if (!move_uploaded_file($file["tmp_name"], $target_file)) {
        return [false, "There was an error moving the uploaded file. Directory may not be writable."];
    }
    
    return [true, $new_filename];
}

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $petName = trim($_POST['petName']);
    $age = $_POST['age'];
    $type = trim($_POST['type']);
    $sex = $_POST['sex'];
    $breed = trim($_POST['breed']);
    $color = trim($_POST['color']);
    
    // Validate form data
    if (empty($petName)) {
        $errors[] = "Pet name is required";
    }
    
    if (!is_numeric($age) || $age < 0) {
        $errors[] = "Age must be a valid number";
    }
    
    if (empty($type)) {
        $errors[] = "Pet type is required";
    }
    
    if (empty($breed)) {
        $errors[] = "Breed is required";
    }
    
    if (empty($color)) {
        $errors[] = "Color is required";
    }
    
    // Process image upload if no errors
    $image_filename = null;
    if (empty($errors) && isset($_FILES['pet_image']) && $_FILES['pet_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $target_dir = "../assets/images/pets/";
        
        // Upload the image
        $upload_result = uploadImage($_FILES["pet_image"], $target_dir);
        
        if ($upload_result[0] === false) {
            $errors[] = $upload_result[1];
        } else {
            $image_filename = $upload_result[1];
        }
    }
    
    // If no errors, insert pet into database
    if (empty($errors)) {
        $sql = "INSERT INTO pet_profile (petName, age, type, sex, breed, color, image, userID) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sisssssi", $petName, $age, $type, $sex, $breed, $color, $image_filename, $user_id);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Pet added successfully!";
            header("Location: pets.php");
            exit();
        } else {
            $errors[] = "Error adding pet: " . $conn->error;
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
                <h1 class="display-5 mb-2">Add a New Pet</h1>
                <p class="lead">Enter your pet's details below.</p>
            </div>
            <div class="col-md-4 text-md-end">
                <a href="pets.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-1"></i> Back to My Pets
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Add Pet Form -->
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title mb-4">Pet Information</h4>
                    
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
                            <label for="petName" class="form-label">Pet Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="petName" name="petName" value="<?php echo htmlspecialchars($petName); ?>" required>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="type" class="form-label">Pet Type <span class="text-danger">*</span></label>
                                <select class="form-select" id="type" name="type" required>
                                    <option value="" <?php echo empty($type) ? 'selected' : ''; ?>>-- Select Type --</option>
                                    <option value="Dog" <?php echo ($type === 'Dog') ? 'selected' : ''; ?>>Dog</option>
                                    <option value="Cat" <?php echo ($type === 'Cat') ? 'selected' : ''; ?>>Cat</option>
                                    <option value="Bird" <?php echo ($type === 'Bird') ? 'selected' : ''; ?>>Bird</option>
                                    <option value="Fish" <?php echo ($type === 'Fish') ? 'selected' : ''; ?>>Fish</option>
                                    <option value="Rabbit" <?php echo ($type === 'Rabbit') ? 'selected' : ''; ?>>Rabbit</option>
                                    <option value="Hamster" <?php echo ($type === 'Hamster') ? 'selected' : ''; ?>>Hamster</option>
                                    <option value="Guinea Pig" <?php echo ($type === 'Guinea Pig') ? 'selected' : ''; ?>>Guinea Pig</option>
                                    <option value="Reptile" <?php echo ($type === 'Reptile') ? 'selected' : ''; ?>>Reptile</option>
                                    <option value="Other" <?php echo ($type === 'Other') ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="sex" class="form-label">Sex <span class="text-danger">*</span></label>
                                <select class="form-select" id="sex" name="sex" required>
                                    <option value="" <?php echo empty($sex) ? 'selected' : ''; ?>>-- Select Sex --</option>
                                    <option value="Male" <?php echo ($sex === 'Male') ? 'selected' : ''; ?>>Male</option>
                                    <option value="Female" <?php echo ($sex === 'Female') ? 'selected' : ''; ?>>Female</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="age" class="form-label">Age (years) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="age" name="age" min="0" step="0.1" value="<?php echo htmlspecialchars($age); ?>" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="color" class="form-label">Color <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="color" name="color" value="<?php echo htmlspecialchars($color); ?>" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="breed" class="form-label">Breed <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="breed" name="breed" value="<?php echo htmlspecialchars($breed); ?>" required>
                            <div class="form-text">E.g., Golden Retriever, Siamese, etc.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="pet_image" class="form-label">Pet Photo</label>
                            <input type="file" class="form-control" id="pet_image" name="pet_image" accept="image/jpeg, image/png, image/jpg">
                            <div class="form-text">Upload a photo of your pet (JPG, JPEG, or PNG only, max 5MB)</div>
                        </div>
                        
                        <hr class="my-4">
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg">Add Pet</button>
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