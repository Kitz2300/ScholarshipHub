<?php
session_start();
require_once __DIR__ . "/../../includes/conn.php";

if (isset($_POST['update'])) {
    // Get input
    $scholar_id = (int)($_POST['scholar_id'] ?? 0);
    $record_id = (int)($_POST['record_id'] ?? 0);
    $semester = trim($_POST['sem'] ?? '');
    $gwa = (float)($_POST['gwa'] ?? 0);
    $units = (float)($_POST['units'] ?? 0);

    // Validate required fields
    if (empty($semester) || $units <= 0 || $gwa < 0) {
        $_SESSION['fail'] = "All fields are required and must be valid!";
        header("Location: ../../admin/academic_performance.php?scholar_id=$scholar_id");
        exit();
    }

    // Verify record belongs to this scholar
    $verify_sql = "SELECT id FROM academic_performance WHERE id = ? AND scholar_id = ?";
    $verify_stmt = $conn->prepare($verify_sql);
    $verify_stmt->bind_param('ii', $record_id, $scholar_id);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result();
    if ($verify_result->num_rows === 0) {
        $_SESSION['fail'] = "Invalid academic record.";
        header("Location: ../../admin/academic_performance.php?scholar_id=$scholar_id");
        exit();
    }

    // Fetch scholar's school year
    $year_lvl_sql = "SELECT school_year FROM scholar_info WHERE id = ?";
    $year_lvl_stmt = $conn->prepare($year_lvl_sql);
    $year_lvl_stmt->bind_param('i', $scholar_id);
    $year_lvl_stmt->execute();
    $year_lvl_result = $year_lvl_stmt->get_result();
    if ($row = $year_lvl_result->fetch_assoc()) {
        $school_year = $row['school_year'];
    } else {
        $_SESSION['fail'] = "Scholar not found.";
        header("Location: ../../admin/academic_performance.php?scholar_id=$scholar_id");
        exit();
    }

    // Check for duplicate semester/year (excluding current record)
    $sem_sql = "SELECT id FROM academic_performance 
                WHERE scholar_id = ? AND semester = ? AND school_year = ? AND id != ?";
    $sem_stmt = $conn->prepare($sem_sql);
    $sem_stmt->bind_param('issi', $scholar_id, $semester, $school_year, $record_id);
    $sem_stmt->execute();
    $sem_result = $sem_stmt->get_result();
    if ($sem_result->num_rows > 0) {
        $_SESSION['fail'] = "A record for this semester and school year already exists.";
        header("Location: ../../admin/academic_performance.php?scholar_id=$scholar_id");
        exit();
    }

    // Perform update
    $update_sql = "UPDATE academic_performance SET semester = ?, gwa = ?, units = ? WHERE id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param('sddi', $semester, $gwa, $units, $record_id);

    if ($update_stmt->execute()) {
        $_SESSION['success'] = "Academic record updated!";
    } else {
        $_SESSION['fail'] = "Failed to update: " . $update_stmt->error;
    }

    header("Location: ../../admin/academic_performance.php?scholar_id=$scholar_id");
    exit();
}