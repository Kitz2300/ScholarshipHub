<?php 
include __DIR__ . '/../includes/conn.php';
include __DIR__ . '/../includes/check_permission.php';
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
    unset($_SESSION['file_name']);
    unset($_SESSION['data_rows']);
    unset($_SESSION['header_row_index']);
    unset($_SESSION['upload_error']);
    header("Location: upload_excel.php");
    exit();
}

require '../lib/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ReaderException;

// ─── Variables ───
$errors_by_row = [];
$valid_rows = [];
$import_processed = false;
$scholarship_program_id = 0;
$file_name = "";
$extra_cols = false;
$num_extra_cols = 0;
$selected_columns = [];
// Get the user's skip count from POST, default to 3 if not set
$user_skip = isset($_POST['headers']) ? (int)$_POST['headers'] : 3;
$user_skip = max(0, $user_skip); // ensure non-negative

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
function processRows($data_rows, $mapped_columns, $conn, $header_row_index) {
    $valid_rows = [];
    $errors_by_row = [];
    foreach($data_rows as $row_idx => $rowData) {
        if(empty(array_filter($rowData))) continue;
        $rowErrors = validateFullRow($rowData, $conn, $mapped_columns);
        if(empty($rowErrors)) {
            $valid_rows[] = $rowData;
        } else {
            $errors_by_row[] = [
                'row_number' => $row_idx + $header_row_index + 2,  // correct for skipped rows
                'row_error' => $rowErrors
            ];
        }
    }
    return [$valid_rows, $errors_by_row];
}

// ─── Handle form submission ───
if(isset($_POST['select_columns'])) {
    // Clear previous error
    unset($_SESSION['upload_error']);

    // 1. Update session with selected columns
    if(isset($_POST['columns_present'])) {
        $_SESSION['selected_columns'] = $_POST['columns_present'];
    }

    // 2. Determine which columns to use for mapping
    $selected_columns = isset($_SESSION['selected_columns']) 
        ? $_SESSION['selected_columns'] 
        : $default_checked;

    // ─── Check if we have a new file upload ───
    $has_new_file = isset($_FILES['excel_file']) && $_FILES['excel_file']['error'] === UPLOAD_ERR_OK;

    // If no new file, but we have session data, reuse it
    if (!$has_new_file && isset($_SESSION['data_rows']) && !empty($_SESSION['data_rows'])) {
        // Reuse existing data
        $data_rows = $_SESSION['data_rows'];
        $header_row_index = $_SESSION['header_row_index'] ?? 3; // fallback
        $scholarship_program_id = $_SESSION['scholarship_program_id'] ?? 0;
        $file_name = $_SESSION['file_name'] ?? '';

        // Build mapping
        $mapped_columns = [];
        $excel_index = 1;
        foreach($column_order as $field) {
            if(in_array($field, $selected_columns)) {
                $mapped_columns[$field] = $excel_index;
                $excel_index++;
            }
        }
        $_SESSION['mapped_columns'] = $mapped_columns;

        // Process rows
        list($valid_rows, $errors_by_row) = processRows($data_rows, $mapped_columns, $conn, $header_row_index);

        // ─── Determine max columns (ignoring trailing empty columns) ───
        $max_columns = 0;
        foreach($data_rows as $rowData) {
            if(empty(array_filter($rowData))) continue;
            // Find the last non-empty cell in this row
            $last_non_empty = 0;
            foreach($rowData as $idx => $value) {
                if(!empty(trim($value))) {
                    $last_non_empty = $idx + 1; // convert to 1-based count
                }
            }
            if($last_non_empty > $max_columns) {
                $max_columns = $last_non_empty;
            }
        }
        $mapped_count = count($mapped_columns) + 1; // +1 for SEQ
        $extra_cols = ($max_columns > $mapped_count);
        $num_extra_cols = $extra_cols ? ($max_columns - $mapped_count) : 0;
        $sample_row = !empty($valid_rows) ? $valid_rows[0] : [];

        $_SESSION['valid_rows'] = $valid_rows;
        $_SESSION['errors_by_row'] = $errors_by_row;
        $_SESSION['extra_cols'] = $extra_cols;
        $_SESSION['num_extra_cols'] = $num_extra_cols;
        $_SESSION['sample_row'] = $sample_row;
        $_SESSION['import_processed'] = true;

        // Redirect to clear POST and avoid resubmission
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    // ─── New file upload ───
    if($has_new_file) {
        $excel_file = $_FILES['excel_file'];
        $file_name = $excel_file['name'];
        $scholarship_program_id = (int) $_POST['scholarship_program'];

        if (empty($scholarship_program_id)) {
            $_SESSION['upload_error'] = 'Please select a scholarship program.';
            $_SESSION['import_processed'] = false;
        } else {
            // Validate file type
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
                    $all_rows = $worksheet->toArray();

                    // ─── Use the user's input for skip count ───
                    // $user_skip is already defined from POST
                    // Last row to skip (0-based) = user_skip - 1
                    $header_row_index = max(0, $user_skip - 1);

                    // ─── Get data rows (starting at index = user_skip) ───
                    $data_rows = array_slice($all_rows, $user_skip);

                    // ─── Store for later reuse ───
                    $_SESSION['data_rows'] = $data_rows;
                    $_SESSION['header_row_index'] = $header_row_index; // used for error row numbering
                    $_SESSION['scholarship_program_id'] = $scholarship_program_id;
                    $_SESSION['file_name'] = $file_name;

                    // ─── Build mapping using selected columns ───
                    $mapped_columns = [];
                    $excel_index = 1;
                    foreach($column_order as $field) {
                        if(in_array($field, $selected_columns)) {
                            $mapped_columns[$field] = $excel_index;
                            $excel_index++;
                        }
                    }
                    $_SESSION['mapped_columns'] = $mapped_columns;

                    // ─── Process rows ───
                    list($valid_rows, $errors_by_row) = processRows($data_rows, $mapped_columns, $conn, $header_row_index);

                    // ─── Determine max columns (ignoring trailing empties) ───
                    $max_columns = 0;
                    foreach($data_rows as $rowData) {
                        if(empty(array_filter($rowData))) continue;
                        // Find the last non-empty cell in this row
                        $last_non_empty = 0;
                        foreach($rowData as $idx => $value) {
                            if(!empty(trim($value))) {
                                $last_non_empty = $idx + 1;
                            }
                        }
                        if($last_non_empty > $max_columns) {
                            $max_columns = $last_non_empty;
                        }
                    }
                    $mapped_count = count($mapped_columns) + 1; // +1 for SEQ
                    $extra_cols = ($max_columns > $mapped_count);
                    $num_extra_cols = $extra_cols ? ($max_columns - $mapped_count) : 0;
                    $sample_row = !empty($valid_rows) ? $valid_rows[0] : [];

                    $_SESSION['valid_rows'] = $valid_rows;
                    $_SESSION['errors_by_row'] = $errors_by_row;
                    $_SESSION['extra_cols'] = $extra_cols;
                    $_SESSION['num_extra_cols'] = $num_extra_cols;
                    $_SESSION['sample_row'] = $sample_row;
                    $_SESSION['import_processed'] = true;

                    // Redirect to clear POST and avoid resubmission
                    header("Location: " . $_SERVER['PHP_SELF']);
                    exit();
                }
            }
        }
    }

    // If we reach here, something went wrong (no file and no session data)
    if (!isset($_SESSION['upload_error'])) {
        $_SESSION['upload_error'] = 'No file uploaded or session data missing. Please upload a file again.';
    }
    $_SESSION['import_processed'] = false;
}

