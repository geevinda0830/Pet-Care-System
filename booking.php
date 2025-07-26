<?php
// FILE PATH: /booking.php (Root Directory)

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

// Function to check if a sitter is available during a specific time period
function checkSitterAvailability($conn, $sitter_id, $check_in_date, $check_in_time, $check_out_date, $check_out_time, $exclude_booking_id = null) {
    // Create datetime objects for comparison
    $requested_start = $check_in_date . ' ' . $check_in_time;
    $requested_end = $check_out_date . ' ' . $check_out_time;
    
    // Check for overlapping bookings with status 'Confirmed' or 'Completed'
    $sql = "SELECT bookingID, checkInDate, checkInTime, checkOutDate, checkOutTime 
            FROM booking 
            WHERE sitterID = ? 
            AND status IN ('Confirmed', 'Completed')
            AND (
                (CONCAT(checkInDate, ' ', checkInTime) < ? AND CONCAT(checkOutDate, ' ', checkOutTime) > ?) OR
                (CONCAT(checkInDate, ' ', checkInTime) < ? AND CONCAT(checkOutDate, ' ', checkOutTime) > ?) OR
                (CONCAT(checkInDate, ' ', checkInTime) >= ? AND CONCAT(checkOutDate, ' ', checkOutTime) <= ?)
            )";
    
    $params = [$sitter_id, $requested_end, $requested_start, $requested_start, $requested_end, $requested_start, $requested_end];
    
    // If we're updating an existing booking, exclude it from the check
    if ($exclude_booking_id !== null) {
        $sql .= " AND bookingID != ?";
        $params[] = $exclude_booking_id;
    }
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(str_repeat('s', count($params) - 1) . 'i', ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $conflicts = [];
    while ($row = $result->fetch_assoc()) {
        $conflicts[] = $row;
    }
    
    $stmt->close();
    
    return empty($conflicts) ? ['available' => true] : ['available' => false, 'conflicts' => $conflicts];
}

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
    
    if (!isset($_POST['terms']) || $_POST['terms'] != '1') {
        $errors[] = "You must agree to the terms and conditions.";
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
    
    // Check sitter availability before creating booking
    if (empty($errors)) {
        $availability = checkSitterAvailability($conn, $sitter_id, $check_in_date, $check_in_time, $check_out_date, $check_out_time);
        
        if (!$availability['available']) {
            $errors[] = "Sorry, this pet sitter is not available during the selected time period. Please choose different dates/times or contact the sitter directly.";
        }
    }
    
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
            $errors[] = "Booking failed: " . $booking_stmt->error;
        }
        
        $booking_stmt->close();
    }
}

// Include header
include_once 'includes/header.php';
?>

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

.step-title {
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 4px;
}

.step-description {
    color: #64748b;
    font-size: 0.9rem;
}

.cost-calculator {
    background: linear-gradient(135deg, #f8f9ff 0%, #f1f5f9 100%);
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 20px;
    margin: 20px 0;
}

.cost-breakdown {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-top: 12px;
}

.cost-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid #e5e7eb;
}

.cost-item:last-child {
    border-bottom: none;
    font-weight: 600;
    font-size: 1.1rem;
    color: #1e293b;
}

.sitter-card {
    background: white;
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    border: 1px solid #e5e7eb;
    overflow: hidden;
    position: sticky;
    top: 100px;
}

.sitter-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 24px;
    text-align: center;
}

.sitter-avatar {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    margin: 0 auto 16px;
    border: 4px solid rgba(255, 255, 255, 0.2);
}

.sitter-info {
    padding: 24px;
}

.info-item {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 12px;
    padding: 12px;
    background: #f8fafc;
    border-radius: 8px;
}

.info-icon {
    width: 32px;
    height: 32px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.9rem;
}

.availability-status {
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 600;
    text-align: center;
    margin-top: 16px;
}

.available {
    background: #d1fae5;
    color: #065f46;
}

.unavailable {
    background: #fee2e2;
    color: #991b1b;
}
</style>

