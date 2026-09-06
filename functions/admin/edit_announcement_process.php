<?php 
require_once __DIR__ . "/../../includes/conn.php";
session_start();

if(isset($_POST['title'], $_POST['content'], $_POST['announcement_id'])) {
    // store inputs in variables
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    $announcement_id = (int) $_POST['announcement_id'];

    // update query
    $update_sql = "UPDATE announcements SET title = ?, content = ? WHERE id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("ssi", $title, $content, $announcement_id);

    // success/fail messages
    if($update_stmt->execute()) {
        // ========== AUDIT TRAIL LOGGING ==========
        $user_id = $_SESSION['user_id'] ?? $_SESSION['id'] ?? 0;
        $action = "Updated announcement ID $announcement_id: " . $title;

        $audit_sql = "INSERT INTO audit_trail (user_id, action) VALUES (?, ?)";
        $audit_stmt = $conn->prepare($audit_sql);
        $audit_stmt->bind_param("is", $user_id, $action);
        $audit_stmt->execute();

        $_SESSION['success'] = "Announcement Updated";
    } else {
        $_SESSION['fail'] = "Update Failed " . $update_stmt->error;
    }

    header("Location: ../../admin/announcements.php");
    exit();
} 
?>