<?php 
require_once __DIR__ . '/../../includes/conn.php';
session_start();

if (isset($_POST['username']) && isset($_POST['password'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $admin_sql = "SELECT * FROM scholarship_users WHERE username = ? AND role = 'admin'";
    $stmt = $conn->prepare($admin_sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        if(password_verify($password, $row['password'])) {
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['role'] = 'admin';

            
            // ========== AUDIT TRAIL LOGGING ==========
            $user_id = $row['id'];
            $action = "Admin user logged in: " . $row['username'];
            
            $audit_sql = "INSERT INTO audit_trail (user_id, action) VALUES (?, ?)";
            $audit_stmt = $conn->prepare($audit_sql);
            $audit_stmt->bind_param("is", $user_id, $action);
            $audit_stmt->execute();
            
            header("Location: ../../admin/dashboard.php");
            exit();
        }
    }

    // Login failed
    $_SESSION['fail'] = "Invalid username or password, please try again";
    header("Location: ../../admin/login.php");
    exit();

}
?>