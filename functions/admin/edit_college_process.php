<?php 
include __DIR__ . "/../../includes/conn.php";
session_start();

// ─── EDIT COLLEGE ───
if(isset($_POST['edit_college'])) {
    $college_id = (int) $_POST['college_id'];
    $college_name = trim($_POST['college_name']);
    $college_code = trim($_POST['college_code']);
    
    if(!empty($college_name) && !empty($college_code) && $college_id > 0) {
        $sql = "UPDATE college SET college_name = ?, college_code = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssi", $college_name, $college_code, $college_id);
        if($stmt->execute()) {
            // ========== AUDIT TRAIL LOGGING ==========
            $user_id = $_SESSION['user_id'] ?? $_SESSION['id'] ?? 0;
            $action = "Updated college ID $college_id: " . $college_name . " (" . $college_code . ")";

            $audit_sql = "INSERT INTO audit_trail (user_id, action) VALUES (?, ?)";
            $audit_stmt = $conn->prepare($audit_sql);
            $audit_stmt->bind_param("is", $user_id, $action);
            $audit_stmt->execute();

            $_SESSION['success'] = "College updated successfully.";
        } else {
            $_SESSION['fail'] = "Failed to update college.";
        }
    } else {
        $_SESSION['fail'] = "All fields are required.";
    }
    header("Location: ../../admin/program_management.php");
    exit();
}
?>