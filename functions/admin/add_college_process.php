<?php 
include __DIR__ . "/../../includes/conn.php";
session_start();

// ─── ADD COLLEGE ───
if (isset($_POST['add_college'])) {
    $college_name = trim($_POST['college_name'] ?? '');
    $college_code = trim($_POST['college_code'] ?? '');
    
    if (!empty($college_name) && !empty($college_code)) {
        $sql = "INSERT INTO college (college_name, college_code) VALUES (?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $college_name, $college_code);
        
        if ($stmt->execute()) {
            // ========== AUDIT TRAIL LOGGING ==========
            $user_id = $_SESSION['user_id'] ?? $_SESSION['id'] ?? 0;
            $action = "Added college: " . $college_name . " (" . $college_code . ")";

            $audit_sql = "INSERT INTO audit_trail (user_id, action) VALUES (?, ?)";
            $audit_stmt = $conn->prepare($audit_sql);
            $audit_stmt->bind_param("is", $user_id, $action);
            $audit_stmt->execute();

            $_SESSION['success'] = "College added successfully.";
        } else {
            $_SESSION['fail'] = "Failed to add college.";
        }
    } else {
        $_SESSION['fail'] = "College name and code are required.";
    }
    header("Location: ../../admin/program_management.php");
    exit();
}
?>