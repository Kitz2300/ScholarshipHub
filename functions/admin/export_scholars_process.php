<?php
include __DIR__ . "/../../includes/conn.php";
require_once "../../vendor/autoload.php";
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
$college = $_POST['college'] ?? 'all';
$program = $_POST['program'] ?? 'all';
$academic_status = $_POST['academic_status'] ?? 'all';
$selected_cols = isset($_POST['columns']) ? $_POST['columns'] : [];
$column_order = isset($_POST['column_order']) ? json_decode($_POST['column_order'], true) : [];

// --- Separate main columns and extra columns ---
$main_cols = [];
$extra_cols = [];
foreach($selected_cols as $col) {
    if($col === "father_name") {
        $main_cols[] = "father_last";
        $main_cols[] = "father_given";
        $main_cols[] = "father_middle";
    } elseif($col === "mother_name") {
        $main_cols[] = "mother_last";
        $main_cols[] = "mother_given";
        $main_cols[] = "mother_middle";
    } elseif(strpos($col, 'extra_') === 0) {
        $extra_cols[] = $col; // keep full name e.g., "extra_Remarks"
    } else {
        $main_cols[] = $col;
    }
}

// Automatically enforce LOA details based on academic status filter
if ($academic_status === 'Leave of Absence') {
    if (!in_array('loa_semester', $main_cols)) {
        $main_cols[] = 'loa_semester';
    }
    if (!in_array('loa_school_year', $main_cols)) {
        $main_cols[] = 'loa_school_year';
    }
} else {
    $main_cols = array_values(array_diff($main_cols, ['loa_semester', 'loa_school_year']));
}

// Column mapping for main fields
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
    'father_contact' => 'scholar_info.father_contact',
    'mother_last' => 'scholar_info.mother_last_name',
    'mother_given' => 'scholar_info.mother_given_name',
    'mother_middle' => 'scholar_info.mother_middle_name',
    'mother_contact' => 'scholar_info.mother_contact',
    'is_child_of_solo_parent' => 'scholar_info.is_child_of_solo_parent',
    'permanent_address' => 'scholar_info.permanent_address',
    'disability' => 'scholar_info.disability',
    'contact' => 'scholar_info.contact_num',
    'email' => 'scholar_info.email',
    'ip_group' => 'scholar_info.ip_group',
    'philsys_id' => 'scholar_info.philsys_id',
    'lrn' => 'scholar_info.lrn',
    'four_ps_id' => 'scholar_info.four_ps_id',
    'gwa' => '(SELECT ap.gwa FROM academic_performance ap WHERE ap.scholar_id = scholar_info.id ORDER BY ap.school_year DESC LIMIT 1)',
    'loa_semester' => 'scholar_info.loa_semester',
    'loa_school_year' => 'scholar_info.loa_school_year',
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
    'father_contact' => "Father's Contact Number",
    'mother_last' => "Mother's Last Name",
    'mother_given' => "Mother's Given Name",
    'mother_middle' => "Mother's Middle Name",
    'mother_contact' => "Mother's Contact Number",
    'is_child_of_solo_parent' => 'Child of Solo Parent',
    'permanent_address' => 'Permanent Address',
    'disability' => 'Disability',
    'contact' => 'Contact Number',
    'email' => 'Email Address',
    'ip_group' => 'IP Group',
    'philsys_id' => 'PHILSYS ID',
    'lrn' => 'LRN',
    'four_ps_id' => "4P's ID",
    'gwa' => 'GWA',
    'loa_semester' => 'LOA Semester',
    'loa_school_year' => 'LOA School Year',
];

// Build the list of all columns (main + extra) for final ordering
$all_selected = array_merge($main_cols, $extra_cols);

// Determine if we need to join scholar_notes
$has_extra = !empty($extra_cols);

// Build SELECT part for main columns
$select_parts = [];
foreach($main_cols as $col) {
    if(isset($column_map[$col])) {
        $select_parts[] = $column_map[$col] . ' AS ' . $col;
    }
}
if($has_extra) {
    $select_parts[] = 'scholar_notes.notes AS _notes';
}
$select_sql = implode(', ', $select_parts);

