<?php 
require_once __DIR__ . '/../includes/conn.php';
session_start();

if(isset($_POST['username']) && isset($_POST['password'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $scholar_sql = "SELECT * FROM scholar_info WHERE username = ? AND password = ?";
    $stmt = $conn->prepare($scholar_sql);
    $stmt->bind_param("ss", $username, $password);
    $stmt->execute();
    $result = $stmt->get_result();

    if($result->num_rows > 0) {
        // Login successful
        $_SESSION['username'] = $username;
        header("Location: ../student/dashboard.php");
        exit();
    } else {
        // Login failed
        echo "Invalid username or password.";
        header("Location: ../student/login.php");
        exit();
    }
}
?>