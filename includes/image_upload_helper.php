<?php
// Improved image upload function that creates directory if not exists
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
        throw new Exception("Error uploading file: " . $error_message);
    }
    
    // Create unique filename
    $file_extension = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
    $new_filename = "file_" . time() . "_" . uniqid() . "." . $file_extension;
    $target_file = $target_dir . $new_filename;
    
    // Check if image file is a actual image
    $check = getimagesize($file["tmp_name"]);
    if ($check === false) {
        throw new Exception("File is not an image.");
    }
    
    // Check file size (limit to 5MB)
    if ($file["size"] > 5000000) {
        throw new Exception("File is too large. Maximum file size is 5MB.");
    }
    
    // Allow certain file formats
    if ($file_extension != "jpg" && $file_extension != "png" && $file_extension != "jpeg") {
        throw new Exception("Only JPG, JPEG, PNG files are allowed.");
    }
    
    // Try to move the uploaded file
    if (!move_uploaded_file($file["tmp_name"], $target_file)) {
        throw new Exception("There was an error moving the uploaded file. Directory may not be writable.");
    }
    
    return $new_filename;
}