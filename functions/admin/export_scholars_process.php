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

// Check authorization
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Unauthorized access.");
}

// initialize required columns
$required_cols = ['student_id', 'last_name', 'given_name', 'ext_name', 'middle_name', 'program_name', 'scholarship_program_name', 'year_level'];

// get scholarship_program, program filters and filename
$scholarship_program = !empty($_POST['scholar_program']) ? $_POST['scholar_program'] : '';
$program = !empty($_POST['program']) ? $_POST['program'] : '';
$filename = !empty($_POST['filename']) ? $_POST['filename'] : 'scholars_export_' . date('Y-m-d');
$selected_cols = $_POST['columns'] ?? [];

// if user selected father_name or mother_name then expand name into last, given, and middle
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
$export_cols = array_unique($export_cols);

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
    $where_clauses[] = "scholarship_program.name = '" . $conn->real_escape_string($scholarship_program) . "'";
}

if($program !== 'all') {
    $where_clauses[] = "program.name = '" . $conn->real_escape_string($program) . "'";
}

if(!empty($where_clauses)) {
    $scholar_sql .= " WHERE " . implode(' AND ', $where_clauses);
}

$scholar_sql .= " ORDER BY scholar_info.last_name ASC";

// Execute query
$result = $conn->query($scholar_sql);

if(!$result || $result->num_rows === 0) {
    $_SESSION['fail'] = "no records found to export";
    header("Location: ../../admin/export_scholars.php");
    exit();
}

// Define category groups (match the keys used in $export_cols)
$category_groups = [
    'STUDENT INFORMATION' => ['student_id', 'last_name', 'given_name', 'ext_name', 'middle_name', 'sex', 'birthdate', 'program_name', 'year_level', 'scholarship_program', 'academic_status'],
    'FAMILY BACKGROUND' => ['father_last', 'father_given', 'father_middle', 'mother_last', 'mother_given', 'mother_middle'],
    'PERMANENT ADDRESS' => ['permanent_address'],
    'CONTACT & OTHER' => ['contact', 'email', 'disability', 'ip_group'],
];

// Build the column order based on user selection
$column_order = [];
$category_ranges = [];
$current_col = 1;

foreach ($category_groups as $category => $columns) {
    $filtered_columns = array_filter($columns, function($col) use ($export_cols) {
        return in_array($col, $export_cols);
    });
    
    if (!empty($filtered_columns)) {
        $start_col = $current_col;
        $end_col = $current_col + count($filtered_columns) - 1;
        
        $category_ranges[] = [
            'category' => $category,
            'start' => $start_col,
            'end' => $end_col,
            'columns' => $filtered_columns
        ];
        
        foreach ($filtered_columns as $col) {
            $column_order[] = $col;
        }
        $current_col = $end_col + 1;
    }
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

// Add merged category headers (Row 1 / Header 1)
foreach ($category_ranges as $range) {
    $start = $range['start'];
    $end = $range['end'];
    
    if ($start < $end) {
        $startLetter = Coordinate::stringFromColumnIndex($start);
        $endLetter = Coordinate::stringFromColumnIndex($end);
        $rangeString = $startLetter . '1:' . $endLetter . '1';
        $sheet->mergeCells($rangeString);
    }
    
    $cell = Coordinate::stringFromColumnIndex($start) . '1';
    $sheet->setCellValue($cell, $range['category']);
    $sheet->getStyle($cell)->applyFromArray($headerStyle);
}

// Add column headers (Row 2 / Header 2)
$col = 1;
foreach ($column_order as $colName) {
    $headerText = $display_headers[$colName] ?? ucwords(str_replace('_', ' ', $colName));
    $cell = Coordinate::stringFromColumnIndex($col) . '2';
    $sheet->setCellValue($cell, $headerText);
    $sheet->getStyle($cell)->applyFromArray($headerStyle);
    $col++;
}

// Add data starting from Row 3
$rowNum = 3;
while($row = $result->fetch_assoc()) {
    $col = 1;
    foreach($column_order as $colName) {
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
$totalCols = count($column_order);
for($col = 1; $col <= $totalCols; $col++) {
    $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
}

// Apply borders to data rows
$lastRow = $rowNum - 1;
if($lastRow >= 3) {
    $firstDataCell = 'A3';
    $lastDataCell = Coordinate::stringFromColumnIndex($totalCols) . $lastRow;
    $sheet->getStyle($firstDataCell . ':' . $lastDataCell)->applyFromArray($dataStyle);
}

// Freeze header rows (both rows 1 and 2)
$sheet->freezePane('A3');

// Download Excel file


$writer = new Xlsx($spreadsheet);

// Set filename
$fullFilename = $filename . '.xlsx';

// Set headers for download
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $fullFilename . '"');
header('Cache-Control: max-age=0');
header('Expires: 0');
header('Pragma: public');

// Output the file
$writer->save('php://output');
exit();
?>