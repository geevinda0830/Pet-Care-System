<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in as pet owner
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'pet_owner') {
    $_SESSION['error_message'] = "You must be logged in as a pet owner to leave a review.";
    header("Location: login.php");
    exit();
}

// Check if the request method is POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    $_SESSION['error_message'] = "Invalid request method.";
    header("Location: user/dashboard.php");
    exit();
}

// Check if required parameters are provided
if (!isset($_POST['sitter_id']) || !isset($_POST['booking_id']) || !isset($_POST['rating']) || !isset($_POST['review'])) {
    $_SESSION['error_message'] = "Missing required parameters.";
    header("Location: user/dashboard.php");
    exit();
}

// Include database connection
require_once 'config/db_connect.php';

$user_id = $_SESSION['user_id'];
$sitter_id = $_POST['sitter_id'];
$booking_id = $_POST['booking_id'];
$rating = $_POST['rating'];
$review = trim($_POST['review']);

// Validate inputs
$errors = array();

if (empty($rating) || !is_numeric($rating) || $rating < 1 || $rating > 5) {
    $errors[] = "Please provide a rating between 1 and 5.";
}

if (empty($review)) {
    $errors[] = "Please write a review.";
}

// Verify that booking belongs to this user, is for this sitter, and is completed
$booking_sql = "SELECT * FROM booking WHERE bookingID = ? AND userID = ? AND sitterID = ? AND status = 'Completed'";
$booking_stmt = $conn->prepare($booking_sql);
$booking_stmt->bind_param("iii", $booking_id, $user_id, $sitter_id);
$booking_stmt->execute();
$booking_result = $booking_stmt->get_result();

if ($booking_result->num_rows === 0) {
    $errors[] = "Invalid booking or you cannot review this pet sitter yet.";
}

$booking_stmt->close();

// Check if user has already reviewed this booking
$check_review_sql = "SELECT reviewID FROM reviews WHERE bookingID = ?";
$check_review_stmt = $conn->prepare($check_review_sql);
$check_review_stmt->bind_param("i", $booking_id);
$check_review_stmt->execute();
$check_review_result = $check_review_stmt->get_result();

if ($check_review_result->num_rows > 0) {
    $errors[] = "You have already reviewed this booking.";
}

$check_review_stmt->close();

// If there are errors, redirect back to the review form
if (!empty($errors)) {
    $_SESSION['error_message'] = implode("<br>", $errors);
    header("Location: user/add_review.php?sitter_id=" . $sitter_id . "&booking_id=" . $booking_id);
    exit();
}

// If no errors, insert review
$insert_sql = "INSERT INTO reviews (userID, sitterID, bookingID, rating, review, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
$insert_stmt = $conn->prepare($insert_sql);
$insert_stmt->bind_param("iiiis", $user_id, $sitter_id, $booking_id, $rating, $review);

if ($insert_stmt->execute()) {
    $_SESSION['success_message'] = "Your review has been submitted successfully.";
    header("Location: user/bookings.php");
} else {
    $_SESSION['error_message'] = "Error submitting review: " . $conn->error;
    header("Location: user/add_review.php?sitter_id=" . $sitter_id . "&booking_id=" . $booking_id);
}

$insert_stmt->close();
$conn->close();
?>