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

// merge required columns and selected columns by user
$export_cols = array_merge($required_cols, $_POST['columns'] ?? []);

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
    // for father and mother's name select last name, given name, and middle name
    'father_name' => "CONCAT(scholar_info.father_last_name, ', ', scholar_info.father_given_name, ' ', scholar_info.father_middle_name)",
    'mother_name' => "CONCAT(scholar_info.mother_last_name, ', ', scholar_info.mother_given_name, ' ', scholar_info.mother_middle_name)",
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
    'father_name' => "Father's Name",
    'mother_name' => "Mother's Name",
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