<?php 
include __DIR__ . "/../../includes/conn.php";
session_start();

// ─── ADD PROGRAM ───
if(isset($_POST['add_program'])) {
    $program_name = trim($_POST['program_name']);
    $college_id = (int) $_POST['college_id'];
    
    if(!empty($program_name) && $college_id > 0) {
        $sql = "INSERT INTO program (name, college_id) VALUES (?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $program_name, $college_id);
        if($stmt->execute()) {
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