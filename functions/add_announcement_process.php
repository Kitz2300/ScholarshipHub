<?php 
require_once __DIR__ . "/../includes/conn.php";
session_start();
$user_id = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : "";

if(!empty($user_id) && isset($_POST['title']) && isset($_POST['content']) && isset($_POST['status'])) {    
    $title = $_POST['title'];
    $content = $_POST['content'];
    $status = (int)$_POST['status'];

    $add_sql = "INSERT INTO announcements(title, content, is_active, user_id) VALUES(?,?,?,?)";
    $add_stmt = $conn->prepare($add_sql);
    $add_stmt->bind_param("ssii", $title, $content, $status, $user_id);
    $add_stmt->execute();
    
    header("Location: ../admin/announcements.php?success");
    exit();
} else {
    header("Location: ../admin/announements.php?failed");
    exit();
}
 
?>