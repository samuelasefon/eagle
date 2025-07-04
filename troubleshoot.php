<?php
include 'db_connect.php';

header('Content-Type: text/plain');

// Check database connection
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
} else {
    echo "Database connection successful.\n";
}

// Check if the `login_attempts` table exists
$tableCheckQuery = "SHOW TABLES LIKE 'login_attempts'";
$result = $conn->query($tableCheckQuery);

if ($result && $result->num_rows > 0) {
    echo "Table 'login_attempts' exists.\n";

    // Check the structure of the `login_attempts` table
    $structureQuery = "DESCRIBE login_attempts";
    $structureResult = $conn->query($structureQuery);

    if ($structureResult) {
        echo "Table structure:\n";
        while ($row = $structureResult->fetch_assoc()) {
            echo $row['Field'] . " - " . $row['Type'] . "\n";
        }
    } else {
        echo "Failed to retrieve table structure: " . $conn->error . "\n";
    }
} else {
    echo "Table 'login_attempts' does not exist.\n";
}

// Close the connection
$conn->close();
?>