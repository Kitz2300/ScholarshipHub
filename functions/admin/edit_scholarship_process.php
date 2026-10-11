<?php 
include __DIR__ . "/../../includes/conn.php";
include __DIR__ . "/../../includes/check_permission.php";
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['update'])) {
    header("Location: ../../admin/scholarship_program.php");
    exit();
}

if ($_SESSION['role'] !== 'admin' && !hasPermission($conn, $_SESSION['user_id'], 'scholarship_programs', 'edit')) {
    $_SESSION['fail'] = "You do not have permission to edit scholarship programs.";
    header("Location: ../../admin/scholarship_program.php");
    exit();
}

$scholarship_id = (int) ($_POST['scholarship_id'] ?? 0);

if ($scholarship_id === 0) {
    $_SESSION['fail'] = "Scholarship Program does not exist!";
    header("Location: ../../admin/scholarship_program.php");
    exit();
}

$name = trim($_POST['name'] ?? '');
$code = trim($_POST['code'] ?? '');
$start_year = (int) ($_POST['start_year'] ?? date('Y'));
$end_year = !empty($_POST['end_year']) ? (int) $_POST['end_year'] : ($start_year + 1);
$school_year = $start_year . " - " . $end_year;
$semester = trim($_POST['semester'] ?? '1st Semester');

$provider_id = (int) ($_POST['provider_id'] ?? 0);
$description = trim($_POST['desc'] ?? ''); // Optional description
$pay = (float) ($_POST['pay_amount'] ?? 0);
$gwa_req = !empty($_POST['gwa_req']) ? (float) $_POST['gwa_req'] : 0.00;

$sc_type = trim($_POST['sc_type'] ?? '');
if ($sc_type === 'Other' && !empty($_POST['sc_type_other'])) {
    $sc_type = trim($_POST['sc_type_other']);
}

if (!empty($name) && !empty($code) && !empty($school_year) && !empty($semester) && $provider_id > 0 && !empty($sc_type)) {
    
    // 1. Check for Duplicate Program in Target Term (School Year & Semester)
    $clean_school_year = str_replace(' ', '', $school_year);
    $check_dup_sql = "SELECT id FROM scholarship_program 
                      WHERE id != ? 
                      AND (name = ? OR code = ?) 
                      AND REPLACE(school_year, ' ', '') = ? 
                      AND semester = ? 
                      AND is_archived = 0";
    $check_dup_stmt = $conn->prepare($check_dup_sql);
    $check_dup_stmt->bind_param("issss", $scholarship_id, $name, $code, $clean_school_year, $semester);
    $check_dup_stmt->execute();
    $dup_res = $check_dup_stmt->get_result();

    if ($dup_res->num_rows > 0) {
        $_SESSION['fail'] = "A scholarship program with this name or code already exists for " . htmlspecialchars($school_year) . " (" . htmlspecialchars($semester) . ").";
        header("Location: ../../admin/edit_scholarship_program.php?id=" . $scholarship_id);
        exit();
    }

    // 2. Update Scholarship Program Record
    $update_sql = "UPDATE scholarship_program 
                   SET name = ?, code = ?, school_year = ?, semester = ?, provider_id = ?, description = ?, min_gwa_req = ?, pay_amount = ?, scholarship_type = ? 
                   WHERE id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("ssssisddsi", $name, $code, $school_year, $semester, $provider_id, $description, $gwa_req, $pay, $sc_type, $scholarship_id);

    if ($update_stmt->execute()) {

        // 3. Cascade update semester & school year to all scholars assigned to this program
        $update_scholars_sql = "UPDATE scholar_info 
                                SET semester = ?, school_year = ? 
                                WHERE scholarship_program_id = ?";
        $update_scholars_stmt = $conn->prepare($update_scholars_sql);
        $update_scholars_stmt->bind_param("ssi", $semester, $school_year, $scholarship_id);
        $update_scholars_stmt->execute();

        // Audit Trail Logging
        $user_id = $_SESSION['user_id'] ?? $_SESSION['id'] ?? 0;
        $action = "Updated scholarship program ID $scholarship_id: $name ($code) to $school_year ($semester) and synced assigned scholars.";

        $audit_sql = "INSERT INTO audit_trail (user_id, action) VALUES (?, ?)";
        $audit_stmt = $conn->prepare($audit_sql);
        $audit_stmt->bind_param("is", $user_id, $action);
        $audit_stmt->execute();

        $_SESSION['success'] = "Scholarship program and assigned scholars successfully updated.";
        header("Location: ../../admin/scholarship_program.php");
        exit();
    } else {
        $_SESSION['fail'] = "Failed to update scholarship program: " . $conn->error;
        header("Location: ../../admin/edit_scholarship_program.php?id=" . $scholarship_id);
        exit();
    }
} else {
    $_SESSION['fail'] = "Please fill in all required fields.";
    header("Location: ../../admin/edit_scholarship_program.php?id=" . $scholarship_id);
    exit();
}
?>