// ─── If user confirmed insertion ───
if(isset($_POST['confirm'])) {
    header("Location: ../functions/admin/upload_data_insert.php");
    exit();
}

// ─── Show error message if any ───
$upload_error = $_SESSION['upload_error'] ?? null;
unset($_SESSION['upload_error']);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Import Excel - PIT ScholarshipHub</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/upload_excel.css?v=<?= filemtime('../assets/css/upload_excel.css') ?>">
    <link rel="icon" href="../assets/images/PIT%20LOGO.png">
</head>
<body>
    <?php 
    if(!isset($_SESSION['user_id']) && $_SESSION['role'] !== 'admin') : ?>
        <script>alert('You are not logged in')</script>
        <script>window.location.href='login.php'</script>
    <?php else : ?>
        <div class="container">
            <!-- Header -->
            <div class="header">
                <div class="header-left">
                    <img src="../assets/images/PIT%20LOGO.png" alt="PIT Logo">
                    <div>
                        <h1>PIT ScholarshipHub</h1>
                        <p>Import Excel</p>
                    </div>
                </div>
                <div class="header-right">
                    <span class="welcome-text">Welcome, Admin!</span>
                    <form action="../functions/logout.php" method="post" style="display: inline;">
                        <button type="submit" class="logout-btn" onclick="return confirm('Logout?')">Logout</button>
                    </form>
                </div>
            </div>

            <?php include __DIR__ . "/../includes/navigation.php"; ?>

            <!-- Page Content -->
            <div class="page-content">
                <div class="page-header">
                    <h2>Upload Excel File</h2>
                    <!-- <div class="page-actions">
                        <a href="scholar_list.php" class="btn btn-secondary">← Back to Scholars</a>
                    </div> -->
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
                    <form action="<?= $_SERVER['PHP_SELF'] ?>" method="POST" enctype="multipart/form-data" class="upload-form">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="upload">Import Excel File <span class="required">*</span></label>
                                <input type="file" name="excel_file" accept=".xls, .xlsx" id="upload" required/>
                            </div>
                            <div class="form-group">
                                <label for="headers">Number of Rows to Skip (Headers) <span class="required">*</span></label>
                                <input type="number" name="headers" value="<?= $user_skip ?>" id="headers" min="0" required/>
                                <small>Enter the number of rows to skip (e.g., 3 for a 3‑row header)</small>
                            </div>
                            <div class="form-group">
                                <label for="scholarship">Scholarship Program <span class="required">*</span></label>
                                <select name="scholarship_program" required>
                                    <option value="" disabled selected>Select Scholarship Program</option>
                                    <?php 
                                    $sql = "SELECT id, name, school_year FROM scholarship_program";
                                    $stmt = $conn->prepare($sql);
                                    $stmt->execute();
                                    $results = $stmt->get_result();
                                    while($row = $results->fetch_assoc()): ?>
                                        <option value="<?= $row['id'] ?>"><?= htmlspecialchars($row['name']) ?> (<?= $row['school_year'] ?>)</option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="submit" name="select_columns" class="btn btn-success">Next: Select Columns</button>
                        </div>
                    </form>
                </div>

                <!-- Instructions -->
                <div class="instructions-section">
                    <h4>Instructions</h4>
                    <ul>
                        <li>Use the template provided by the scholarship office.
                            <a href="../assets/TEMPLATE.xlsx" download>Download Template</a>
                        </li>
                        <!-- <li>Required Columns: Student ID, Last Name, Given Name, Sex, Birthdate, Program, Year Level</li> -->
                        <li>Make sure the file format is <strong>.xlsx</strong> or <strong>.xls</strong>.</li>
                        <li>After uploading, you will be able to select which columns are present in your file.</li>
                        <li><strong>Skip rows:</strong> Enter the number of top rows that contain headers. Data rows will start after that many rows.</li>
                        <!-- <li><strong>Skip rows:</strong> Enter the number of top rows that contain headers or extra information. Data rows will start after that many rows.</li> -->
                    </ul>
                </div>

                <?php else: ?>
                <!-- Step 2: Column Selection & Preview -->
                <div class="column-selection-section">
                    <h3>Step 2: Select Columns Present in Your File</h3>
                    <p>Check only the columns that exist in your Excel file. They will be mapped in the order they appear (left to right).</p>
                    
                    <form action="<?= $_SERVER['PHP_SELF'] ?>" method="POST">
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
                        <div class="form-actions">
                            <button type="submit" name="select_columns" class="btn btn-success">Update Mapping & Validate</button>
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
                            <form action="../functions/admin/upload_data_insert.php" method="POST">
                                <?php if($extra_cols) : ?>
                                    <div class="extra-columns-section">
                                        <h4>Extra Columns Detected!</h4>
                                        <p class="help-text">These columns are beyond the mapped columns. Please provide a name for each, or they will be stored as notes.</p>
                                        <table>
                                            <thead>
                                                <tr>
                                                    <th>Column Data (Sample)</th>
                                                    <th>Column Name</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php 
                                                $mapped_count = count($_SESSION['mapped_columns'] ?? []) + 1; // +1 for SEQ
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
                                <input type="hidden" name="valid_rows" value="<?= urlencode(json_encode($_SESSION['valid_rows'])) ?>" />
                                <input type="hidden" name="file_name" value="<?= htmlspecialchars($_SESSION['file_name']) ?>" />

                                <div class="form-actions">
                                    <?php if(count($_SESSION['valid_rows']) > 0) : ?>
                                        <button type="submit" name="confirm" class="btn btn-success">Add Scholar Records</button>
                                    <?php endif; ?>
                                    <!-- Cancel button clears session -->
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
                                    imported_at, 
                                    filename, 
                                    scholarship_users.role, 
                                    CONCAT_WS(' ', scholarship_users.given_name, scholarship_users.middle_name, scholarship_users.last_name) AS name
                                    FROM import_history 
                                    LEFT JOIN 
                                        scholarship_users ON import_history.user_id = scholarship_users.id 
                                    ORDER BY imported_at DESC";
                    $history_stmt = $conn->prepare($history_sql);
                    ?>
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
                                            <td><?= htmlspecialchars($row['role']) ?></td>
                                        </tr>
                                    <?php endwhile;
                                else:?>
                                    <tr>
                                        <td colspan="3" class="no-data">No import history found.</td>
                                    </tr>
                                <?php endif;
                            else :?>
                                <tr>
                                    <td colspan="3" class="no-data">Error loading import history.</td>
                                </tr>
                            <?php endif;?>
                        </tbody>
                    </table>
                </div>

            </div>

            <!-- Footer -->
            <div class="footer">
                © 2026 PIT ScholarshipHub. All rights reserved.
            </div>
        </div>
    <?php endif; ?> 
</body>
</html>