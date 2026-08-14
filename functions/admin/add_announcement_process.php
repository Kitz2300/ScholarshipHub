<?php 
require_once __DIR__ . "/../../includes/conn.php";
session_start();

if(isset($_POST['add'])) {
    // check if user id is not empty
    $user_id = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : "";
    
    // check if user_id and inputs are not empty
    if(!empty($user_id) && isset($_POST['title']) && isset($_POST['content'])) {    
        // store inputs in variables
        $title = $_POST['title'];
        $content = $_POST['content'];
    
        // insert query
        $add_sql = "INSERT INTO announcements(title, content, is_active, user_id) VALUES(?,?,1,?)";
        $add_stmt = $conn->prepare($add_sql);
        $add_stmt->bind_param("ssi", $title, $content, $user_id);
    
        // success message with audit insert, also fail message
        if($add_stmt->execute()) {
            // $_SESSION['success'] = "Announcement Created";

            $audit_sql = "INSERT INTO audit_trail(user_id, action) VALUES(?, ?)";
            $audit_stmt = $conn->prepare($audit_sql);
            $action = "Added a new announcement: " . $title;
            $audit_stmt->bind_param("is", $user_id, $action);
            $audit_stmt->execute();
        } else {
            $_SESSION['fail'] = "Creating Announcement Failed " . $add_stmt->error;
        }
    
        // redirect user after the process
        header("Location: ../../admin/announcements.php");
        exit();
    } 
}
 
?>