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

// Process service deletion
if (isset($_POST['delete_service']) && isset($_POST['service_id'])) {
    $service_id = $_POST['service_id'];
    
    // Check if service belongs to this sitter
    $check_sql = "SELECT * FROM pet_service WHERE serviceID = ? AND userID = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ii", $service_id, $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        // Delete service
        $delete_sql = "DELETE FROM pet_service WHERE serviceID = ?";
        $delete_stmt = $conn->prepare($delete_sql);
        $delete_stmt->bind_param("i", $service_id);
        
        if ($delete_stmt->execute()) {
            $_SESSION['success_message'] = "Service deleted successfully.";
        } else {
            $_SESSION['error_message'] = "Failed to delete service: " . $conn->error;
        }
        
        $delete_stmt->close();
    } else {
        $_SESSION['error_message'] = "Service not found or does not belong to you.";
    }
    
    $check_stmt->close();
    
    // Redirect to refresh page
    header("Location: services.php");
    exit();
}

// Get all services for this sitter
$services_sql = "SELECT * FROM pet_service WHERE userID = ? ORDER BY name ASC";
$services_stmt = $conn->prepare($services_sql);
$services_stmt->bind_param("i", $user_id);
$services_stmt->execute();
$services_result = $services_stmt->get_result();
$services = [];
while ($row = $services_result->fetch_assoc()) {
    $services[] = $row;
}
$services_stmt->close();

// Include header
include_once '../includes/header.php';
?>

<!-- Page Header -->
<div class="container-fluid bg-light py-4">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="display-5 mb-2">My Services</h1>
                <p class="lead">Create and manage your pet sitting services.</p>
            </div>
            <div class="col-md-4 text-md-end">
                <a href="add_service.php" class="btn btn-primary">
                    <i class="fas fa-plus me-1"></i> Add New Service
                </a>
                <a href="dashboard.php" class="btn btn-outline-secondary ms-2">
                    <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Services List -->
<div class="container py-5">
    <?php if (empty($services)): ?>
        <div class="alert alert-info">
            <h4 class="alert-heading">No Services Found</h4>
            <p>You haven't added any services yet. Click the "Add New Service" button to get started.</p>
        </div>
    <?php else: ?>
        <div class="row">
            <?php foreach ($services as $service): ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card h-100">
                        <?php if (!empty($service['image'])): ?>
                            <img src="../assets/images/services/<?php echo htmlspecialchars($service['image']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($service['name']); ?>" style="height: 200px; object-fit: cover;">
                        <?php else: ?>
                            <img src="../assets/images/service-placeholder.jpg" class="card-img-top" alt="Service Placeholder" style="height: 200px; object-fit: cover;">
                        <?php endif; ?>
                        
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($service['name']); ?></h5>
                            <span class="badge bg-primary mb-2"><?php echo htmlspecialchars($service['type']); ?></span>
                            <p class="card-text mb-3"><?php echo nl2br(htmlspecialchars(substr($service['description'], 0, 150) . (strlen($service['description']) > 150 ? '...' : ''))); ?></p>
                            <p class="price mb-3">$<?php echo number_format($service['price'], 2); ?></p>
                            
                            <div class="d-flex justify-content-between">
                                <a href="edit_service.php?id=<?php echo $service['serviceID']; ?>" class="btn btn-sm btn-primary">
                                    <i class="fas fa-edit me-1"></i> Edit
                                </a>
                                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" onsubmit="return confirm('Are you sure you want to delete this service?');">
                                    <input type="hidden" name="service_id" value="<?php echo $service['serviceID']; ?>">
                                    <button type="submit" name="delete_service" class="btn btn-sm btn-danger">
                                        <i class="fas fa-trash-alt me-1"></i> Delete
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Service Types Guide -->
<div class="container mb-5">
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Service Types Guide</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4 mb-4">
                    <div class="d-flex align-items-start">
                        <i class="fas fa-home fa-2x text-primary me-3 mt-1"></i>
                        <div>
                            <h5>Pet Sitting</h5>
                            <p class="text-muted">Caring for pets in the owner's home. This may include feeding, walking, playing, and providing companionship.</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4 mb-4">
                    <div class="d-flex align-items-start">
                        <i class="fas fa-walking fa-2x text-success me-3 mt-1"></i>
                        <div>
                            <h5>Dog Walking</h5>
                            <p class="text-muted">Taking dogs for walks of varying lengths and intensities based on the dog's needs and owner's preferences.</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4 mb-4">
                    <div class="d-flex align-items-start">
                        <i class="fas fa-house-user fa-2x text-info me-3 mt-1"></i>
                        <div>
                            <h5>Pet Boarding</h5>
                            <p class="text-muted">Caring for pets in your own home. This service is ideal for owners who don't want their pets to stay alone.</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4 mb-4">
                    <div class="d-flex align-items-start">
                        <i class="fas fa-cut fa-2x text-warning me-3 mt-1"></i>
                        <div>
                            <h5>Pet Grooming</h5>
                            <p class="text-muted">Basic grooming services such as bathing, brushing, nail trimming, and ear cleaning.</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4 mb-4">
                    <div class="d-flex align-items-start">
                        <i class="fas fa-graduation-cap fa-2x text-danger me-3 mt-1"></i>
                        <div>
                            <h5>Pet Training</h5>
                            <p class="text-muted">Basic obedience training or specific behavioral training for pets.</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4 mb-4">
                    <div class="d-flex align-items-start">
                        <i class="fas fa-pills fa-2x text-secondary me-3 mt-1"></i>
                        <div>
                            <h5>Medication Administration</h5>
                            <p class="text-muted">Administering medications to pets as prescribed by veterinarians.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Pricing Tips -->
<div class="container mb-5">
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Pricing Tips</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h6>Factors to Consider When Setting Prices</h6>
                    <ul>
                        <li>Your experience and qualifications</li>
                        <li>Local market rates for similar services</li>
                        <li>Service duration and complexity</li>
                        <li>Number of pets being cared for</li>
                        <li>Travel distance to client's location</li>
                        <li>Special skills or certifications you offer</li>
                    </ul>
                </div>
                
                <div class="col-md-6">
                    <h6>Average Price Ranges in Colombo</h6>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>Service Type</th>
                                    <th>Price Range (Per Hour)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Pet Sitting</td>
                                    <td>$15 - $25</td>
                                </tr>
                                <tr>
                                    <td>Dog Walking</td>
                                    <td>$10 - $20</td>
                                </tr>
                                <tr>
                                    <td>Pet Boarding</td>
                                    <td>$25 - $40</td>
                                </tr>
                                <tr>
                                    <td>Pet Grooming</td>
                                    <td>$30 - $50</td>
                                </tr>
                                <tr>
                                    <td>Pet Training</td>
                                    <td>$35 - $60</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <div class="alert alert-info mt-3">
                <i class="fas fa-info-circle me-2"></i> 
                <strong>Tip:</strong> Consider offering package deals for regular clients or for multi-hour bookings to increase your competitiveness.
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