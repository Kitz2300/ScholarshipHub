<?php 
require_once __DIR__ . "/../includes/conn.php";
session_start();

if(isset($_POST['title'], $_POST['content'], $_POST['status'], $_POST['announcement_id'])) {
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    $status = (int) $_POST['status'];
    $announcement_id = (int) $_POST['announcement_id'];

    $update_sql = "UPDATE announcements SET title = ?, content = ?, is_active = ? WHERE id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("ssii", $title, $content, $status, $announcement_id);

    if($update_stmt->execute()) {

    }

} 
header("Location: ../announcements.php");
exit();
?>