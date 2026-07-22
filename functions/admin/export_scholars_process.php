<?php
include __DIR__ . "/../../includes/conn.php";
require_once "../../lib/vendor/autoload.php";
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
session_start();

// initialize required columns
$required_cols = ['student_id', 'last_name', 'given_name', 'ext_name', 'middle_name', 'program_name', 'scholarship_program_name', 'year_level'];

// get scholarship_program, program filters and filename
$scholarship_program = !empty($_POST['scholar_program']) ? $_POST['scholar_program'] : '';
$program = !empty($_POST['program']) ? $_POST['program'] : '';
$filename = !empty($_POST['filename']) ? $_POST['filename'] : '';
$selected_cols = $_POST['columns'] ?? [];

// if user selected father_name or mmother_name then expand name into last, given, and middle
$expanded_cols = [];
foreach($selected_cols as $cols) {
    if($cols === "father_name") {
        $expanded_cols[] = "father_last";
        $expanded_cols[] = "father_given";
        $expanded_cols[] = "father_middle";
    } else if($cols === "mother_name") {
        $expanded_cols[] = "mother_last";
        $expanded_cols[] = "mother_given";
        $expanded_cols[] = "mother_middle";
    } else {
        $expanded_cols[] = $cols;
    }
}


// merge required columns and selected columns by user
$export_cols = array_merge($required_cols, $expanded_cols);

// column mapping
$column_map = [
    'student_id' => 'scholar_info.student_id',
    'last_name' => 'scholar_info.last_name',
    'given_name' => 'scholar_info.given_name',
    'ext_name' => 'scholar_info.ext_name',
    'middle_name' => 'scholar_info.middle_name',
    'sex' => 'scholar_info.sex',
    'birthdate' => 'scholar_info.birthdate',
    'program_name' => 'program.name',
    'year_level' => 'scholar_info.year_level',
    'scholarship_program' => 'scholarship_program.name',
    'academic_status' => 'scholar_info.academic_status',

    'father_last' => 'scholar_info.father_last_name',
    'father_given' => 'scholar_info.father_given_name',
    'father_middle' => 'scholar_info.father_middle_name',

    'mother_last' => 'scholar_info.mother_last_name',
    'mother_given' => 'scholar_info.mother_given_name',
    'mother_middle' => 'scholar_info.mother_middle_name',

    'permanent_address' => 'scholar_info.permanent_address',
    'disability' => 'scholar_info.disability',
    'contact' => 'scholar_info.contact_num',
    'email' => 'scholar_info.email',
    'ip_group' => 'scholar_info.ip_group',
];

$display_headers = [
    'student_id' => 'Student ID',
    'last_name' => 'Last Name',
    'given_name' => 'Given Name',
    'ext_name' => 'Ext. Name',
    'middle_name' => 'Middle Name',
    'sex' => 'Sex',
    'birthdate' => 'Birthdate',
    'program_name' => 'Program',
    'year_level' => 'Year Level',
    'scholarship_program' => 'Scholarship Program',
    'academic_status' => 'Academic Status',
    'father_last' => "Father's Last Name",
    'father_given' => "Father's Given Name",
    'father_middle' => "Father's Middle Name",
    'mother_last' => "Mother's Last Name",
    'mother_given' => "Mother's Given Name",
    'mother_middle' => "Mother's Middle Name",
    'permanent_address' => 'Permanent Address',
    'disability' => 'Disability',
    'contact' => 'Contact Number',
    'email' => 'Email Address',
    'ip_group' => 'IP Group',
];

// only get the corresponding map based on the required and selected columns
$select_parts = [];
foreach ($export_cols as $col) {
    if(isset($column_map[$col])) {
        $select_parts[] = $column_map[$col] . ' AS ' . $col;
    }
}

// Convert to comma-separated string
$select_sql = implode(', ', $select_parts);

// full sql query
$scholar_sql = "SELECT $select_sql 
        FROM scholar_info
        LEFT JOIN program ON scholar_info.program_id = program.id
        LEFT JOIN scholarship_program ON scholar_info.scholarship_program_id = scholarship_program.id";

// add WHERE clauses for filters
$where_clauses = [];

if($scholarship_program !== 'all') {
    $where_clauses[] = "sp.name = '" . $conn->real_escape_string($scholarship_program) . "'";
}

if($program !== 'all') {
    $where_clauses[] = "p.name = '" . $conn->real_escape_string($program) . "'";
}

if(!empty($where_clauses)) {
    $scholar_sql .= " WHERE " . implode(' AND ', $where_clauses);
}

$scholar_sql .= " ORDER BY s.last_name ASC";

// 11. Execute query
$stmt = $conn->prepare($scholar_sql);
$stmt->execute();
$result = $stmt->get_result();

if(!$result || $result->num_rows === 0) {
    $_SESSION['fail'] = "no records found to export";
    header("Location: ../../admin/export_scholars.php");
    exit();
}


?>