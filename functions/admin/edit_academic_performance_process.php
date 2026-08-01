<?php 
include __DIR__ . "/../../includes/conn.php";
session_start();

// execute action only when button is pressed
if(isset($_POST['update'])) {
    // store input values in variabled
    $scholar_id = (int) ($_POST['scholar_id'] ?? 0);
    $record_id = (int) ($_POST['record_id'] ?? 0);
    $semester = $_POST['sem'] ?? "";
    $gwa = (float) ($_POST['gwa'] ?? 0);
    $units = (float) ($_POST['units'] ?? 0);
    $school_year = $_POST['start_year'] . " - " . $_POST['end_year'];

    // validate required fields
    if(empty($semester) || $units == 0 || $gwa == 0 ) {
        $_SESSION['fail'] = "All fields are required!";
        header("Location: ../../admin/academic_performance.php?scholar_id=$scholar_id");
        exit();
    }

    $update_sql = "UPDATE academic_performance SET semester = ?, gwa = ?, units = ?, school_year = ? WHERE id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param('sddsi', $semester, $gwa, $units, $school_year, $record_id);
    
    if($update_stmt->execute()) {
        $_SESSION['success'] = "Scholar academic record updated!";
        header("Location: ../../admin/academic_performance.php?scholar_id=$scholar_id");
        exit();
    } else {
        $_SESSION['fail'] = "Failed to update academic record.";
        header("Location: ../../admin/academic_performance.php?scholar_id=$scholar_id");
        exit();
    }

}

?>
