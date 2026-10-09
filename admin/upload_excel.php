<?php 
include __DIR__ . '/../includes/conn.php';
include __DIR__ . '/../includes/check_permission.php';
include __DIR__ . '/../includes/school_year_helper.php';
include __DIR__ . '/../functions/admin/upload_validation.php';
session_start();

if($_SESSION['role'] !== 'admin') {
    if(!hasPermission($conn, $_SESSION['user_id'], 'import_export', 'import')) {
        header("Location: dashboard.php");
        exit();
    }
}

// ─── Clear import session data ───
if(isset($_GET['clear'])) {
    unset($_SESSION['import_processed']);
    unset($_SESSION['selected_columns']);
    unset($_SESSION['mapped_columns']);
    unset($_SESSION['valid_rows']);
    unset($_SESSION['errors_by_row']);
    unset($_SESSION['extra_cols']);
    unset($_SESSION['num_extra_cols']);
    unset($_SESSION['sample_row']);
    unset($_SESSION['scholarship_program_id']);
    unset($_SESSION['school_year']);
    unset($_SESSION['semester']);
    unset($_SESSION['file_name']);
    unset($_SESSION['data_rows']);
    unset($_SESSION['header_row_index']);
    unset($_SESSION['upload_error']);
    header("Location: upload_excel.php");
    exit();
}

// Autoload composer vendor (supports both root vendor and lib/vendor paths)
if (file_exists(__DIR__ . "/../vendor/autoload.php")) {
    require_once __DIR__ . "/../vendor/autoload.php";
} elseif (file_exists(__DIR__ . "/../lib/vendor/autoload.php")) {
    require_once __DIR__ . "/../lib/vendor/autoload.php";
} 

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ReaderException;

// ─── Variables & School Year / Semester Data ───
$errors_by_row = [];
$valid_rows = [];
$import_processed = false;
$scholarship_program_id = 0;
$file_name = "";
$extra_cols = false;
$num_extra_cols = 0;
$selected_columns = [];
$user_skip = isset($_POST['headers']) ? (int)$_POST['headers'] : 3;
$user_skip = max(0, $user_skip);

$available_years = getScholarshipSchoolYears($conn);
if (empty($available_years)) {
    $available_years = getAvailableSchoolYears($conn);
}
$selected_sy = $_POST['school_year'] ?? ($_SESSION['school_year'] ?? getSelectedSchoolYear());

$available_semesters = ['1st Semester', '2nd Semester', 'Summer'];
$selected_sem = $_POST['semester'] ?? ($_SESSION['semester'] ?? getSelectedSemester());

// ─── Field order ───
$column_order = [
    'student_id', 'philsys_id', 'lrn', 'four_ps_id',
    'last_name', 'given_name', 'ext_name', 'middle_name',
    'sex', 'birthdate', 'program', 'year_level',
    'father_last', 'father_given', 'father_middle',
    'mother_last', 'mother_given', 'mother_middle',
    'street', 'town_city', 'province', 'zipcode',
    'disability', 'contact_num', 'email', 'ip_group'
];

// ─── Column display names ───
$column_labels = [
    'student_id' => 'Student ID',
    'philsys_id' => 'PHILSYS ID',
    'lrn' => 'LRN',
    'four_ps_id' => "4P's ID",
    'last_name' => 'Last Name',
    'given_name' => 'Given Name',
    'ext_name' => 'Extension Name',
    'middle_name' => 'Middle Name',
    'sex' => 'Sex',
    'birthdate' => 'Birthdate',
    'program' => 'Program / Course',
    'year_level' => 'Year Level',
    'father_last' => "Father's Last Name",
    'father_given' => "Father's Given Name",
    'father_middle' => "Father's Middle Name",
    'mother_last' => "Mother's Last Name",
    'mother_given' => "Mother's Given Name",
    'mother_middle' => "Mother's Middle Name",
    'street' => 'Street & Barangay',
    'town_city' => 'Town / City',
    'province' => 'Province',
    'zipcode' => 'Zipcode',
    'disability' => 'Disability',
    'contact_num' => 'Contact Number',
    'email' => 'Email Address',
    'ip_group' => 'IP Group'
];

