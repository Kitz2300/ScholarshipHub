<?php 
include __DIR__ . "/../../includes/conn.php";
session_start();

// ─── EDIT PROGRAM ───
if(isset($_POST['edit_program'])) {
    $program_id = (int) $_POST['program_id'];
    $program_name = trim($_POST['program_name']);
    $college_id = (int) $_POST['college_id'];
    
    if(!empty($program_name) && $college_id > 0 && $program_id > 0) {
        $sql = "UPDATE program SET name = ?, college_id = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sii", $program_name, $college_id, $program_id);
        if($stmt->execute()) {
            $_SESSION['success'] = "Program updated successfully.";
        } else {
            $_SESSION['fail'] = "Filed to update program.";
        }
    } else {
        $_SESSION['fail'] = "All fields are required.";
    }
    header("Location: ../../admin/program_management.php");
    exit();
}
?>