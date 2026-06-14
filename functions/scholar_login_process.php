<?php 
require_once __DIR__ . '/../includes/conn.php';
session_start();

if(isset($_POST['username']) && isset($_POST['password'])) {
    // store username and passwor in variables
    $username = $_POST['username'];
    $password = $_POST['password'];

    // query to search for scholar with matching username and password
    $scholar_sql = "SELECT * FROM scholar_info WHERE username = ? AND password = ?";
    $stmt = $conn->prepare($scholar_sql);
    $stmt->bind_param("ss", $username, $password);
    $stmt->execute();
    $result = $stmt->get_result();

    // if a matching scholar is found, store username in session variable and redirect to scholar dashboard
    if($result->num_rows > 0) {
        // Login successful
        $_SESSION['username'] = $username;
        header("Location: ../student/dashboard.php");
        exit();
    } else {
        // Login failed
        echo "<script>
        alert('Invalid username or password. Please try again.'); 
        window.location.href = '../student/login.php';
        </script>";
    }
}
?>