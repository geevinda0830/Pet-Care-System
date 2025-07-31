<?php
// Minimal refund request test page
// Save as: user/test_minimal_refund.php

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>🧪 Minimal Refund Request Test</h2>";

// Step 1: Test Session
echo "<h3>Step 1: Session Test</h3>";
try {
    session_start();
    echo "<p>✅ Session started</p>";
    
    if (isset($_SESSION['user_id']) && $_SESSION['user_type'] === 'pet_owner') {
        echo "<p>✅ User is logged in as pet_owner</p>";
        echo "<p>👤 User ID: " . $_SESSION['user_id'] . "</p>";
        $customer_id = $_SESSION['user_id'];
        $user_logged_in = true;
    } else {
        echo "<p>⚠️ User not logged in as pet_owner</p>";
        echo "<p>Current session:</p>";
        echo "<pre>" . print_r($_SESSION, true) . "</pre>";
        $customer_id = 1; // Use test ID
        $user_logged_in = false;
        echo "<p>💡 Using test customer ID: $customer_id</p>";
    }
} catch (Exception $e) {
    echo "<p>❌ Session error: " . $e->getMessage() . "</p>";
    exit;
}

// Step 2: Test Database
echo "<h3>Step 2: Database Test</h3>";
try {
    require_once '../config/db_connect.php';
    
    if (isset($conn) && !$conn->connect_error) {
        echo "<p>✅ Database connected</p>";
    } else {
        echo "<p>❌ Database connection failed</p>";
        exit;
    }
} catch (Exception $e) {
    echo "<p>❌ Database error: " . $e->getMessage() . "</p>";
    exit;
}

// Step 3: Test Form Processing
echo "<h3>Step 3: Form Processing Test</h3>";
if (isset($_POST['test_submit'])) {
    echo "<p>📝 Form submitted! Processing...</p>";
    
    $refund_type = $_POST['refund_type'] ?? '';
    $reference_id = intval($_POST['reference_id'] ?? 0);
    $amount = floatval($_POST['amount'] ?? 0);
    $reason = $_POST['reason'] ?? '';
    $notes = $_POST['notes'] ?? '';
    
    echo "<p>📊 Form data:</p>";
    echo "<ul>";
    echo "<li>Type: $refund_type</li>";
    echo "<li>Reference ID: $reference_id</li>";
    echo "<li>Amount: $$amount</li>";
    echo "<li>Reason: $reason</li>";
    echo "<li>Notes: " . strlen($notes) . " characters</li>";
    echo "</ul>";
    
    if ($refund_type && $reference_id > 0 && $amount > 0 && $reason && $notes) {
        // Try to insert into database
        try {
            $insert_sql = "INSERT INTO refunds (refund_type, reference_id, customer_id, amount, reason, customer_notes, status, request_date) VALUES (?, ?, ?, ?, ?, ?, 'Pending', CURRENT_TIMESTAMP)";
            $stmt = $conn->prepare($insert_sql);
            
            if ($stmt) {
                $stmt->bind_param("siidss", $refund_type, $reference_id, $customer_id, $amount, $reason, $notes);
                
                if ($stmt->execute()) {
                    $refund_id = $conn->insert_id;
                    echo "<p>✅ Test refund created successfully! ID: #$refund_id</p>";
                    
                    // Clean up test data
                    $cleanup_sql = "DELETE FROM refunds WHERE refund_id = ?";
                    $cleanup_stmt = $conn->prepare($cleanup_sql);
                    if ($cleanup_stmt) {
                        $cleanup_stmt->bind_param("i", $refund_id);
                        $cleanup_stmt->execute();
                        echo "<p>🧹 Test data cleaned up</p>";
                    }
                } else {
                    echo "<p>❌ Failed to insert: " . $stmt->error . "</p>";
                }
            } else {
                echo "<p>❌ Failed to prepare statement: " . $conn->error . "</p>";
            }
        } catch (Exception $e) {
            echo "<p>❌ Insert error: " . $e->getMessage() . "</p>";
        }
    } else {
        echo "<p>⚠️ Form validation failed - missing required fields</p>";
    }
}

// Step 4: Test Data Retrieval
echo "<h3>Step 4: Data Retrieval Test</h3>";
try {
    // Test orders query
    $orders_sql = "SELECT COUNT(*) as count FROM `order` WHERE userID = ?";
    $orders_stmt = $conn->prepare($orders_sql);
    if ($orders_stmt) {
        $orders_stmt->bind_param("i", $customer_id);
        $orders_stmt->execute();
        $orders_count = $orders_stmt->get_result()->fetch_assoc()['count'];
        echo "<p>📦 Orders found: $orders_count</p>";
    } else {
        echo "<p>❌ Failed to prepare orders query</p>";
    }
    
    // Test bookings query
    $bookings_sql = "SELECT COUNT(*) as count FROM booking WHERE userID = ?";
    $bookings_stmt = $conn->prepare($bookings_sql);
    if ($bookings_stmt) {
        $bookings_stmt->bind_param("i", $customer_id);
        $bookings_stmt->execute();
        $bookings_count = $bookings_stmt->get_result()->fetch_assoc()['count'];
        echo "<p>📅 Bookings found: $bookings_count</p>";
    } else {
        echo "<p>❌ Failed to prepare bookings query</p>";
    }
    
} catch (Exception $e) {
    echo "<p>❌ Data retrieval error: " . $e->getMessage() . "</p>";
}

// Step 5: Simple Test Form
echo "<h3>Step 5: Simple Test Form</h3>";
?>

<form method="POST" style="background: #f8f9fa; padding: 20px; border-radius: 10px; max-width: 500px;">
    <div style="margin-bottom: 15px;">
        <label><b>Refund Type:</b></label><br>
        <select name="refund_type" style="width: 100%; padding: 8px; margin-top: 5px;">
            <option value="">Select type</option>
            <option value="order">Order</option>
            <option value="booking">Booking</option>
        </select>
    </div>
    
    <div style="margin-bottom: 15px;">
        <label><b>Reference ID:</b></label><br>
        <input type="number" name="reference_id" value="1" style="width: 100%; padding: 8px; margin-top: 5px;">
    </div>
    
    <div style="margin-bottom: 15px;">
        <label><b>Amount:</b></label><br>
        <input type="number" name="amount" value="25.00" step="0.01" style="width: 100%; padding: 8px; margin-top: 5px;">
    </div>
    
    <div style="margin-bottom: 15px;">
        <label><b>Reason:</b></label><br>
        <select name="reason" style="width: 100%; padding: 8px; margin-top: 5px;">
            <option value="">Select reason</option>
            <option value="Test Reason">Test Reason</option>
            <option value="Product Issue">Product Issue</option>
        </select>
    </div>
    
    <div style="margin-bottom: 15px;">
        <label><b>Notes:</b></label><br>
        <textarea name="notes" style="width: 100%; padding: 8px; margin-top: 5px; height: 80px;">This is a test refund request.</textarea>
    </div>
    
    <button type="submit" name="test_submit" style="background: #007bff; color: white; padding: 12px 24px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px;">
        Test Submit
    </button>
</form>

<h3>Quick Navigation</h3>
<a href="test_basic.php">Basic Test</a> | 
<a href="test_database.php">Database Test</a> | 
<a href="customer_refund_request.php">Full Refund Page</a> |
<a href="../login.php">Login</a>

<?php
if (isset($conn)) {
    $conn->close();
}
?>