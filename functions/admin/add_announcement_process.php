<?php 
require_once __DIR__ . "/../../includes/conn.php";
session_start();

if (isset($_POST['add'])) {
    // Retrieve logged-in user ID
    $user_id = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
    
    // Trim input data
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');

    // Validate user authentication and non-empty form inputs
    if ($user_id > 0 && !empty($title) && !empty($content)) {    

        // Insert announcement record
        $add_sql = "INSERT INTO announcements (title, content, is_active, user_id) VALUES (?, ?, 1, ?)";
        $add_stmt = $conn->prepare($add_sql);
        $add_stmt->bind_param("ssi", $title, $content, $user_id);
    
        if ($add_stmt->execute()) {
            // Set success message
            $_SESSION['success'] = "Announcement created successfully.";

            // Log activity to audit trail
            $audit_sql = "INSERT INTO audit_trail (user_id, action) VALUES (?, ?)";
            $audit_stmt = $conn->prepare($audit_sql);
            $action = "Added a new announcement: " . $title;
            $audit_stmt->bind_param("is", $user_id, $action);
            $audit_stmt->execute();
        } else {
            $_SESSION['fail'] = "Creating announcement failed: " . $add_stmt->error;
        }
    
        // Redirect user back to announcements management
        header("Location: ../../admin/announcements.php");
        exit();
    } else {
        $_SESSION['fail'] = "Please fill in all required fields.";
        header("Location: ../../admin/announcements.php");
        exit();
    }
}
?>