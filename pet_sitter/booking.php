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

// Process booking update if requested
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_status'])) {
    $booking_id = $_POST['booking_id'];
    $new_status = $_POST['new_status'];
    
    // Check if booking belongs to this sitter
    $check_sql = "SELECT * FROM booking WHERE bookingID = ? AND sitterID = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ii", $booking_id, $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        // Update booking status
        $update_sql = "UPDATE booking SET status = ? WHERE bookingID = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("si", $new_status, $booking_id);
        
        if ($update_stmt->execute()) {
            $_SESSION['success_message'] = "Booking status updated successfully.";
        } else {
            $_SESSION['error_message'] = "Failed to update booking status: " . $conn->error;
        }
        
        $update_stmt->close();
    } else {
        $_SESSION['error_message'] = "Booking not found or does not belong to you.";
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
$sql = "SELECT b.*, po.fullName as ownerName, po.contact as ownerContact, p.petName, p.type as petType, p.breed as petBreed, p.age as petAge
        FROM booking b 
        JOIN pet_owner po ON b.userID = po.userID
        JOIN pet_profile p ON b.petID = p.petID
        WHERE b.sitterID = ?";

// Add status filter if specified
if ($status_filter === 'active') {
    $sql .= " AND b.status IN ('Pending', 'Confirmed')";
} elseif ($status_filter === 'completed') {
    $sql .= " AND b.status = 'Completed'";
} elseif ($status_filter === 'cancelled') {
    $sql .= " AND b.status = 'Cancelled'";
} elseif ($status_filter === 'pending') {
    $sql .= " AND b.status = 'Pending'";
} elseif ($status_filter === 'confirmed') {
    $sql .= " AND b.status = 'Confirmed'";
} elseif (!empty($status_filter)) {
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
    case 'pet_name':
        $sql .= " ORDER BY p.petName ASC";
        break;
    case 'owner_name':
        $sql .= " ORDER BY po.fullName ASC";
        break;
    case 'newest':
    default:
        $sql .= " ORDER BY b.created_at DESC";
        break;
}

// Prepare and execute the query
$stmt = $conn->prepare($sql);

if ($status_filter === 'active' || $status_filter === 'completed' || $status_filter === 'cancelled' || $status_filter === 'pending' || $status_filter === 'confirmed' || empty($status_filter)) {
    $stmt->bind_param("i", $user_id);
} else {
    $stmt->bind_param("is", $user_id, $status_filter);
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
                <a href="dashboard.php" class="btn btn-outline-secondary">
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
                        <option value="active" <?php echo ($status_filter === 'active') ? 'selected' : ''; ?>>Active Bookings (Pending & Confirmed)</option>
                        <option value="pending" <?php echo ($status_filter === 'pending') ? 'selected' : ''; ?>>Pending Bookings</option>
                        <option value="confirmed" <?php echo ($status_filter === 'confirmed') ? 'selected' : ''; ?>>Confirmed Bookings</option>
                        <option value="completed" <?php echo ($status_filter === 'completed') ? 'selected' : ''; ?>>Completed Bookings</option>
                        <option value="cancelled" <?php echo ($status_filter === 'cancelled') ? 'selected' : ''; ?>>Cancelled Bookings</option>
                    </select>
                </div>
                
                <div class="col-md-5">
                    <label for="sort_by" class="form-label">Sort By</label>
                    <select class="form-select" id="sort_by" name="sort_by">
                        <option value="newest" <?php echo ($sort_by === 'newest') ? 'selected' : ''; ?>>Newest First</option>
                        <option value="oldest" <?php echo ($sort_by === 'oldest') ? 'selected' : ''; ?>>Oldest First</option>
                        <option value="upcoming" <?php echo ($sort_by === 'upcoming') ? 'selected' : ''; ?>>Upcoming Dates</option>
                        <option value="pet_name" <?php echo ($sort_by === 'pet_name') ? 'selected' : ''; ?>>Pet Name</option>
                        <option value="owner_name" <?php echo ($sort_by === 'owner_name') ? 'selected' : ''; ?>>Owner Name</option>
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
                                <th>Owner</th>
                                <th>Dates</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bookings as $booking): ?>
                                <tr>
                                    <td><?php echo $booking['bookingID']; ?></td>
                                    <td>
                                        <div><strong><?php echo htmlspecialchars($booking['petName']); ?></strong></div>
                                        <small><?php echo htmlspecialchars($booking['petType']); ?>, <?php echo htmlspecialchars($booking['petBreed']); ?>, <?php echo $booking['petAge']; ?> years</small>
                                    </td>
                                    <td>
                                        <div><?php echo htmlspecialchars($booking['ownerName']); ?></div>
                                        <small><?php echo htmlspecialchars($booking['ownerContact']); ?></small>
                                    </td>
                                    <td>
                                        <small>
                                            <div><i class="fas fa-calendar-check me-1"></i> <?php echo date('M d, Y', strtotime($booking['checkInDate'])); ?> at <?php echo date('h:i A', strtotime($booking['checkInTime'])); ?></div>
                                            <div><i class="fas fa-calendar-times me-1"></i> <?php echo date('M d, Y', strtotime($booking['checkOutDate'])); ?> at <?php echo date('h:i A', strtotime($booking['checkOutTime'])); ?></div>
                                            <div class="text-muted mt-1"><?php 
                                                // Calculate duration
                                                $checkIn = new DateTime($booking['checkInDate'] . ' ' . $booking['checkInTime']);
                                                $checkOut = new DateTime($booking['checkOutDate'] . ' ' . $booking['checkOutTime']);
                                                $interval = $checkIn->diff($checkOut);
                                                
                                                $duration = '';
                                                if ($interval->days > 0) {
                                                    $duration .= $interval->days . ' day' . ($interval->days > 1 ? 's' : '') . ' ';
                                                }
                                                
                                                if ($interval->h > 0) {
                                                    $duration .= $interval->h . ' hour' . ($interval->h > 1 ? 's' : '') . ' ';
                                                }
                                                
                                                if (empty($duration) && $interval->i > 0) {
                                                    $duration .= $interval->i . ' minute' . ($interval->i > 1 ? 's' : '') . ' ';
                                                }
                                                
                                                echo trim($duration);
                                            ?></div>
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
                                        <div class="btn-group">
                                            <a href="booking_details.php?id=<?php echo $booking['bookingID']; ?>" class="btn btn-sm btn-outline-primary" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            
                                            <?php if ($booking['status'] === 'Pending'): ?>
                                                <button type="button" class="btn btn-sm btn-outline-success" title="Accept Booking" data-bs-toggle="modal" data-bs-target="#confirmModal<?php echo $booking['bookingID']; ?>">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                
                                                <button type="button" class="btn btn-sm btn-outline-danger" title="Decline Booking" data-bs-toggle="modal" data-bs-target="#declineModal<?php echo $booking['bookingID']; ?>">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                                
                                                <!-- Confirm Modal -->
                                                <div class="modal fade" id="confirmModal<?php echo $booking['bookingID']; ?>" tabindex="-1" aria-labelledby="confirmModalLabel<?php echo $booking['bookingID']; ?>" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title" id="confirmModalLabel<?php echo $booking['bookingID']; ?>">Confirm Booking</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p>Are you sure you want to confirm this booking?</p>
                                                                <p>Booking #<?php echo $booking['bookingID']; ?> for <?php echo htmlspecialchars($booking['petName']); ?> (<?php echo htmlspecialchars($booking['petType']); ?>)</p>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                                                                    <input type="hidden" name="booking_id" value="<?php echo $booking['bookingID']; ?>">
                                                                    <input type="hidden" name="new_status" value="Confirmed">
                                                                    <button type="submit" name="update_status" class="btn btn-success">Confirm Booking</button>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <!-- Decline Modal -->
                                                <div class="modal fade" id="declineModal<?php echo $booking['bookingID']; ?>" tabindex="-1" aria-labelledby="declineModalLabel<?php echo $booking['bookingID']; ?>" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title" id="declineModalLabel<?php echo $booking['bookingID']; ?>">Decline Booking</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p>Are you sure you want to decline this booking?</p>
                                                                <p>Booking #<?php echo $booking['bookingID']; ?> for <?php echo htmlspecialchars($booking['petName']); ?> (<?php echo htmlspecialchars($booking['petType']); ?>)</p>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                                                                    <input type="hidden" name="booking_id" value="<?php echo $booking['bookingID']; ?>">
                                                                    <input type="hidden" name="new_status" value="Cancelled">
                                                                    <button type="submit" name="update_status" class="btn btn-danger">Decline Booking</button>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if ($booking['status'] === 'Confirmed' && strtotime($booking['checkOutDate']) <= time()): ?>
                                                <button type="button" class="btn btn-sm btn-outline-info" title="Mark as Completed" data-bs-toggle="modal" data-bs-target="#completeModal<?php echo $booking['bookingID']; ?>">
                                                    <i class="fas fa-check-circle"></i>
                                                </button>
                                                
                                                <!-- Complete Modal -->
                                                <div class="modal fade" id="completeModal<?php echo $booking['bookingID']; ?>" tabindex="-1" aria-labelledby="completeModalLabel<?php echo $booking['bookingID']; ?>" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title" id="completeModalLabel<?php echo $booking['bookingID']; ?>">Complete Booking</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p>Are you sure you want to mark this booking as completed?</p>
                                                                <p>Booking #<?php echo $booking['bookingID']; ?> for <?php echo htmlspecialchars($booking['petName']); ?> (<?php echo htmlspecialchars($booking['petType']); ?>)</p>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                                                                    <input type="hidden" name="booking_id" value="<?php echo $booking['bookingID']; ?>">
                                                                    <input type="hidden" name="new_status" value="Completed">
                                                                    <button type="submit" name="update_status" class="btn btn-info">Mark as Completed</button>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if ($booking['status'] === 'Completed'): ?>
                                                <a href="add_pet_ranking.php?booking_id=<?php echo $booking['bookingID']; ?>" class="btn btn-sm btn-outline-warning" title="Rank Pet">
                                                    <i class="fas fa-star"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
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

<!-- Booking Status Guide -->
<div class="container mb-5">
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Booking Status Guide</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3 mb-3">
                    <div class="d-flex align-items-center">
                        <span class="badge bg-warning me-2 p-2">Pending</span>
                        <div>
                            <p class="mb-0">New bookings awaiting your confirmation.</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 mb-3">
                    <div class="d-flex align-items-center">
                        <span class="badge bg-success me-2 p-2">Confirmed</span>
                        <div>
                            <p class="mb-0">Bookings you've accepted and scheduled.</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 mb-3">
                    <div class="d-flex align-items-center">
                        <span class="badge bg-info me-2 p-2">Completed</span>
                        <div>
                            <p class="mb-0">Bookings you've successfully completed.</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 mb-3">
                    <div class="d-flex align-items-center">
                        <span class="badge bg-danger me-2 p-2">Cancelled</span>
                        <div>
                            <p class="mb-0">Bookings that were declined or cancelled.</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="alert alert-info mt-3 mb-0">
                <i class="fas fa-info-circle me-2"></i> 
                <strong>Tip:</strong> Respond to pending bookings promptly to maintain a good reputation with pet owners.
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