<?php
session_start();
require_once __DIR__ . "/../../includes/conn.php";

if (isset($_POST['add']) && isset($_POST['scholar_id'])) {
    $scholar_id = (int)$_POST['scholar_id'];
    $sem = trim($_POST['sem'] ?? '');
    $gwa = (float)$_POST['gwa'] ?? 0.0;
    $units = (float)$_POST['units'] ?? 0.0;

    // ========== VALIDATE INPUTS ==========
    if (empty($sem) || $units <= 0 || $gwa < 0) {
        $_SESSION['fail'] = "All fields are required and must be valid (GWA >= 0, Units > 0).";
        header("Location: ../../admin/academic_performance.php?scholar_id=$scholar_id");
        exit();
    }

    // Optional: GWA range check (adjust max to your system)
    if ($gwa > 5.0) {
        $_SESSION['fail'] = "GWA cannot exceed 5.0.";
        header("Location: ../../admin/academic_performance.php?scholar_id=$scholar_id");
        exit();
    }

    // ========== FETCH SCHOLAR DATA ==========
    $year_lvl_sql = "SELECT year_level, school_year FROM scholar_info WHERE id = ?";
    $year_lvl_stmt = $conn->prepare($year_lvl_sql);
    $year_lvl_stmt->bind_param('i', $scholar_id);
    $year_lvl_stmt->execute();
    $year_lvl_result = $year_lvl_stmt->get_result();

    if ($row = $year_lvl_result->fetch_assoc()) {
        if (empty($row['year_level']) || empty($row['school_year'])) {
            $_SESSION['fail'] = "Scholar has no year level or School Year set.";
            header("Location: ../../admin/academic_performance.php?scholar_id=$scholar_id");
            exit();
        }
        $year_lvl = $row['year_level'];
        $school_year = $row['school_year'];
    } else {
        $_SESSION['fail'] = "Scholar not found!";
        header("Location: ../../admin/academic_performance.php?scholar_id=$scholar_id");
        exit();
    }

    // ========== DUPLICATE CHECK ==========
    $sem_sql = "SELECT id FROM academic_performance 
                WHERE scholar_id = ? AND semester = ? AND school_year = ?";
    $sem_stmt = $conn->prepare($sem_sql);
    $sem_stmt->bind_param('iss', $scholar_id, $sem, $school_year);
    $sem_stmt->execute();
    $sem_result = $sem_stmt->get_result();

    if ($sem_result->num_rows > 0) {
        $_SESSION['fail'] = "A record for this semester and school year already exists.";
        header("Location: ../../admin/academic_performance.php?scholar_id=$scholar_id");
        exit();
    }

    // ========== INSERT ==========
    $sql = "INSERT INTO academic_performance (scholar_id, semester, gwa, units, year_level, school_year)
            VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('isddss', $scholar_id, $sem, $gwa, $units, $year_lvl, $school_year);

    if ($stmt->execute()) {
        $_SESSION['success'] = "Academic record successfully added.";
    } else {
        $_SESSION['fail'] = "Failed to add academic record: " . $stmt->error;
    }

    header("Location: ../../admin/academic_performance.php?scholar_id=$scholar_id");
    exit();
}
?>