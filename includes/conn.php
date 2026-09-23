<?php 
// Database connection parameters (checking $_ENV, $_SERVER, and getenv)
$servername = $_ENV['MYSQLHOST']     ?? $_SERVER['MYSQLHOST']     ?? getenv('MYSQLHOST')     ?: "localhost";
$username   = $_ENV['MYSQLUSER']     ?? $_SERVER['MYSQLUSER']     ?? getenv('MYSQLUSER')     ?: "root";
$password   = $_ENV['MYSQLPASSWORD'] ?? $_SERVER['MYSQLPASSWORD'] ?? getenv('MYSQLPASSWORD') ?: "";
$dbname     = $_ENV['MYSQLDATABASE'] ?? $_SERVER['MYSQLDATABASE'] ?? getenv('MYSQLDATABASE') ?: "railway";
$port       = $_ENV['MYSQLPORT']     ?? $_SERVER['MYSQLPORT']     ?? getenv('MYSQLPORT')     ?: 3306;

// Enable strict exception throwing for MySQLi
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($servername, $username, $password, $dbname, (int)$port);
    $conn->set_charset("utf8mb4");
} catch (Exception $e) {
    error_log("Database Connection Error: " . $e->getMessage());
    die("Unable to connect to the server at this time. Please try again later.");
}
?>