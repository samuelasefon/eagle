<?php
include 'db_connect.php';

header('Content-Type: application/json');

// Ensure no output before JSON response
ob_start();

// Log the incoming request for debugging
error_log("delete_all_data.php: Request received");

// Log database connection status
if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit;
}

try {
    // Reset the auto-increment value after deleting all data
    $sql = "TRUNCATE TABLE login_attempts";

    // Log the SQL query execution
    error_log("Executing query: TRUNCATE TABLE login_attempts");

    if ($conn->query($sql) === TRUE) {
        error_log("All data deleted and auto-increment reset successfully.");
        echo json_encode(['success' => true, 'message' => 'All data deleted and auto-increment reset successfully.']);
    } else {
        error_log("Error truncating table: " . $conn->error);
        throw new Exception("Error truncating table: " . $conn->error);
    }
} catch (Exception $e) {
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();

// End of script
ob_end_flush();
?>