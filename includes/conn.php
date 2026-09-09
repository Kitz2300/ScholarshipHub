<?php 
// Database connection parameters
$username = "root";
$password = "";
$servername = "localhost";
$dbname = "scholarship_hub";

// Enable strict exception throwing for MySQLi
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($servername, $username, $password, $dbname);
    $conn->set_charset("utf8mb4");
} catch (Exception $e) {
    // Log the actual technical error internally for developer debugging
    error_log("Database Connection Error: " . $e->getMessage());
    
    // Display a clean, generic message to the user
    die("Unable to connect to the server at this time. Please try again later.");
}
?>