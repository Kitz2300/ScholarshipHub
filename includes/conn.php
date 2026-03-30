<?php 
// Database connection parameters
$username = "root";
$password = "";
$servername = "localhost";
$dbname = "scholarship_hub";

// Create connection to MySQL database
try {
    $conn = new mysqli($servername, $username, $password, $dbname);
} catch (Exception $e) {
    die("Connection failed: " . $e->getMessage());
}

?>