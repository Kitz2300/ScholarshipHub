<?php 
include __DIR__ . "/../../includes/conn.php";
include __DIR__ . "/../../includes/check_permission.php";
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['add'])) {
    header("Location: ../../admin/scholarship_program.php");
    exit();
}

// Permission Check
if ($_SESSION['role'] !== 'admin' && !hasPermission($conn, $_SESSION['user_id'], 'scholarship_programs', 'add')) {
    $_SESSION['fail'] = "You do not have permission to add scholarship programs.";
    header("Location: ../../admin/scholarship_program.php");
    exit();
}

$required_fields = ['name', 'start_year', 'semester', 'provider_id', 'sc_type', 'pay_amount'];
$missing_fields = [];

foreach ($required_fields as $field) {
    if (empty($_POST[$field])) {
        $missing_fields[] = $field;
    }
}

if (!empty($missing_fields)) {
    $_SESSION['fail'] = "Please fill in all required fields.";
    header("Location: ../../admin/add_scholarship.php");
    exit();
}

$name = trim($_POST['name']);
$code = !empty($_POST['code']) ? trim($_POST['code']) : '';
$start_year = (int) $_POST['start_year'];
$end_year = !empty($_POST['end_year']) ? (int) $_POST['end_year'] : ($start_year + 1);
$school_year = $start_year . ' - ' . $end_year;
$semester = trim($_POST['semester']);

$provider_id = (int) $_POST['provider_id'];
$sc_type = trim($_POST['sc_type']);

if ($sc_type === 'Other' && !empty($_POST['sc_type_other'])) {
    $sc_type = trim($_POST['sc_type_other']);
}

// Description is optional
$description = !empty($_POST['desc']) ? trim($_POST['desc']) : '';
$pay_amount = (float) $_POST['pay_amount'];
$min_gwa_req = !empty($_POST['gwa_req']) ? (float) $_POST['gwa_req'] : 0.00;

// Verify Provider Existence
$check_provider_sql = "SELECT id FROM providers WHERE id = ? AND is_archived = 0";
$check_provider_stmt = $conn->prepare($check_provider_sql);
$check_provider_stmt->bind_param('i', $provider_id);
$check_provider_stmt->execute();

if ($check_provider_stmt->get_result()->num_rows === 0) {
    $_SESSION['fail'] = "Invalid or inactive funding provider selected.";
    header("Location: ../../admin/add_scholarship.php");
    exit();
}

// Insert Scholarship Program
$insert_sql = "INSERT INTO scholarship_program (
    name, code, school_year, semester, provider_id, scholarship_type, description, pay_amount, min_gwa_req, is_archived, is_active
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 1)";

$stmt = $conn->prepare($insert_sql);
$stmt->bind_param('ssssissdd', $name, $code, $school_year, $semester, $provider_id, $sc_type, $description, $pay_amount, $min_gwa_req);

if ($stmt->execute()) {
    // ========== AUDIT TRAIL LOGGING ==========
    $user_id = $_SESSION['user_id'] ?? $_SESSION['id'] ?? 0;
    $action = "Added scholarship program: $name for SY $school_year ($semester)";

    $audit_sql = "INSERT INTO audit_trail (user_id, action) VALUES (?, ?)";
    $audit_stmt = $conn->prepare($audit_sql);
    $audit_stmt->bind_param("is", $user_id, $action);
    $audit_stmt->execute();

    $_SESSION['success'] = "Scholarship program added successfully.";
    header("Location: ../../admin/scholarship_program.php");
    exit();
} else {
    $_SESSION['fail'] = "Failed to add scholarship program: " . $stmt->error;
    header("Location: ../../admin/add_scholarship.php");
    exit();
}
?>