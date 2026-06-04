<?php 
require_once __DIR__ . '/../includes/conn.php';

if(isset($_POST['username']) && isset($_POST['password'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $admin_sql = "SELECT * FROM scholarship_users WHERE username = ? AND password = ? AND role = 'admin'";
    $stmt = $conn->prepare($admin_sql);
    $stmt->bind_param("ss", $username, $password);
    $stmt->execute();
    $result = $stmt->get_result();

    if($result->num_rows > 0) {
        // Login successful
        header("Location: ../admin/dashboard.php");
        exit();
    } else {
        // Login faileds
        echo "Invalid username or password.";
        header("Location: ../admin/login.php");
        exit();
    }
}
?>