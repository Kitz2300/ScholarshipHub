<?php 
include __DIR__ . "/../../includes/conn.php";
include __DIR__ . "/../../includes/check_permission.php";
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['update'])) {
    header("Location: ../../admin/scholarship_program.php");
    exit();
}

// ─── Permission Check ───
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

// ─── Retrieve & Sanitize Inputs ───
$name = trim($_POST['name'] ?? '');
$code = trim($_POST['code'] ?? '');
$start_year = (int) ($_POST['start_year'] ?? date('Y'));
$end_year = !empty($_POST['end_year']) ? (int) $_POST['end_year'] : ($start_year + 1);
$school_year = $start_year . " - " . $end_year;

$provider_id = (int) ($_POST['provider_id'] ?? 0);
$description = trim($_POST['desc'] ?? '');
$pay = (float) ($_POST['pay_amount'] ?? 0);
$gwa_req = !empty($_POST['gwa_req']) ? (float) $_POST['gwa_req'] : 0.00;

$sc_type = trim($_POST['sc_type'] ?? '');
if ($sc_type === 'Other' && !empty($_POST['sc_type_other'])) {
    $sc_type = trim($_POST['sc_type_other']);
}

// ─── Required Fields Validation ───
if (!empty($name) && !empty($code) && !empty($description) && !empty($school_year) && $provider_id > 0 && !empty($sc_type)) {
    
    // Update Scholarship Program Record
    $update_sql = "UPDATE scholarship_program 
                   SET name = ?, code = ?, school_year = ?, provider_id = ?, description = ?, min_gwa_req = ?, pay_amount = ?, scholarship_type = ? 
                   WHERE id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("sssisddsi", $name, $code, $school_year, $provider_id, $description, $gwa_req, $pay, $sc_type, $scholarship_id);
    
    // Cascade School Year Updates to Scholar Profiles
    $update_scholar_sql = "UPDATE scholar_info SET school_year = ? WHERE scholarship_program_id = ?";
    $update_scholar_stmt = $conn->prepare($update_scholar_sql);
    $update_scholar_stmt->bind_param('si', $school_year, $scholarship_id);

    if ($update_scholar_stmt->execute() && $update_stmt->execute()) {
        // ========== AUDIT TRAIL LOGGING ==========
        $user_id = $_SESSION['user_id'] ?? $_SESSION['id'] ?? 0;
        $action = "Updated scholarship program ID $scholarship_id: $name ($code)";

        $audit_sql = "INSERT INTO audit_trail (user_id, action) VALUES (?, ?)";
        $audit_stmt = $conn->prepare($audit_sql);
        $audit_stmt->bind_param("is", $user_id, $action);
        $audit_stmt->execute();

        $_SESSION['success'] = "Scholarship program successfully updated.";
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