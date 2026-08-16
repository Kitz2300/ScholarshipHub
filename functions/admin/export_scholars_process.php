<?php
include __DIR__ . "/../../includes/conn.php";
require_once "../../lib/vendor/autoload.php";
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
session_start();

// ===== Authorization =====
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Unauthorized access.");
}

// ===== Handle AJAX Preview request =====
$isPreview = isset($_POST['action']) && $_POST['action'] === 'preview';

// Get filters and columns
$scholarship_program = $_POST['scholar_program'] ?? 'all';
$program = $_POST['program'] ?? 'all';
$selected_cols = isset($_POST['columns']) ? $_POST['columns'] : [];
$column_order = isset($_POST['column_order']) ? json_decode($_POST['column_order'], true) : [];

// --- DO NOT expand column_order – it's already expanded by the frontend ---

// Expand selected columns for SQL (these are the columns we fetch)
$expanded_cols = [];
foreach($selected_cols as $col) {
    if($col === "father_name") {
        $expanded_cols[] = "father_last";
        $expanded_cols[] = "father_given";
        $expanded_cols[] = "father_middle";
    } elseif($col === "mother_name") {
        $expanded_cols[] = "mother_last";
        $expanded_cols[] = "mother_given";
        $expanded_cols[] = "mother_middle";
    } else {
        $expanded_cols[] = $col;
    }
}
$export_cols = array_unique($expanded_cols);

// Column mapping
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

// Build SELECT part
$select_parts = [];
foreach($export_cols as $col) {
    if(isset($column_map[$col])) {
        $select_parts[] = $column_map[$col] . ' AS ' . $col;
    }
}
$select_sql = implode(', ', $select_parts);

// Build FROM and WHERE
$sql = "SELECT $select_sql 
        FROM scholar_info
        LEFT JOIN program ON scholar_info.program_id = program.id
        LEFT JOIN scholarship_program ON scholar_info.scholarship_program_id = scholarship_program.id
        WHERE scholar_info.is_archived = 0";

if($scholarship_program !== 'all') {
    $sql .= " AND scholarship_program.name = '" . $conn->real_escape_string($scholarship_program) . "'";
}
if($program !== 'all') {
    $sql .= " AND program.name = '" . $conn->real_escape_string($program) . "'";
}
$sql .= " ORDER BY scholar_info.last_name ASC";

// ===== If preview, return JSON =====
if($isPreview) {
    $limitSql = $sql . " LIMIT 5";
    $result = $conn->query($limitSql);
    $rows = [];
    while($row = $result->fetch_assoc()) {
        if(isset($row['sex'])) {
            $row['sex'] = $row['sex'] == 0 ? 'Male' : 'Female';
        }
        $rows[] = $row;
    }
    header('Content-Type: application/json');
    echo json_encode([
        'columns' => $export_cols,
        'display_headers' => $display_headers,
        'rows' => $rows
    ]);
    exit;
}

// ===== Otherwise, do full export =====
// Use column_order if provided and complete, else fallback to $export_cols
if(!empty($column_order) && is_array($column_order)) {
    // Filter to only include columns that exist in $export_cols
    $ordered_cols = array_intersect($column_order, $export_cols);
    // If the filtered order has the same count as $export_cols, use it
    if(count($ordered_cols) === count($export_cols)) {
        $final_cols = array_values($ordered_cols); // re-index
    } else {
        // Otherwise fallback to default order
        $final_cols = $export_cols;
    }
} else {
    $final_cols = $export_cols;
}

// Execute full query
$result = $conn->query($sql);
if(!$result || $result->num_rows === 0) {
    $_SESSION['fail'] = "No records found to export.";
    header("Location: ../../admin/export_scholars.php");
    exit();
}

// Create spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Define styles
$headerStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
];

$dataStyle = [
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
];

// --------------------
// NEW: Output columns in EXACT order (no category grouping)
// --------------------

// Add header row (Row 1) with column names
$col = 1;
foreach($final_cols as $colName) {
    $cell = Coordinate::stringFromColumnIndex($col) . '1';
    $headerText = $display_headers[$colName] ?? ucwords(str_replace('_', ' ', $colName));
    $sheet->setCellValue($cell, $headerText);
    $sheet->getStyle($cell)->applyFromArray($headerStyle);
    $col++;
}

// Add data rows (Row 2 onward)
$rowNum = 2;
while($row = $result->fetch_assoc()) {
    $col = 1;
    foreach($final_cols as $colName) {
        $value = $row[$colName] ?? '';
        if($colName === 'sex' && $value !== '') {
            $value = $value == 0 ? 'Male' : 'Female';
        }
        $cell = Coordinate::stringFromColumnIndex($col) . $rowNum;
        $sheet->setCellValue($cell, $value);
        $col++;
    }
    $rowNum++;
}

// Auto-size columns
$totalCols = count($final_cols);
for($c = 1; $c <= $totalCols; $c++) {
    $sheet->getColumnDimensionByColumn($c)->setAutoSize(true);
}

// Apply borders to data rows
$lastRow = $rowNum - 1;
if($lastRow >= 2) {
    $firstData = 'A2';
    $lastData = Coordinate::stringFromColumnIndex($totalCols) . $lastRow;
    $sheet->getStyle($firstData . ':' . $lastData)->applyFromArray($dataStyle);
}

// Freeze header row (Row 1)
$sheet->freezePane('A2');

// Set filename and download
$filename = !empty($_POST['filename']) ? $_POST['filename'] : 'scholars_export_' . date('Y-m-d');
$fullFilename = $filename . '.xlsx';

$writer = new Xlsx($spreadsheet);
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $fullFilename . '"');
header('Cache-Control: max-age=0');
header('Expires: 0');
header('Pragma: public');
$writer->save('php://output');
exit();
?>