// ─── Default checked columns ───
$default_checked = [
    'student_id', 'philsys_id', 'lrn', 'four_ps_id',
    'last_name', 'given_name', 'ext_name', 'middle_name',
    'sex', 'birthdate', 'program', 'year_level',
    'father_last', 'father_given', 'father_middle',
    'mother_last', 'mother_given', 'mother_middle',
    'street', 'town_city', 'province', 'zipcode',
    'disability', 'contact_num', 'email', 'ip_group'
];

// ─── Helper: process rows with given mapping ───
function processRows($data_rows, $mapped_columns, $conn, $header_row_index, $school_year = '', $semester = '') {
    $valid_rows = [];
    $errors_by_row = [];
    foreach($data_rows as $row_idx => $rowData) {
        if(empty(array_filter($rowData))) continue;
        $rowErrors = validateFullRow($rowData, $conn, $mapped_columns, $school_year, $semester);
        if(empty($rowErrors)) {
            $valid_rows[] = $rowData;
        } else {
            $errors_by_row[] = [
                'row_number' => $row_idx + $header_row_index + 2,
                'row_error' => $rowErrors
            ];
        }
    }
    return [$valid_rows, $errors_by_row];
}

// ─── Handle form submission ───
if(isset($_POST['select_columns'])) {
    unset($_SESSION['upload_error']);

    if(isset($_POST['columns_present'])) {
        $_SESSION['selected_columns'] = $_POST['columns_present'];
    }

    $selected_columns = isset($_SESSION['selected_columns']) 
        ? $_SESSION['selected_columns'] 
        : $default_checked;

    $has_new_file = isset($_FILES['excel_file']) && $_FILES['excel_file']['error'] === UPLOAD_ERR_OK;

    if (isset($_POST['school_year'])) $_SESSION['school_year'] = trim($_POST['school_year']);
    if (isset($_POST['semester'])) $_SESSION['semester'] = trim($_POST['semester']);

    $current_sy = $_SESSION['school_year'] ?? '';
    $current_sem = $_SESSION['semester'] ?? '';

    if (!$has_new_file && isset($_SESSION['data_rows']) && !empty($_SESSION['data_rows'])) {
        $data_rows = $_SESSION['data_rows'];
        $header_row_index = $_SESSION['header_row_index'] ?? 3;
        $scholarship_program_id = $_SESSION['scholarship_program_id'] ?? 0;
        $file_name = $_SESSION['file_name'] ?? '';

        $mapped_columns = [];
        $excel_index = 1;
        foreach($column_order as $field) {
            if(in_array($field, $selected_columns)) {
                $mapped_columns[$field] = $excel_index;
                $excel_index++;
            }
        }
        $_SESSION['mapped_columns'] = $mapped_columns;

        list($valid_rows, $errors_by_row) = processRows($data_rows, $mapped_columns, $conn, $header_row_index, $current_sy, $current_sem);

        $max_columns = 0;
        foreach($data_rows as $rowData) {
            if(empty(array_filter($rowData))) continue;
            $last_non_empty = 0;
            foreach($rowData as $idx => $value) {
                $v_str = trim((string)$value);
                if(!empty($v_str) && strtolower($v_str) !== 'nan') {
                    $last_non_empty = $idx + 1;
                }
            }
            if($last_non_empty > $max_columns) {
                $max_columns = $last_non_empty;
            }
        }
        $mapped_count = count($mapped_columns) + 1;
        $extra_cols = ($max_columns > $mapped_count);
        $num_extra_cols = $extra_cols ? ($max_columns - $mapped_count) : 0;
        $sample_row = !empty($valid_rows) ? $valid_rows[0] : [];

        $_SESSION['valid_rows'] = $valid_rows;
        $_SESSION['errors_by_row'] = $errors_by_row;
        $_SESSION['extra_cols'] = $extra_cols;
        $_SESSION['num_extra_cols'] = $num_extra_cols;
        $_SESSION['sample_row'] = $sample_row;
        $_SESSION['import_processed'] = true;

        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    if($has_new_file) {
        $excel_file = $_FILES['excel_file'];
        $file_name = $excel_file['name'];
        $scholarship_program_id = (int) $_POST['scholarship_program'];

        if (empty($scholarship_program_id)) {
            $_SESSION['upload_error'] = 'Please select a scholarship program.';
            $_SESSION['import_processed'] = false;
        } else {
            $allowedMimeTypes = [
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.ms-excel',
                'application/vnd.oasis.opendocument.spreadsheet',
                'text/csv'
            ];
            $file_info = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($file_info, $excel_file['tmp_name']);
            if(!in_array($mime_type, $allowedMimeTypes)) {
                $_SESSION['upload_error'] = 'Invalid file type. Please upload a valid Excel file (.xlsx, .xls, .ods, .csv).';
                $_SESSION['import_processed'] = false;
            } else {
                try {
                    $spreadsheet = IOFactory::load($excel_file['tmp_name']);
                } catch (ReaderException $e) {
                    $_SESSION['upload_error'] = 'Error loading file: ' . $e->getMessage();
                    $_SESSION['import_processed'] = false;
                }

                if(!isset($_SESSION['upload_error'])) {
                    $worksheet = $spreadsheet->getActiveSheet();
                    foreach ($spreadsheet->getAllSheets() as $sheet) {
                        if ($sheet->getHighestRow() > $worksheet->getHighestRow()) {
                            $worksheet = $sheet;
                        }
                    }

                    $raw_all_rows = $worksheet->toArray();
                    
                    $all_rows = [];
                    foreach($raw_all_rows as $r_idx => $r_val) {
                        $c_row = [];
                        foreach($r_val as $c_val) {
                            if ($c_val === null || $c_val === '') {
                                $c_row[] = '';
                            } elseif (is_float($c_val) && floor($c_val) == $c_val) {
                                $c_row[] = (string)(int)$c_val;
                            } else {
                                $c_row[] = trim((string)$c_val);
                            }
                        }
                        $all_rows[] = $c_row;
                    }

                    $header_row_index = max(0, $user_skip - 1);
                    $data_rows = array_slice($all_rows, $user_skip);

                    $_SESSION['data_rows'] = $data_rows;
                    $_SESSION['header_row_index'] = $header_row_index;
                    $_SESSION['scholarship_program_id'] = $scholarship_program_id;
                    $_SESSION['file_name'] = $file_name;

                    $mapped_columns = [];
                    $excel_index = 1;
                    foreach($column_order as $field) {
                        if(in_array($field, $selected_columns)) {
                            $mapped_columns[$field] = $excel_index;
                            $excel_index++;
                        }
                    }
                    $_SESSION['mapped_columns'] = $mapped_columns;

                    list($valid_rows, $errors_by_row) = processRows($data_rows, $mapped_columns, $conn, $header_row_index, $current_sy, $current_sem);

                    $max_columns = 0;
                    foreach($data_rows as $rowData) {
                        if(empty(array_filter($rowData))) continue;
                        $last_non_empty = 0;
                        foreach($rowData as $idx => $value) {
                            $v_str = trim((string)$value);
                            if(!empty($v_str) && strtolower($v_str) !== 'nan') {
                                $last_non_empty = $idx + 1;
                            }
                        }
                        if($last_non_empty > $max_columns) {
                            $max_columns = $last_non_empty;
                        }
                    }
                    $mapped_count = count($mapped_columns) + 1;
                    $extra_cols = ($max_columns > $mapped_count);
                    $num_extra_cols = $extra_cols ? ($max_columns - $mapped_count) : 0;
                    $sample_row = !empty($valid_rows) ? $valid_rows[0] : [];

                    $_SESSION['valid_rows'] = $valid_rows;
                    $_SESSION['errors_by_row'] = $errors_by_row;
                    $_SESSION['extra_cols'] = $extra_cols;
                    $_SESSION['num_extra_cols'] = $num_extra_cols;
                    $_SESSION['sample_row'] = $sample_row;
                    $_SESSION['import_processed'] = true;

                    header("Location: " . $_SERVER['PHP_SELF']);
                    exit();
                }
            }
        }
    }

    if (!isset($_SESSION['upload_error'])) {
        $_SESSION['upload_error'] = 'No file uploaded or session data missing. Please upload a file again.';
    }
    $_SESSION['import_processed'] = false;
}

if(isset($_POST['confirm'])) {
    header("Location: ../functions/admin/upload_data_insert.php");
    exit();
}

$upload_error = $_SESSION['upload_error'] ?? null;
unset($_SESSION['upload_error']);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Import Scholar Records - PIT ScholarshipHub</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/upload_excel.css?v=<?= filemtime('../assets/css/upload_excel.css') ?>">
    <link rel="icon" href="../assets/images/PIT%20LOGO.png">
    <link rel="stylesheet" href="../assets/css/modals.css">
    <style>
        .filter-summary-box {
            background: rgba(30, 70, 40, 0.05);
            border: 1px solid rgba(30, 70, 40, 0.2);
            border-radius: 6px;
            padding: 12px 16px;
            margin-bottom: 20px;
        }
        .filter-summary-box h4 {
            color: #1e4628;
            font-size: 14px;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .filter-summary-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 8px 12px;
        }
        .filter-chip {
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid #c2d6c6;
            border-radius: 4px;
            padding: 6px 12px;
            font-size: 13px;
            color: #2c3e50;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        }
        .filter-chip strong {
            color: #1e4628;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . "/../includes/modal_alerts.php"; ?>

    <!-- Loading Modal -->
    <div id="importLoadingModal" class="sys-modal">
        <div class="sys-modal-content" style="text-align: center; padding: 35px 20px;">
            <div class="sys-spinner"></div>
            <h3 style="margin-top: 18px; color: #1e4628; font-size: 18px;">Processing Import Data...</h3>
            <p style="color: #666; font-size: 13px; margin-top: 6px;">Please wait while the system validates and imports scholar records.</p>
        </div>
    </div>

    <?php if(!isset($_SESSION['user_id']) && $_SESSION['role'] !== 'admin') :
        $_SESSION['fail'] = "You are not logged in!";
        header("Location: ../portal.php");
        exit();  
    else : ?>
        <div class="container">

            <?php include __DIR__ . "/../includes/navigation.php"; ?>

            <!-- Page Content -->
            <div class="page-content">
                <div class="page-header">
                    <h2>Import Scholar Records</h2>
                </div>

                <?php if($upload_error): ?>
                    <div class="error-message" style="background:#f8d7da; border:1px solid #f5c6cb; color:#721c24; padding:12px 15px; border-radius:4px; margin-bottom:20px;">
                        <strong>Error:</strong> <?= htmlspecialchars($upload_error) ?>
                    </div>
                <?php endif; ?>

                <?php if(!isset($_SESSION['import_processed']) || $_SESSION['import_processed'] !== true): ?>
                <!-- Step 1: Upload Form -->
                <div class="upload-section">
                    <h3>Step 1: Upload Your Excel File</h3>
                    <form action="<?= $_SERVER['PHP_SELF'] ?>" method="POST" enctype="multipart/form-data" class="upload-form" onsubmit="showImportLoading()">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="upload">Upload Excel File <span class="required">*</span></label>
                                <input type="file" name="excel_file" accept=".xls, .xlsx, .csv" id="upload" required/>
                            </div>
                            <div class="form-group">
                                <label for="headers">Number of Rows to Skip (Headers) <span class="required">*</span></label>
                                <input type="number" name="headers" value="<?= $user_skip ?>" id="headers" min="0" required/>
                                <small>Enter the number of rows to skip (e.g., 3 for a 3‑row header)</small>
                            </div>

                            <!-- School Year Selector -->
                            <div class="form-group">
                                <label for="school_year">School Year <span class="required">*</span></label>
                                <select name="school_year" id="school_year" required onchange="filterProgramsBySYAndSemester()">
                                    <?php foreach ($available_years as $yr) : ?>
                                        <option value="<?= htmlspecialchars($yr) ?>" <?= $yr === $selected_sy ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($yr) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Semester Selector -->
                            <div class="form-group">
                                <label for="semester">Semester <span class="required">*</span></label>
                                <select name="semester" id="semester" required onchange="filterProgramsBySYAndSemester()">
                                    <?php foreach ($available_semesters as $sem) : ?>
                                        <option value="<?= htmlspecialchars($sem) ?>" <?= $sem === $selected_sem ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($sem) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Scholarship Program Selector (Filtered by SY & Semester) -->
                            <div class="form-group">
                                <label for="scholarship">Scholarship Program <span class="required">*</span></label>
                                <select name="scholarship_program" id="scholarship_program" required>
                                    <option value="" disabled selected>Select Scholarship Program</option>
                                    <?php 
                                    $sql = "SELECT sp.id, sp.name, sp.code, sp.school_year, 
                                                   GROUP_CONCAT(DISTINCT si.semester SEPARATOR ',') AS semesters 
                                            FROM scholarship_program sp 
                                            LEFT JOIN scholar_info si ON si.scholarship_program_id = sp.id AND si.is_archived = 0 
                                            WHERE sp.is_archived = 0 AND sp.is_active = 1 
                                            GROUP BY sp.id, sp.name, sp.code, sp.school_year 
                                            ORDER BY sp.name ASC";
                                    $stmt = $conn->prepare($sql);
                                    $stmt->execute();
                                    $results = $stmt->get_result();
                                    while($row = $results->fetch_assoc()): ?>
                                        <option value="<?= $row['id'] ?>" 
                                                data-sy="<?= htmlspecialchars($row['school_year']) ?>" 
                                                data-sem="<?= htmlspecialchars($row['semesters'] ?? '') ?>">
                                            <?= htmlspecialchars($row['name']) ?> (<?= htmlspecialchars($row['school_year']) ?>)
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                                <small id="no_program_msg" style="color: #d9534f; display: none; margin-top: 4px;">
                                    No active scholarship programs found for the selected School Year and Semester.
                                </small>
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="submit" name="select_columns" class="btn btn-success">Next</button>
                        </div>
                    </form>
                </div>

                <!-- Instructions -->
                <div class="instructions-section">
                    <h4>Instructions</h4>
                    <ul>
                        <!-- <li>Use the template provided by the scholarship office.
                            <a href="../assets/TEMPLATE.xlsx" download>Download Template</a>
                        </li> -->
                        <li>Make sure the file format is <strong>.xlsx</strong>, <strong>.xls</strong>, or <strong>.csv</strong>.</li>
                        <li>After uploading, you will be able to select which columns are present in your file.</li>
                        <li><strong>Skip rows:</strong> Enter the number of top rows that contain headers. Data rows will start after that many rows.</li>
                    </ul>
                </div>

                <?php else: ?>
                <!-- Step 2: Column Selection & Preview -->
                <div class="column-selection-section">
                    <h3>Step 2: Select Columns Present in Your File</h3>
                    <p style="margin-bottom: 15px;">Check only the columns that exist in your Excel file. They will be mapped in the order they appear (left to right).</p>
                    
                    <?php
                    // Retrieve selected scholarship program details
                    $sp_display_name = 'N/A';
                    if (!empty($_SESSION['scholarship_program_id'])) {
                        $sp_stmt = $conn->prepare("SELECT name, code FROM scholarship_program WHERE id = ?");
                        $sp_stmt->bind_param("i", $_SESSION['scholarship_program_id']);
                        $sp_stmt->execute();
                        $sp_res = $sp_stmt->get_result();
                        if ($sp_row = $sp_res->fetch_assoc()) {
                            $sp_display_name = $sp_row['name'] . (!empty($sp_row['code']) ? ' (' . $sp_row['code'] . ')' : '');
                        }
                        $sp_stmt->close();
                    }
                    ?>

                    <!-- Selected Targets & Filters Summary Box -->
                    <div class="filter-summary-box">
                        <h4>Selected Import Targets:</h4>
                        <div class="filter-summary-grid">
                            <div class="filter-chip"><strong>Scholarship Program:</strong> <?= htmlspecialchars($sp_display_name) ?></div>
                            <div class="filter-chip"><strong>School Year:</strong> <?= htmlspecialchars($_SESSION['school_year'] ?? 'N/A') ?></div>
                            <div class="filter-chip"><strong>Semester:</strong> <?= htmlspecialchars($_SESSION['semester'] ?? 'N/A') ?></div>
                            <div class="filter-chip"><strong>Uploaded File:</strong> <?= htmlspecialchars($_SESSION['file_name'] ?? 'N/A') ?></div>
                            <div class="filter-chip"><strong>Rows Skipped:</strong> <?= isset($_SESSION['header_row_index']) ? ((int)$_SESSION['header_row_index'] + 1) : 'N/A' ?></div>
                        </div>
                    </div>

                    <form action="<?= $_SERVER['PHP_SELF'] ?>" method="POST" onsubmit="showImportLoading()">
                        <div class="checkbox-grid">
                            <?php 
                            $selected = isset($_SESSION['selected_columns']) ? $_SESSION['selected_columns'] : $default_checked;
                            foreach($column_labels as $key => $label): ?>
                                <div class="checkbox-group">
                                    <input type="checkbox" 
                                           name="columns_present[]" 
                                           value="<?= $key ?>" 
                                           id="col_<?= $key ?>"
                                           <?= in_array($key, $selected) ? 'checked' : '' ?>>
                                    <label for="col_<?= $key ?>"><?= $label ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="form-actions" style="margin-top: 20px;">
                            <button type="submit" name="select_columns" class="btn btn-success">Update Mapping & Validate</button>
                            <a href="?clear=1" class="btn btn-secondary">Change File / Reset</a>
                        </div>
                    </form>
                </div>

                <?php endif; ?>

                <!-- Import Summary -->
                <?php if(isset($_SESSION['import_processed']) && $_SESSION['import_processed'] === true): 
                    $errors_by_row = $_SESSION['errors_by_row'] ?? [];
                    $valid_rows = $_SESSION['valid_rows'] ?? [];
                    $extra_cols = $_SESSION['extra_cols'] ?? false;
                    $num_extra_cols = $_SESSION['num_extra_cols'] ?? 0;
                    $sample_row = $_SESSION['sample_row'] ?? [];
                ?>
                    <div class="summary-section">
                        <h3>Import Summary</h3>
                        <div class="summary-stats">
                            <div class="stat-item success">
                                <span class="stat-label">Valid Rows</span>
                                <span class="stat-number"><?= count($valid_rows) ?></span>
                            </div>
                            <div class="stat-item error">
                                <span class="stat-label">Rows with Errors</span>
                                <span class="stat-number"><?= count($errors_by_row) ?></span>
                            </div>
                        </div>
                        
                        <?php if(!empty($errors_by_row)): ?>
                            <div class="error-table-wrapper">
                                <h4>Error Details</h4>
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Row</th>
                                            <th>Field</th>
                                            <th>Error</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($errors_by_row as $rowError):
                                            foreach($rowError['row_error'] as $field => $fieldError):
                                                foreach($fieldError as $error): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($rowError['row_number']) ?></td>
                                                    <td><?= htmlspecialchars($field) ?></td>
                                                    <td><?= htmlspecialchars($error) ?></td>
                                                </tr>
                                        <?php endforeach;
                                            endforeach;
                                        endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>

                        <!-- Confirmation Form -->
                        <div class="confirm-section">
                            <form action="../functions/admin/upload_data_insert.php" method="POST" onsubmit="showImportLoading()">
                                <?php if($extra_cols) : ?>
                                    <div class="extra-columns-section">
                                        <h4>Extra Columns Detected!</h4>
                                        <p class="help-text">These columns are beyond the mapped columns. Please provide a name for each, or they will be stored as Others.</p>
                                        <table>
                                            <thead>
                                                <tr>
                                                    <th>Column Data (Sample)</th>
                                                    <th>Column Name</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php 
                                                $mapped_count = count($_SESSION['mapped_columns'] ?? []) + 1;
                                                for($i = $mapped_count; $i < $mapped_count + $num_extra_cols; $i++) : 
                                                    $sample_value = $sample_row[$i] ?? "";
                                                ?>
                                                <tr>
                                                    <td>
                                                        <strong>Column <?= $i + 1 ?></strong>
                                                        <br>
                                                        <small>Sample: <?= htmlspecialchars($sample_value) ?></small>
                                                    </td>
                                                    <td>
                                                        <input type="text" name="column_names[<?= $i ?>]" placeholder="Enter column name" required/>
                                                    </td>
                                                </tr>
                                                <?php endfor; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>

                                <input type="hidden" name="scholarship_program_id" value="<?= $_SESSION['scholarship_program_id'] ?>" />
                                <input type="hidden" name="school_year" value="<?= htmlspecialchars($_SESSION['school_year'] ?? '') ?>" />
                                <input type="hidden" name="semester" value="<?= htmlspecialchars($_SESSION['semester'] ?? '') ?>" />
                                <input type="hidden" name="valid_rows" value="<?= urlencode(json_encode($_SESSION['valid_rows'])) ?>" />
                                <input type="hidden" name="file_name" value="<?= htmlspecialchars($_SESSION['file_name']) ?>" />

                                <div class="form-actions">
                                    <?php if(count($_SESSION['valid_rows']) > 0) : ?>
                                        <button type="submit" name="confirm" class="btn btn-success">Add Scholar Records</button>
                                    <?php endif; ?>
                                    <a href="?clear=1" class="btn btn-secondary">Cancel</a>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Import History -->
                <div class="history-section">
                    <h3>Import History</h3>
                    <?php 
                    $history_sql = "SELECT 
                                    import_history.imported_at, 
                                    import_history.filename, 
                                    COALESCE(import_history.role, scholarship_users.role, 'N/A') AS role, 
                                    COALESCE(NULLIF(import_history.user_name, ''), CONCAT_WS(' ', scholarship_users.given_name, scholarship_users.middle_name, scholarship_users.last_name), 'Deleted User') AS name
                                    FROM import_history 
                                    LEFT JOIN 
                                        scholarship_users ON import_history.user_id = scholarship_users.id 
                                    ORDER BY import_history.imported_at DESC";
                    $history_stmt = $conn->prepare($history_sql);
                    ?>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>File Name</th>
                                    <th>Imported At</th>
                                    <th>Name</th>
                                    <th>Role</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if($history_stmt->execute()) :
                                    $history_result = $history_stmt->get_result();
                                    if($history_result->num_rows > 0) :
                                        while($row = $history_result->fetch_assoc()) : ?>
                                            <tr>
                                                <td><?= htmlspecialchars($row['filename']) ?></td>
                                                <td><?= htmlspecialchars($row['imported_at']) ?></td>
                                                <td><?= htmlspecialchars($row['name']) ?></td>
                                                <td><?= htmlspecialchars(ucfirst($row['role'])) ?></td>
                                            </tr>
                                        <?php endwhile;
                                    else:?>
                                        <tr>
                                            <td colspan="4" class="no-data">No import history found.</td>
                                        </tr>
                                    <?php endif;
                                else :?>
                                    <tr>
                                        <td colspan="4" class="no-data">Error loading import history.</td>
                                    </tr>
                                <?php endif;?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>

            <!-- Footer -->
            <div class="footer">
                © 2026 PIT ScholarshipHub. All rights reserved.
            </div>
        </div>
    <?php endif; ?> 

    <script>
    function showImportLoading() {
        document.getElementById('importLoadingModal').classList.add('sys-show');
    }

    function filterProgramsBySYAndSemester() {
        const sySelect = document.getElementById('school_year');
        const semSelect = document.getElementById('semester');
        const programSelect = document.getElementById('scholarship_program');
        const noProgMsg = document.getElementById('no_program_msg');
        
        if (!sySelect || !semSelect || !programSelect) return;

        const selectedSY = sySelect.value.replace(/\s+/g, '').toLowerCase();
        const selectedSem = semSelect.value.replace(/\s+/g, '').toLowerCase();

        let visibleCount = 0;

        Array.from(programSelect.options).forEach(opt => {
            if (opt.value === "") return;

            const progSY = (opt.getAttribute('data-sy') || '').replace(/\s+/g, '').toLowerCase();
            const progSem = (opt.getAttribute('data-sem') || '').replace(/\s+/g, '').toLowerCase();

            const syMatch = (progSY === selectedSY);
            const semMatch = (!progSem || progSem.includes(selectedSem));

            if (syMatch && semMatch) {
                opt.style.display = "";
                opt.disabled = false;
                visibleCount++;
            } else {
                opt.style.display = "none";
                opt.disabled = true;
            }
        });

        if (visibleCount === 0) {
            programSelect.value = "";
            if (noProgMsg) noProgMsg.style.display = "block";
        } else {
            if (noProgMsg) noProgMsg.style.display = "none";
            if (programSelect.selectedOptions.length === 0 || programSelect.selectedOptions[0].disabled) {
                programSelect.value = "";
            }
        }
    }

    document.addEventListener("DOMContentLoaded", filterProgramsBySYAndSemester);
    </script>
</body>
</html>