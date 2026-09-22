<?php 
// Database connection parameters
$servername = getenv('MYSQLHOST') ?: "localhost";
$username = getenv('MYSQLUSER') ?: "root";
$password = getenv('MYSQLPASSWORD') ?: "";
$dbname = getenv('MYSQLDATABASE') ?: "scholarship_hub";
$port = getenv('MYSQLPORT') ?: "3306";

// Enable strict exception throwing for MySQLi
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($servername, $username, $password, $dbname, $port);
    $conn->set_charset("utf8mb4");
} catch (Exception $e) {
    // Log the actual technical error internally for developer debugging
    error_log("Database Connection Error: " . $e->getMessage());
    
    // Display a clean, generic message to the user
    die("Unable to connect to the server at this time. Please try again later.");
}
?>