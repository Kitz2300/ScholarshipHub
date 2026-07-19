<?php 
require_once __DIR__ . '/../includes/conn.php';
session_start();

if(isset($_POST['username']) && isset($_POST['password'])) {
    // store username and passwor in variables
    $username = $_POST['username'];
    $password = $_POST['password'];

    // query to search for admin with matching username and password
    $admin_sql = "SELECT * FROM scholarship_users WHERE username = ? AND password = ? AND role = 'admin'";
    $stmt = $conn->prepare($admin_sql);
    $stmt->bind_param("ss", $username, $password);
    $stmt->execute();
    $result = $stmt->get_result();


     // if a matching admin is found, store username and role in session variables and redirect to admin dashboard
    if($row=$result->fetch_assoc()) {
        // Login successful
        $_SESSION['user_id'] = $row['id'];
        $_SESSION['role'] = 'admin';
        header("Location: ../admin/dashboard.php");
        exit();
    } else {
        // Login failed
        echo "<script>
        alert('Invalid username or password. Please try again.'); 
        window.location.href = '../admin/login.php';
        </script>";
    }
}
?>