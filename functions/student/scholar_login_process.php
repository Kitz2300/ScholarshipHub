<?php 
require_once __DIR__ . '/../../includes/conn.php';
session_start();

if(isset($_POST['username']) && isset($_POST['password'])) {
    // store username and passwor in variables
    $username = $_POST['username'];
    $password = $_POST['password'];

    // query to search for scholar with matching username and password
    $scholar_sql = "SELECT id, password FROM scholar_info WHERE username = ?";
    $stmt = $conn->prepare($scholar_sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if($row=$result->fetch_assoc()) {
        // verify password
        if(password_verify($password, $row['password'])) {
            // Login successful, store user id and role in session variable
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['role'] = "student";
            header("Location: ../../student/profile.php");
            exit();
        }
    } 

    // Login failed
    $_SESSION['fail'] = "Invalid username or password, please try again";
    header("Location: ../../student/login.php");
    exit();
}
?>