<?php 
// Detect environment: Check if Railway variables are present
$is_railway = getenv('MYSQLHOST') || isset($_ENV['MYSQLHOST']) || isset($_SERVER['MYSQLHOST']);

if ($is_railway) {
    // Railway Live Environment
    $servername = $_ENV['MYSQLHOST']     ?? $_SERVER['MYSQLHOST']     ?? getenv('MYSQLHOST');
    $username   = $_ENV['MYSQLUSER']     ?? $_SERVER['MYSQLUSER']     ?? getenv('MYSQLUSER');
    $password   = $_ENV['MYSQLPASSWORD'] ?? $_SERVER['MYSQLPASSWORD'] ?? getenv('MYSQLPASSWORD');
    $dbname     = $_ENV['MYSQLDATABASE'] ?? $_SERVER['MYSQLDATABASE'] ?? getenv('MYSQLDATABASE') ?: 'railway';
    $port       = $_ENV['MYSQLPORT']     ?? $_SERVER['MYSQLPORT']     ?? getenv('MYSQLPORT')     ?: 3306;
} else {
    // Local XAMPP Environment
    $servername = "localhost";
    $username   = "root";
    $password   = "";
    $dbname     = "scholarship_hub"; // Change to your local DB name if different (e.g., scholarship_db)
    $port       = 3306;
}

// Enable strict exception reporting
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($servername, $username, $password, $dbname, (int)$port);
    $conn->set_charset("utf8mb4");

    // Disable ONLY_FULL_GROUP_BY for session compatibility
    $conn->query("SET SESSION sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))");
} catch (Exception $e) {
    error_log("Database Connection Error: " . $e->getMessage());
    die("Database Connection Error: " . $e->getMessage());
}
?>