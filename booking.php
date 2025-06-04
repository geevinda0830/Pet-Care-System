<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in as pet owner
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'pet_owner') {
    $_SESSION['error_message'] = "You must be logged in as a pet owner to book a pet sitter.";
    header("Location: login.php");
    exit();
}

// Include database connection
require_once 'config/db_connect.php';

// Check if sitter_id is provided
if (!isset($_GET['sitter_id']) || empty($_GET['sitter_id'])) {
    $_SESSION['error_message'] = "Invalid pet sitter.";
    header("Location: pet_sitters.php");
    exit();
}

$sitter_id = $_GET['sitter_id'];

// Get pet sitter details
$sitter_sql = "SELECT * FROM pet_sitter WHERE userID = ?";
$sitter_stmt = $conn->prepare($sitter_sql);
$sitter_stmt->bind_param("i", $sitter_id);
$sitter_stmt->execute();
$sitter_result = $sitter_stmt->get_result();

if ($sitter_result->num_rows === 0) {
    $_SESSION['error_message'] = "Pet sitter not found.";
    header("Location: pet_sitters.php");
    exit();
}

$sitter = $sitter_result->fetch_assoc();
$sitter_stmt->close();

// Get pet owner's pets
$pets_sql = "SELECT * FROM pet_profile WHERE userID = ?";
$pets_stmt = $conn->prepare($pets_sql);
$pets_stmt->bind_param("i", $_SESSION['user_id']);
$pets_stmt->execute();
$pets_result = $pets_stmt->get_result();
$pets = [];

while ($row = $pets_result->fetch_assoc()) {
    $pets[] = $row;
}

$pets_stmt->close();

// Process booking form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $pet_id = $_POST['pet_id'];
    $check_in_date = $_POST['check_in_date'];
    $check_out_date = $_POST['check_out_date'];
    $check_in_time = $_POST['check_in_time'];
    $check_out_time = $_POST['check_out_time'];
    $additional_info = $_POST['additional_info'];
    
    // Validate form data
    $errors = array();
    
    if (empty($pet_id)) {
        $errors[] = "Please select a pet.";
    }
    
    if (empty($check_in_date)) {
        $errors[] = "Check-in date is required.";
    }
    
    if (empty($check_out_date)) {
        $errors[] = "Check-out date is required.";
    }
    
    if (empty($check_in_time)) {
        $errors[] = "Check-in time is required.";
    }
    
    if (empty($check_out_time)) {
        $errors[] = "Check-out time is required.";
    }
    
    // Validate dates
    $today = date('Y-m-d');
    if ($check_in_date < $today) {
        $errors[] = "Check-in date cannot be in the past.";
    }
    
    if ($check_out_date < $check_in_date) {
        $errors[] = "Check-out date cannot be before check-in date.";
    }
    
    // Check if pet exists and belongs to user
    $pet_check_sql = "SELECT * FROM pet_profile WHERE petID = ? AND userID = ?";
    $pet_check_stmt = $conn->prepare($pet_check_sql);
    $pet_check_stmt->bind_param("ii", $pet_id, $_SESSION['user_id']);
    $pet_check_stmt->execute();
    $pet_check_result = $pet_check_stmt->get_result();
    
    if ($pet_check_result->num_rows === 0) {
        $errors[] = "Invalid pet selection.";
    }
    
    $pet_check_stmt->close();
    
    // If no errors, proceed with booking
    if (empty($errors)) {
        // Insert booking into database with 'Pending' status (no payment yet)
        $booking_sql = "INSERT INTO booking (checkInDate, checkOutDate, checkInTime, checkOutTime, additionalInformation, status, userID, petID, sitterID) VALUES (?, ?, ?, ?, ?, 'Pending', ?, ?, ?)";
        $booking_stmt = $conn->prepare($booking_sql);
        $booking_stmt->bind_param("sssssiii", $check_in_date, $check_out_date, $check_in_time, $check_out_time, $additional_info, $_SESSION['user_id'], $pet_id, $sitter_id);
        
        if ($booking_stmt->execute()) {
            $booking_id = $booking_stmt->insert_id;
            
            // Set success message explaining the new process
            $_SESSION['success_message'] = "Booking request sent successfully! The pet sitter will review your request. You'll be able to pay once they accept.";
            header("Location: user/bookings.php");
            exit();
        } else {
            $errors[] = "Booking failed: " . $conn->error;
        }
        
        $booking_stmt->close();
    }
}

// Include header
include_once 'includes/header.php';
?>

