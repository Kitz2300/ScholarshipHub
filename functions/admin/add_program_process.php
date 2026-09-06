<?php 
include __DIR__ . "/../../includes/conn.php";
session_start();

// ─── ADD PROGRAM ───
if (isset($_POST['add_program'])) {
    $program_name = trim($_POST['program_name'] ?? '');
    $college_id = (int) ($_POST['college_id'] ?? 0);
    
    if (!empty($program_name) && $college_id > 0) {
        $sql = "INSERT INTO program (name, college_id) VALUES (?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $program_name, $college_id);
        if ($stmt->execute()) {
            // ========== AUDIT TRAIL LOGGING ==========
            $user_id = $_SESSION['user_id'] ?? $_SESSION['id'] ?? 0;
            $action = "Added academic program: " . $program_name . " (College ID: " . $college_id . ")";

            $audit_sql = "INSERT INTO audit_trail (user_id, action) VALUES (?, ?)";
            $audit_stmt = $conn->prepare($audit_sql);
            $audit_stmt->bind_param("is", $user_id, $action);
            $audit_stmt->execute();

            $_SESSION['success'] = "Program added successfully.";
        } else {
            $_SESSION['fail'] = "Failed to add program.";
        }
    } else {
        $_SESSION['fail'] = "Program name and college are required.";
    }
    header("Location: ../../admin/program_management.php");
    exit();
}
?>