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

// Process delete pet request
if (isset($_POST['delete_pet']) && isset($_POST['pet_id'])) {
    $pet_id = $_POST['pet_id'];
    
    // Check if pet belongs to user
    $check_sql = "SELECT * FROM pet_profile WHERE petID = ? AND userID = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ii", $pet_id, $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        // Check if pet has active bookings
        $booking_check_sql = "SELECT * FROM booking WHERE petID = ? AND status IN ('Pending', 'Confirmed')";
        $booking_check_stmt = $conn->prepare($booking_check_sql);
        $booking_check_stmt->bind_param("i", $pet_id);
        $booking_check_stmt->execute();
        $booking_check_result = $booking_check_stmt->get_result();
        
        if ($booking_check_result->num_rows > 0) {
            $_SESSION['error_message'] = "Cannot delete pet with active bookings.";
        } else {
            // Delete pet
            $delete_sql = "DELETE FROM pet_profile WHERE petID = ?";
            $delete_stmt = $conn->prepare($delete_sql);
            $delete_stmt->bind_param("i", $pet_id);
            
            if ($delete_stmt->execute()) {
                $_SESSION['success_message'] = "Pet deleted successfully.";
            } else {
                $_SESSION['error_message'] = "Failed to delete pet: " . $conn->error;
            }
            
            $delete_stmt->close();
        }
        
        $booking_check_stmt->close();
    } else {
        $_SESSION['error_message'] = "Pet not found or does not belong to you.";
    }
    
    $check_stmt->close();
    
    // Redirect to refresh page
    header("Location: pets.php");
    exit();
}

// Get all pets for this owner
$pets_sql = "SELECT p.*, 
             (SELECT AVG(pr.rating) FROM pet_ranking pr WHERE pr.petID = p.petID) as avg_rating,
             (SELECT COUNT(pr.rankingID) FROM pet_ranking pr WHERE pr.petID = p.petID) as review_count
             FROM pet_profile p 
             WHERE p.userID = ? 
             ORDER BY p.petName ASC";
$pets_stmt = $conn->prepare($pets_sql);
$pets_stmt->bind_param("i", $user_id);
$pets_stmt->execute();
$pets_result = $pets_stmt->get_result();
$pets = [];
while ($row = $pets_result->fetch_assoc()) {
    $pets[] = $row;
}
$pets_stmt->close();

// Include header
include_once '../includes/header.php';
?>

<!-- Page Header -->
<div class="container-fluid bg-light py-4">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="display-5 mb-2">My Pets</h1>
                <p class="lead">Manage your pet profiles.</p>
            </div>
            <div class="col-md-4 text-md-end">
                <a href="add_pet.php" class="btn btn-primary">
                    <i class="fas fa-plus me-1"></i> Add New Pet
                </a>
                <a href="dashboard.php" class="btn btn-outline-secondary ms-2">
                    <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Pets List -->
<div class="container py-5">
    <?php if (empty($pets)): ?>
        <div class="alert alert-info">
            <h4 class="alert-heading">No Pets Found</h4>
            <p>You haven't added any pets yet. Click the "Add New Pet" button to get started.</p>
        </div>
    <?php else: ?>
        <div class="row">
            <?php foreach ($pets as $pet): ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card pet-profile h-100">
                        <?php if (!empty($pet['image'])): ?>
                            <img src="../assets/images/pets/<?php echo htmlspecialchars($pet['image']); ?>" class="pet-img" alt="<?php echo htmlspecialchars($pet['petName']); ?>">
                        <?php else: ?>
                            <img src="../assets/images/pet-placeholder.jpg" class="pet-img" alt="Pet Placeholder">
                        <?php endif; ?>
                        
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($pet['petName']); ?></h5>
                            
                            <div class="pet-details mb-3">
                                <p class="mb-1"><strong>Type:</strong> <?php echo htmlspecialchars($pet['type']); ?></p>
                                <p class="mb-1"><strong>Breed:</strong> <?php echo htmlspecialchars($pet['breed']); ?></p>
                                <p class="mb-1"><strong>Age:</strong> <?php echo $pet['age']; ?> years</p>
                                <p class="mb-1"><strong>Sex:</strong> <?php echo $pet['sex']; ?></p>
                                <p class="mb-0"><strong>Color:</strong> <?php echo htmlspecialchars($pet['color']); ?></p>
                            </div>
                            
                            <?php if ($pet['avg_rating']): ?>
                                <div class="rating mb-3">
                                    <p class="mb-1"><strong>Pet Rating:</strong></p>
                                    <?php
                                    $avg_rating = round($pet['avg_rating'], 1);
                                    $full_stars = floor($avg_rating);
                                    $half_star = $avg_rating - $full_stars >= 0.5;
                                    
                                    for ($i = 1; $i <= 5; $i++) {
                                        if ($i <= $full_stars) {
                                            echo '<i class="fas fa-star"></i>';
                                        } elseif ($i == $full_stars + 1 && $half_star) {
                                            echo '<i class="fas fa-star-half-alt"></i>';
                                        } else {
                                            echo '<i class="far fa-star"></i>';
                                        }
                                    }
                                    ?>
                                    <span class="ms-1"><?php echo $avg_rating; ?> (<?php echo $pet['review_count']; ?> ratings)</span>
                                </div>
                            <?php endif; ?>
                            
                            <div class="d-flex justify-content-between">
                                <a href="edit_pet.php?id=<?php echo $pet['petID']; ?>" class="btn btn-sm btn-primary">
                                    <i class="fas fa-edit me-1"></i> Edit
                                </a>
                                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" onsubmit="return confirm('Are you sure you want to delete this pet?');">
                                    <input type="hidden" name="pet_id" value="<?php echo $pet['petID']; ?>">
                                    <button type="submit" name="delete_pet" class="btn btn-sm btn-danger">
                                        <i class="fas fa-trash-alt me-1"></i> Delete
                                    </button>
                                </form>
                            </div>
                        </div>
                        
                        <div class="card-footer">
                            <a href="pet_details.php?id=<?php echo $pet['petID']; ?>" class="btn btn-outline-secondary btn-sm w-100">
                                <i class="fas fa-info-circle me-1"></i> Full Details
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php
// Include footer
include_once '../includes/footer.php';

// Close database connection
$conn->close();
?>