// Build FROM and WHERE
$sql = "SELECT $select_sql 
        FROM scholar_info
        LEFT JOIN program ON scholar_info.program_id = program.id
        LEFT JOIN college ON program.college_id = college.id
        LEFT JOIN scholarship_program ON scholar_info.scholarship_program_id = scholarship_program.id";
if($has_extra) {
    $sql .= " LEFT JOIN scholar_notes ON scholar_notes.scholar_id = scholar_info.id";
}
$sql .= " WHERE scholar_info.is_archived = 0";

if($scholarship_program !== 'all' && !empty($scholarship_program)) {
    if(is_numeric($scholarship_program)) {
        $sql .= " AND scholar_info.scholarship_program_id = " . (int)$scholarship_program;
    } else {
        $sql .= " AND scholarship_program.name = '" . $conn->real_escape_string($scholarship_program) . "'";
    }
}
if($college !== 'all' && !empty($college)) {
    if(is_numeric($college)) {
        $sql .= " AND program.college_id = " . (int)$college;
    } else {
        $sql .= " AND college.college_name = '" . $conn->real_escape_string($college) . "'";
    }
}
if($program !== 'all') {
    $sql .= " AND program.name = '" . $conn->real_escape_string($program) . "'";
}
if($academic_status !== 'all') {
    $sql .= " AND scholar_info.academic_status = '" . $conn->real_escape_string($academic_status) . "'";
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
        if(isset($row['is_child_of_solo_parent'])) {
            $row['is_child_of_solo_parent'] = $row['is_child_of_solo_parent'] == 1 ? 'Yes' : 'No';
        }
        // Decode notes for extra columns
        if($has_extra && isset($row['_notes'])) {
            $notes = json_decode($row['_notes'], true);
            if(is_array($notes)) {
                foreach($extra_cols as $extra_key) {
                    $actual_key = substr($extra_key, 6);
                    $row[$extra_key] = $notes[$actual_key] ?? '';
                }
            } else {
                foreach($extra_cols as $extra_key) {
                    $row[$extra_key] = '';
                }
            }
            unset($row['_notes']);
        }
        $rows[] = $row;
    }
    
    $columns_for_preview = $all_selected;
    if(!empty($column_order)) {
        $ordered = [];
        foreach($column_order as $col) {
            if(in_array($col, $all_selected)) {
                $ordered[] = $col;
            }
        }
        foreach($all_selected as $col) {
            if(!in_array($col, $ordered)) {
                $ordered[] = $col;
            }
        }
        $columns_for_preview = $ordered;
    }
    header('Content-Type: application/json');
    echo json_encode([
        'columns' => $columns_for_preview,
        'display_headers' => array_merge($display_headers, array_combine($extra_cols, array_map(function($k) { return substr($k, 6); }, $extra_cols))),
        'rows' => $rows
    ]);
    exit;
}

// ===== Otherwise, do full export =====
$final_cols = $all_selected;
if(!empty($column_order)) {
    $ordered = [];
    foreach($column_order as $col) {
        if(in_array($col, $all_selected)) {
            $ordered[] = $col;
        }
    }
    foreach($all_selected as $col) {
        if(!in_array($col, $ordered)) {
            $ordered[] = $col;
        }
    }
    $final_cols = $ordered;
}

// Execute full query
$result = $conn->query($sql);
if(!$result || $result->num_rows === 0) {
    $_SESSION['fail'] = "No records found to export.";
    header("Location: ../../admin/export_scholars.php");
    exit();
}

// Fetch Scholarship Program Details for Row 1 Header Banner
$sp_title = "ALL SCHOLARSHIP PROGRAMS";
$sp_school_year = "";
if ($scholarship_program !== 'all' && !empty($scholarship_program)) {
    if (is_numeric($scholarship_program)) {
        $sp_info_sql = "SELECT name, school_year FROM scholarship_program WHERE id = " . (int)$scholarship_program;
    } else {
        $sp_info_sql = "SELECT name, school_year FROM scholarship_program WHERE name = '" . $conn->real_escape_string($scholarship_program) . "'";
    }
    $sp_info_res = $conn->query($sp_info_sql);
    if ($sp_info_res && $sp_info_row = $sp_info_res->fetch_assoc()) {
        $sp_title = $sp_info_row['name'];
        $sp_school_year = $sp_info_row['school_year'];
    }
}