<div class="container mt-4">
    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h4 class="mb-0">
                        <i class="fas fa-calendar-plus me-2"></i>
                        Book Pet Sitter: <?php echo htmlspecialchars($sitter['fullName']); ?>
                    </h4>
                </div>
                <div class="card-body">
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <strong>Please correct the following errors:</strong>
                            <ul class="mb-0 mt-2">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <!-- Booking Steps -->
                    <div class="booking-steps mb-4">
                        <div class="step">
                            <div class="step-number">1</div>
                            <div class="step-content">
                                <div class="step-title">Choose Your Pet</div>
                                <div class="step-description">Select which pet needs sitting</div>
                            </div>
                        </div>
                        <div class="step">
                            <div class="step-number">2</div>
                            <div class="step-content">
                                <div class="step-title">Select Dates & Times</div>
                                <div class="step-description">Pick your check-in and check-out schedule</div>
                            </div>
                        </div>
                        <div class="step">
                            <div class="step-number">3</div>
                            <div class="step-content">
                                <div class="step-title">Add Special Instructions</div>
                                <div class="step-description">Provide any special care instructions</div>
                            </div>
                        </div>
                        <div class="step">
                            <div class="step-number">4</div>
                            <div class="step-content">
                                <div class="step-title">Review & Submit</div>
                                <div class="step-description">Confirm details and send request</div>
                            </div>
                        </div>
                    </div>

                    <form method="POST" action="" id="bookingForm">
                        <div class="mb-3">
                            <label for="pet_id" class="form-label">
                                <i class="fas fa-paw me-2"></i>Select Your Pet *
                            </label>
                            <select class="form-select" id="pet_id" name="pet_id" required>
                                <option value="">Choose a pet...</option>
                                <?php foreach ($pets as $pet): ?>
                                    <option value="<?php echo $pet['petID']; ?>" 
                                        <?php echo (isset($_POST['pet_id']) && $_POST['pet_id'] == $pet['petID']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($pet['petName']) . ' (' . htmlspecialchars($pet['type']) . ' - ' . htmlspecialchars($pet['breed']) . ')'; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="check_in_date" class="form-label">
                                    <i class="fas fa-calendar me-2"></i>Check-in Date *
                                </label>
                                <input type="date" class="form-control" id="check_in_date" name="check_in_date" 
                                    value="<?php echo isset($_POST['check_in_date']) ? $_POST['check_in_date'] : ''; ?>" 
                                    min="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="check_out_date" class="form-label">
                                    <i class="fas fa-calendar me-2"></i>Check-out Date *
                                </label>
                                <input type="date" class="form-control" id="check_out_date" name="check_out_date" 
                                    value="<?php echo isset($_POST['check_out_date']) ? $_POST['check_out_date'] : ''; ?>" 
                                    min="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="check_in_time" class="form-label">
                                    <i class="fas fa-clock me-2"></i>Check-in Time *
                                </label>
                                <input type="time" class="form-control" id="check_in_time" name="check_in_time" 
                                    value="<?php echo isset($_POST['check_in_time']) ? $_POST['check_in_time'] : ''; ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="check_out_time" class="form-label">
                                    <i class="fas fa-clock me-2"></i>Check-out Time *
                                </label>
                                <input type="time" class="form-control" id="check_out_time" name="check_out_time" 
                                    value="<?php echo isset($_POST['check_out_time']) ? $_POST['check_out_time'] : ''; ?>" required>
                            </div>
                        </div>

                        <!-- Cost Calculator -->
                        <div class="cost-calculator">
                            <h6><i class="fas fa-calculator me-2"></i>Estimated Cost</h6>
                            <div class="cost-breakdown">
                                <div class="cost-item">
                                    <span>Hourly Rate:</span>
                                    <span>Rs. <?php echo number_format($sitter['price'], 2); ?></span>
                                </div>
                                <div class="cost-item">
                                    <span>Duration:</span>
                                    <span id="duration">Select dates to calculate</span>
                                </div>
                                <div class="cost-item">
                                    <span>Total Estimated Cost:</span>
                                    <span id="totalCost">Rs. 0.00</span>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="additional_info" class="form-label">
                                <i class="fas fa-sticky-note me-2"></i>Special Instructions
                            </label>
                            <textarea class="form-control" id="additional_info" name="additional_info" rows="4" 
                                placeholder="Any special care instructions, feeding schedules, medication requirements, behavioral notes, emergency contacts, or other important information about your pet..."><?php echo isset($_POST['additional_info']) ? htmlspecialchars($_POST['additional_info']) : ''; ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="terms" name="terms" value="1" required>
                                <label class="form-check-label" for="terms">
                                    I agree to the <a href="terms.php" target="_blank">Terms and Conditions</a> and <a href="privacy.php" target="_blank">Privacy Policy</a> *
                                </label>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-paper-plane me-2"></i>Send Booking Request
                            </button>
                            <a href="pet_sitters.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Back to Pet Sitters
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="sitter-card">
                <div class="sitter-header">
                    <div class="sitter-avatar bg-light d-flex align-items-center justify-content-center">
                        <i class="fas fa-user fa-2x text-muted"></i>
                    </div>
                    <h5 class="mb-1"><?php echo htmlspecialchars($sitter['fullName']); ?></h5>
                    <p class="mb-0 opacity-75">Professional Pet Sitter</p>
                </div>
                
                <div class="sitter-info">
                    <div class="info-item">
                        <div class="info-icon">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <div>
                            <strong>Location</strong><br>
                            <span class="text-muted"><?php echo htmlspecialchars($sitter['location']); ?></span>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-icon">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                        <div>
                            <strong>Price</strong><br>
                            <span class="text-muted">Rs. <?php echo number_format($sitter['price'], 2); ?> per hour</span>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-icon">
                            <i class="fas fa-award"></i>
                        </div>
                        <div>
                            <strong>Experience</strong><br>
                            <span class="text-muted"><?php echo htmlspecialchars($sitter['experience']); ?> years</span>
                        </div>
                    </div>
                    
                    <?php if (!empty($sitter['service'])): ?>
                    <div class="info-item">
                        <div class="info-icon">
                            <i class="fas fa-paw"></i>
                        </div>
                        <div>
                            <strong>Services</strong><br>
                            <span class="text-muted"><?php echo htmlspecialchars($sitter['service']); ?></span>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($sitter['description'])): ?>
                    <div class="mt-3">
                        <h6>About</h6>
                        <p class="text-muted small"><?php echo nl2br(htmlspecialchars($sitter['description'])); ?></p>
                    </div>
                    <?php endif; ?>

                    <div class="availability-status available">
                        <i class="fas fa-check-circle me-2"></i>Available for Booking
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Cost calculator functionality
function calculateCost() {
    const checkInDate = document.getElementById('check_in_date').value;
    const checkInTime = document.getElementById('check_in_time').value;
    const checkOutDate = document.getElementById('check_out_date').value;
    const checkOutTime = document.getElementById('check_out_time').value;
    const hourlyRate = <?php echo $sitter['price']; ?>;
    
    if (checkInDate && checkInTime && checkOutDate && checkOutTime) {
        const checkIn = new Date(checkInDate + ' ' + checkInTime);
        const checkOut = new Date(checkOutDate + ' ' + checkOutTime);
        
        if (checkOut > checkIn) {
            const diffMs = checkOut - checkIn;
            const diffHours = diffMs / (1000 * 60 * 60);
            const totalCost = diffHours * hourlyRate;
            
            document.getElementById('duration').textContent = diffHours.toFixed(1) + ' hours';
            document.getElementById('totalCost').textContent = 'Rs. ' + totalCost.toFixed(2);
        } else {
            document.getElementById('duration').textContent = 'Invalid date range';
            document.getElementById('totalCost').textContent = 'Rs. 0.00';
        }
    } else {
        document.getElementById('duration').textContent = 'Select dates to calculate';
        document.getElementById('totalCost').textContent = 'Rs. 0.00';
    }
}

// Auto-update check-out date when check-in date changes
document.getElementById('check_in_date').addEventListener('change', function() {
    const checkInDate = this.value;
    const checkOutDateInput = document.getElementById('check_out_date');
    
    if (checkInDate) {
        checkOutDateInput.min = checkInDate;
        if (checkOutDateInput.value < checkInDate) {
            checkOutDateInput.value = checkInDate;
        }
    }
    calculateCost();
});

// Add event listeners for cost calculation
document.getElementById('check_in_time').addEventListener('change', calculateCost);
document.getElementById('check_out_date').addEventListener('change', calculateCost);
document.getElementById('check_out_time').addEventListener('change', calculateCost);

// Form validation
document.getElementById('bookingForm').addEventListener('submit', function(e) {
    const terms = document.getElementById('terms');
    if (!terms.checked) {
        e.preventDefault();
        alert('Please agree to the terms and conditions to continue.');
        terms.focus();
    }
});
</script>

<?php include_once 'includes/footer.php'; ?>