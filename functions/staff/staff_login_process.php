<?php 
require_once __DIR__ . '/../../includes/conn.php';
session_start();

if(isset($_POST['username']) && isset($_POST['password'])) {
    // store username and passwor in variables
    $username = $_POST['username'];
    $password = $_POST['password'];

    // query to search for staff with matching username and password
    $staff_sql = "SELECT * FROM scholarship_users WHERE username = ? AND role = 'staff'";
    $stmt = $conn->prepare($staff_sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();


    // if a matching staff is found, store username and role in session variables and redirect to staff dashboard
    if($row=$result->fetch_assoc()) {
        if(password_verify($password, $row['password'])) {
            // Login successful
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['role'] = 'staff';
            header("Location: ../../admin/dashboard.php");
            exit();
        } else {
            $_SESSION['fail'] = "Invalid password, please try again";
            header("Location: ../../staff/login.php");
            exit();
        }
    } else {
        // Login failed
        $_SESSION['fail'] = "Invalid username or password, please try again";
        header("Location: ../../staff/login.php");
        exit();
    }
}
?>