// Build Row 1 Banner Text
$banner_text = strtoupper($sp_title);
if (!empty($sp_school_year)) {
    $banner_text .= " (" . $sp_school_year . ")";
}

// Create spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Define styles
$bannerStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 12],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E4628']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
];

$headerStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
];

$dataStyle = [
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
];

$totalCols = count($final_cols);
$lastColLetter = Coordinate::stringFromColumnIndex($totalCols);

// ===== ROW 1: SCHOLARSHIP PROGRAM & SCHOOL YEAR HEADER BANNER =====
$sheet->mergeCells("A1:{$lastColLetter}1");
$sheet->setCellValue('A1', $banner_text);
$sheet->getStyle("A1:{$lastColLetter}1")->applyFromArray($bannerStyle);
$sheet->getRowDimension(1)->setRowHeight(32);

// ===== ROW 2: COLUMN HEADERS =====
$col = 1;
foreach($final_cols as $colName) {
    $cell = Coordinate::stringFromColumnIndex($col) . '2';
    if(strpos($colName, 'extra_') === 0) {
        $headerText = substr($colName, 6);
    } else {
        $headerText = $display_headers[$colName] ?? ucwords(str_replace('_', ' ', $colName));
    }
    $sheet->setCellValue($cell, $headerText);
    $sheet->getStyle($cell)->applyFromArray($headerStyle);
    $col++;
}
$sheet->getRowDimension(2)->setRowHeight(24);

// ===== ROW 3 ONWARDS: DATA ROWS =====
$rowNum = 3;
while($row = $result->fetch_assoc()) {
    if($has_extra && isset($row['_notes'])) {
        $notes = json_decode($row['_notes'], true);
        if(is_array($notes)) {
            foreach($extra_cols as $extra_key) {
                $actual_key = substr($extra_key, 6);
                $row[$extra_key] = $notes[$actual_key] ?? '';
            }
        } else {
            foreach($extra_cols as $extra_key) {
                $row[$extra_key] = '';
            }
        }
        unset($row['_notes']);
    }

    $col = 1;
    foreach($final_cols as $colName) {
        $value = $row[$colName] ?? '';
        if($colName === 'sex' && $value !== '') {
            $value = $value == 0 ? 'Male' : 'Female';
        }
        if($colName === 'is_child_of_solo_parent' && $value !== '') {
            $value = $value == 1 ? 'Yes' : 'No';
        }
        $cell = Coordinate::stringFromColumnIndex($col) . $rowNum;
        $sheet->setCellValue($cell, $value);
        $col++;
    }
    $rowNum++;
}

// Auto-size columns
for($c = 1; $c <= $totalCols; $c++) {
    $sheet->getColumnDimensionByColumn($c)->setAutoSize(true);
}

// Apply borders to data rows (starting at Row 3)
$lastRow = $rowNum - 1;
if($lastRow >= 3) {
    $firstData = 'A3';
    $lastData = $lastColLetter . $lastRow;
    $sheet->getStyle($firstData . ':' . $lastData)->applyFromArray($dataStyle);
}

// Freeze top two header rows (Freeze below Row 2)
$sheet->freezePane('A3');

// Set filename and download
$filename = !empty($_POST['filename']) ? $_POST['filename'] : 'scholars_export_' . date('Y-m-d');
$fullFilename = $filename . '.xlsx';

// ========== AUDIT TRAIL LOGGING ==========
$user_id = $_SESSION['user_id'] ?? $_SESSION['id'] ?? 0;
$action = "Exported scholar data to Excel ($fullFilename) [Scholarship Program: $scholarship_program, College: $college, Academic Program: $program, Academic Status: $academic_status]";

$audit_sql = "INSERT INTO audit_trail (user_id, action) VALUES (?, ?)";
$audit_stmt = $conn->prepare($audit_sql);
$audit_stmt->bind_param("is", $user_id, $action);
$audit_stmt->execute();

$writer = new Xlsx($spreadsheet);
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $fullFilename . '"');
header('Cache-Control: max-age=0');
header('Expires: 0');
header('Pragma: public');
$writer->save('php://output');
exit();
?>
