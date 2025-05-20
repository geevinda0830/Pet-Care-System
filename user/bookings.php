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

// Process booking cancellation if requested
if (isset($_POST['cancel_booking']) && isset($_POST['booking_id'])) {
    $booking_id = $_POST['booking_id'];
    
    // Check if booking belongs to user and can be cancelled
    $check_sql = "SELECT * FROM booking WHERE bookingID = ? AND userID = ? AND status IN ('Pending', 'Confirmed')";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ii", $booking_id, $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        // Update booking status to cancelled
        $update_sql = "UPDATE booking SET status = 'Cancelled' WHERE bookingID = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("i", $booking_id);
        
        if ($update_stmt->execute()) {
            $_SESSION['success_message'] = "Booking cancelled successfully.";
        } else {
            $_SESSION['error_message'] = "Failed to cancel booking: " . $conn->error;
        }
        
        $update_stmt->close();
    } else {
        $_SESSION['error_message'] = "Booking not found or cannot be cancelled.";
    }
    
    $check_stmt->close();
    
    // Redirect to refresh page
    header("Location: bookings.php");
    exit();
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'newest';

// Prepare SQL query
$sql = "SELECT b.*, p.petName, ps.fullName as sitterName, ps.service 
        FROM booking b 
        JOIN pet_profile p ON b.petID = p.petID 
        JOIN pet_sitter ps ON b.sitterID = ps.userID 
        WHERE b.userID = ?";

// Add status filter if specified
if (!empty($status_filter)) {
    $sql .= " AND b.status = ?";
}

// Add sorting
switch ($sort_by) {
    case 'oldest':
        $sql .= " ORDER BY b.created_at ASC";
        break;
    case 'upcoming':
        $sql .= " ORDER BY b.checkInDate ASC, b.checkInTime ASC";
        break;
    case 'completed':
        $sql .= " ORDER BY b.checkOutDate DESC, b.checkOutTime DESC";
        break;
    case 'newest':
    default:
        $sql .= " ORDER BY b.created_at DESC";
        break;
}

// Prepare and execute the query
$stmt = $conn->prepare($sql);

if (!empty($status_filter)) {
    $stmt->bind_param("is", $user_id, $status_filter);
} else {
    $stmt->bind_param("i", $user_id);
}

$stmt->execute();
$result = $stmt->get_result();
$bookings = [];

while ($row = $result->fetch_assoc()) {
    $bookings[] = $row;
}

$stmt->close();

// Include header
include_once '../includes/header.php';
?>

<!-- Page Header -->
<div class="container-fluid bg-light py-4">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="display-5 mb-2">My Bookings</h1>
                <p class="lead">View and manage your pet sitting bookings.</p>
            </div>
            <div class="col-md-4 text-md-end">
                <a href="../pet_sitters.php" class="btn btn-primary">
                    <i class="fas fa-plus me-1"></i> New Booking
                </a>
                <a href="dashboard.php" class="btn btn-outline-secondary ms-2">
                    <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Booking Management -->
<div class="container py-5">
    <!-- Filtering and Sorting Options -->
    <div class="card mb-4">
        <div class="card-body">
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="get" class="row g-3">
                <div class="col-md-5">
                    <label for="status" class="form-label">Filter by Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="" <?php echo empty($status_filter) ? 'selected' : ''; ?>>All Bookings</option>
                        <option value="Pending" <?php echo ($status_filter === 'Pending') ? 'selected' : ''; ?>>Pending</option>
                        <option value="Confirmed" <?php echo ($status_filter === 'Confirmed') ? 'selected' : ''; ?>>Confirmed</option>
                        <option value="Completed" <?php echo ($status_filter === 'Completed') ? 'selected' : ''; ?>>Completed</option>
                        <option value="Cancelled" <?php echo ($status_filter === 'Cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
                
                <div class="col-md-5">
                    <label for="sort_by" class="form-label">Sort By</label>
                    <select class="form-select" id="sort_by" name="sort_by">
                        <option value="newest" <?php echo ($sort_by === 'newest') ? 'selected' : ''; ?>>Newest First</option>
                        <option value="oldest" <?php echo ($sort_by === 'oldest') ? 'selected' : ''; ?>>Oldest First</option>
                        <option value="upcoming" <?php echo ($sort_by === 'upcoming') ? 'selected' : ''; ?>>Upcoming Dates</option>
                        <option value="completed" <?php echo ($sort_by === 'completed') ? 'selected' : ''; ?>>Recently Completed</option>
                    </select>
                </div>
                
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Apply</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Bookings List -->
    <?php if (empty($bookings)): ?>
        <div class="alert alert-info">
            <h4 class="alert-heading">No Bookings Found</h4>
            <p>You don't have any bookings<?php echo !empty($status_filter) ? " with status '$status_filter'" : ""; ?>.</p>
            <hr>
            <p class="mb-0">Click the "New Booking" button to book a pet sitter.</p>
        </div>
    <?php else: ?>
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Your Bookings (<?php echo count($bookings); ?>)</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Booking #</th>
                                <th>Pet</th>
                                <th>Pet Sitter</th>
                                <th>Service</th>
                                <th>Dates</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bookings as $booking): ?>
                                <tr>
                                    <td><?php echo $booking['bookingID']; ?></td>
                                    <td><?php echo htmlspecialchars($booking['petName']); ?></td>
                                    <td><?php echo htmlspecialchars($booking['sitterName']); ?></td>
                                    <td><?php echo htmlspecialchars($booking['service']); ?></td>
                                    <td>
                                        <small>
                                            From: <?php echo date('M d, Y', strtotime($booking['checkInDate'])); ?> at 
                                            <?php echo date('h:i A', strtotime($booking['checkInTime'])); ?><br>
                                            To: <?php echo date('M d, Y', strtotime($booking['checkOutDate'])); ?> at 
                                            <?php echo date('h:i A', strtotime($booking['checkOutTime'])); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <?php
                                        $status_class = '';
                                        switch ($booking['status']) {
                                            case 'Pending':
                                                $status_class = 'bg-warning';
                                                break;
                                            case 'Confirmed':
                                                $status_class = 'bg-success';
                                                break;
                                            case 'Cancelled':
                                                $status_class = 'bg-danger';
                                                break;
                                            case 'Completed':
                                                $status_class = 'bg-info';
                                                break;
                                        }
                                        ?>
                                        <span class="badge <?php echo $status_class; ?>"><?php echo $booking['status']; ?></span>
                                    </td>
                                    <td>
                                        <a href="booking_details.php?id=<?php echo $booking['bookingID']; ?>" class="btn btn-sm btn-outline-primary" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        
                                        <?php if ($booking['status'] === 'Pending' || $booking['status'] === 'Confirmed'): ?>
                                            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to cancel this booking?');">
                                                <input type="hidden" name="booking_id" value="<?php echo $booking['bookingID']; ?>">
                                                <button type="submit" name="cancel_booking" class="btn btn-sm btn-outline-danger" title="Cancel Booking">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <?php if ($booking['status'] === 'Completed'): ?>
                                            <a href="add_review.php?sitter_id=<?php echo $booking['sitterID']; ?>&booking_id=<?php echo $booking['bookingID']; ?>" class="btn btn-sm btn-outline-success" title="Leave Review">
                                                <i class="fas fa-star"></i>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php
// Include footer
include_once '../includes/footer.php';

// Close database connection
$conn->close();
?>