<div class="container py-5">
    <div class="row">
        <!-- Sitter Information -->
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">Pet Sitter Details</h5>
                    
                    <div class="text-center mb-3">
                        <?php if (!empty($sitter['image'])): ?>
                            <img src="assets/images/pet_sitters/<?php echo htmlspecialchars($sitter['image']); ?>" class="rounded-circle" width="150" height="150" alt="<?php echo htmlspecialchars($sitter['fullName']); ?>">
                        <?php else: ?>
                            <img src="assets/images/sitter-placeholder.jpg" class="rounded-circle" width="150" height="150" alt="Placeholder">
                        <?php endif; ?>
                    </div>
                    
                    <h4 class="text-center mb-3"><?php echo htmlspecialchars($sitter['fullName']); ?></h4>
                    
                    <div class="mb-3">
                        <p><strong>Service:</strong> <?php echo htmlspecialchars($sitter['service']); ?></p>
                        <p><strong>Price:</strong> $<?php echo number_format($sitter['price'], 2); ?> per hour</p>
                        
                        <?php if (!empty($sitter['qualifications'])): ?>
                            <p><strong>Qualifications:</strong> <?php echo htmlspecialchars($sitter['qualifications']); ?></p>
                        <?php endif; ?>
                        
                        <?php if (!empty($sitter['experience'])): ?>
                            <p><strong>Experience:</strong> <?php echo htmlspecialchars($sitter['experience']); ?></p>
                        <?php endif; ?>
                        
                        <?php if (!empty($sitter['specialization'])): ?>
                            <p><strong>Specializes in:</strong> <?php echo htmlspecialchars($sitter['specialization']); ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <a href="sitter_profile.php?id=<?php echo $sitter['userID']; ?>" class="btn btn-outline-primary w-100">View Full Profile</a>
                </div>
            </div>
            
            <!-- New Booking Process Info -->
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title"><i class="fas fa-info-circle me-2"></i>Booking Process</h5>
                    <div class="booking-steps">
                        <div class="step">
                            <div class="step-number">1</div>
                            <div class="step-content">
                                <strong>Send Request</strong>
                                <small>Submit your booking details</small>
                            </div>
                        </div>
                        <div class="step">
                            <div class="step-number">2</div>
                            <div class="step-content">
                                <strong>Sitter Reviews</strong>
                                <small>Pet sitter accepts or declines</small>
                            </div>
                        </div>
                        <div class="step">
                            <div class="step-number">3</div>
                            <div class="step-content">
                                <strong>Payment</strong>
                                <small>Pay only after acceptance</small>
                            </div>
                        </div>
                        <div class="step">
                            <div class="step-number">4</div>
                            <div class="step-content">
                                <strong>Service</strong>
                                <small>Enjoy pet sitting service</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php if (!empty($sitter['latitude']) && !empty($sitter['longitude'])): ?>
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Location</h5>
                        <div id="map-container" class="map-container rounded"></div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Booking Form -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-body">
                    <h3 class="card-title mb-4">Book a Pet Sitter</h3>
                    
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach($errors as $error): ?>
                                    <li><?php echo $error; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Updated info banner -->
                    <div class="alert alert-info">
                        <h6><i class="fas fa-lightbulb me-2"></i>No Payment Required Yet!</h6>
                        <p class="mb-0">Submit your booking request first. You'll only pay after <?php echo htmlspecialchars($sitter['fullName']); ?> accepts your request.</p>
                    </div>
                    
                    <?php if (empty($pets)): ?>
                        <div class="alert alert-warning">
                            <p>You don't have any pets registered yet. Please add a pet first.</p>
                            <a href="user/add_pet.php" class="btn btn-primary mt-2">Add a Pet</a>
                        </div>
                    <?php else: ?>
                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . "?sitter_id=" . $sitter_id); ?>" method="post">
                            <div class="mb-3">
                                <label for="pet_id" class="form-label">Select Pet</label>
                                <select class="form-select" id="pet_id" name="pet_id" required>
                                    <option value="">-- Select a Pet --</option>
                                    <?php foreach ($pets as $pet): ?>
                                        <option value="<?php echo $pet['petID']; ?>"><?php echo htmlspecialchars($pet['petName']); ?> (<?php echo htmlspecialchars($pet['type']); ?>, <?php echo htmlspecialchars($pet['breed']); ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="check_in_date" class="form-label">Check-in Date</label>
                                    <input type="date" class="form-control" id="check_in_date" name="check_in_date" required min="<?php echo date('Y-m-d'); ?>">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="check_out_date" class="form-label">Check-out Date</label>
                                    <input type="date" class="form-control" id="check_out_date" name="check_out_date" required min="<?php echo date('Y-m-d'); ?>">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="check_in_time" class="form-label">Check-in Time</label>
                                    <input type="time" class="form-control" id="check_in_time" name="check_in_time" required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="check_out_time" class="form-label">Check-out Time</label>
                                    <input type="time" class="form-control" id="check_out_time" name="check_out_time" required>
                                </div>
                            </div>
                            
                            <!-- Cost Calculator -->
                            <div class="mb-3">
                                <div class="cost-calculator card bg-light">
                                    <div class="card-body">
                                        <h6><i class="fas fa-calculator me-2"></i>Estimated Cost</h6>
                                        <div class="cost-breakdown">
                                            <div class="cost-item">
                                                <span>Hourly Rate:</span>
                                                <span>$<?php echo number_format($sitter['price'], 2); ?>/hour</span>
                                            </div>
                                            <div class="cost-item">
                                                <span>Duration:</span>
                                                <span id="duration">Select dates to calculate</span>
                                            </div>
                                            <div class="cost-item total">
                                                <span><strong>Estimated Total:</strong></span>
                                                <span id="total-cost"><strong>$0.00</strong></span>
                                            </div>
                                        </div>
                                        <small class="text-muted">Final cost will be calculated when the sitter accepts your booking.</small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="additional_info" class="form-label">Additional Information</label>
                                <textarea class="form-control" id="additional_info" name="additional_info" rows="4" placeholder="Please provide any specific instructions, pet preferences, or medical information that the sitter should know."></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="terms" required>
                                    <label class="form-check-label" for="terms">
                                        I agree to the <a href="terms.php" target="_blank">Terms and Conditions</a>
                                    </label>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-paper-plane me-2"></i>Send Booking Request
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.booking-steps {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.step {
    display: flex;
    align-items: center;
    gap: 12px;
}

.step-number {
    width: 32px;
    height: 32px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 0.9rem;
    flex-shrink: 0;
}

.step-content {
    display: flex;
    flex-direction: column;
}

.step-content strong {
    color: #1e293b;
    font-size: 0.9rem;
}

.step-content small {
    color: #64748b;
    font-size: 0.8rem;
}

.cost-calculator {
    border: 1px solid #e2e8f0;
}

.cost-breakdown {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.cost-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.cost-item.total {
    padding-top: 8px;
    border-top: 1px solid #e2e8f0;
    margin-top: 8px;
}

@media (max-width: 768px) {
    .booking-steps {
        gap: 12px;
    }
    
    .step {
        flex-direction: column;
        text-align: center;
        gap: 8px;
    }
}
</style>

<script>
// Cost calculator
document.addEventListener('DOMContentLoaded', function() {
    const checkInDate = document.getElementById('check_in_date');
    const checkOutDate = document.getElementById('check_out_date');
    const checkInTime = document.getElementById('check_in_time');
    const checkOutTime = document.getElementById('check_out_time');
    const hourlyRate = <?php echo $sitter['price']; ?>;
    
    function calculateCost() {
        if (checkInDate.value && checkOutDate.value && checkInTime.value && checkOutTime.value) {
            const checkIn = new Date(checkInDate.value + 'T' + checkInTime.value);
            const checkOut = new Date(checkOutDate.value + 'T' + checkOutTime.value);
            
            if (checkOut > checkIn) {
                const diffTime = Math.abs(checkOut - checkIn);
                const diffHours = Math.ceil(diffTime / (1000 * 60 * 60));
                const totalCost = diffHours * hourlyRate;
                
                document.getElementById('duration').textContent = diffHours + ' hours';
                document.getElementById('total-cost').innerHTML = '<strong>$' + totalCost.toFixed(2) + '</strong>';
            } else {
                document.getElementById('duration').textContent = 'Invalid date/time';
                document.getElementById('total-cost').innerHTML = '<strong>$0.00</strong>';
            }
        }
    }
    
    checkInDate.addEventListener('change', calculateCost);
    checkOutDate.addEventListener('change', calculateCost);
    checkInTime.addEventListener('change', calculateCost);
    checkOutTime.addEventListener('change', calculateCost);
});
</script>

<?php if (!empty($sitter['latitude']) && !empty($sitter['longitude'])): ?>
<!-- Google Maps Script -->
<script>
    function initMap() {
        const sitterLocation = {
            lat: <?php echo $sitter['latitude']; ?>,
            lng: <?php echo $sitter['longitude']; ?>
        };
        
        const map = new google.maps.Map(document.getElementById("map-container"), {
            zoom: 14,
            center: sitterLocation,
        });
        
        const marker = new google.maps.Marker({
            position: sitterLocation,
            map: map,
            title: "<?php echo htmlspecialchars($sitter['fullName']); ?>"
        });
    }
</script>
<script src="https://maps.googleapis.com/maps/api/js?key=YOUR_API_KEY&callback=initMap" async defer></script>
<?php endif; ?>

<?php
// Include footer
include_once 'includes/footer.php';

// Close database connection
$conn->close();
?>