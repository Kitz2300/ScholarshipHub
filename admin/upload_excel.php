<?php 
include __DIR__ . '/../includes/conn.php';
include __DIR__ . '/../functions/admin/upload_validation.php';
session_start();

require '../lib/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ReaderException;

// declare variables for the data, invalid rows and valid rows
$rows = [];
$errors_by_row = [];
$valid_rows = [];
$import_processed = false;
$scholarship_program_id = 0;
$file_name = "";
$extra_cols = false;
$num_extra_cols = 0;

// check if file is uploadeds
if(isset($_FILES['excel_file']) && isset($_POST['headers'])) {
    $excel_file = $_FILES['excel_file'];
    $skip_rows = (int) $_POST['headers'];
    $file_name = $excel_file['name'];
    $scholarship_program_id = (int) $_POST['scholarship_program'];

    // check if a scholarship program has been selected
    if (empty($scholarship_program_id)) {
        die('A Scholarship program has not been selected');
    }

    // check for upload errors
    if ($excel_file['error'] !== UPLOAD_ERR_OK) {
        die('Upload failed with error code: ' . $excel_file['error']);
    }

    // file type validation
    $allowedMimeTypes = [
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-excel',
        'application/vnd.oasis.opendocument.spreadsheet',
        'text/csv'
    ];

    $file_info = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($file_info, $excel_file['tmp_name']);

    if(!in_array($mime_type, $allowedMimeTypes)) {
        die('Invalid file type. Please upload a valid Excel File.');
    }

    // catch error if file fails to load
    try {
        $spreadsheet = IOFactory::load($excel_file['tmp_name']);
    } catch (ReaderException $e) {
        die('Error Loading File: ' . $e->getMessage());
    }

    // get worksheet
    $worksheet = $spreadsheet->getActiveSheet();
    
    // convert worksheet to array slice array to remove header rows
    $rows = $worksheet->toArray();
    $rows = array_slice($rows, $skip_rows);

    // initialize variable for extra columns
    $max_columns = 0;
    $mapped_columns = 22;

    foreach($rows as $row => $rowData) {
        // skip row if whole row is empty
        if(empty(array_filter($rowData))) {
            continue;
        }

        // count columns per row for checking extra columns
        $column_count = count($rowData);
        if($column_count > $max_columns) {
            $max_columns = $column_count;
        }

        // validate each row
        $rowErrors = validateFullRow($rowData, $conn);

        // append valid rows to valid_rows array if there are no errors, otherwise append row error to errors_by_row array
        if(empty($rowErrors)) {
            $valid_rows[] = $rowData;
        } else {
            $errors_by_row[] = [
                'row_number' => $row + 1,
                'row_error' => $rowErrors
            ];
        }
    }
    
    // set true if process is successful
    $import_processed = true;  

    // check if there are valid rows
    if(!empty($valid_rows)) {
        // check if max columns is greater than mapped columns
        if($max_columns > $mapped_columns) {
            $extra_cols = true;
            $num_extra_cols = $max_columns - $mapped_columns;
            }
        // get sample row
        $sample_row = $valid_rows[0];
    } else {
        $sample_row = [];
    }

}

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
    <!-- check if Logged in or not -->
    <?php 
    if(!isset($_SESSION['user_id']) && $_SESSION['role'] !== 'admin') : ?>
        <!-- if not logged in, redirect user to login page -->
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

            <!-- Navigation -->
            <nav class="nav-bar">
                <ul>
                    <li><a href="dashboard.php">Dashboard</a></li>
                    <li><a href="scholar_list.php">Scholars</a></li>
                    <li><a href="announcements.php">Announcements</a></li>
                    <li><a href="scholarship_program.php">Scholarship Programs</a></li>
                    <li><a href="upload_excel.php" class="active">Import Excel</a></li>
                    <li><a href="export_scholars.php">Export Excel</a></li>
                    <li><a href="archive.php">Archives</a></li>
                </ul>
            </nav>

            <!-- Page Content -->
            <div class="page-content">
                <!-- Page Header -->
                <div class="page-header">
                    <h2>Upload Excel File</h2>
                    <div class="page-actions">
                        <a href="scholar_list.php" class="btn btn-secondary">← Back to Scholars</a>
                    </div>
                </div>

                <!-- form for uploading excel file -->
                <div class="upload-section">
                    <h3>Step 1: Upload Your Excel File</h3>
                    <form action="<?= $_SERVER['PHP_SELF'] ?>" method="POST" enctype="multipart/form-data" class="upload-form">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="upload">Import Excel File <span class="required">*</span></label>
                                <input type="file" name="excel_file" accept=".xls, .xlsx" id="upload" required/>
                            </div>
                            <div class="form-group">
                                <label for="headers">How many rows as headers? <span class="required">*</span></label>
                                <input type="number" name="headers" id="headers" min="0" value="4" required/>
                            </div>
                            <div class="form-group">
                                <label for="scholarship">Scholarship Program <span class="required">*</span></label>
                                <select name="scholarship_program" required>
                                    <option value="" disabled selected>Select Scholarship Program</option>
                                    <?php 
                                    $sql = "SELECT id, name FROM scholarship_program";
                                    $stmt = $conn->prepare($sql);
                                    $stmt->execute();
                                    $results = $stmt->get_result();
                                    while($row = $results->fetch_assoc()): ?>
                                        <option value="<?= $row['id'] ?>"><?= htmlspecialchars($row['name']) ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-success">Import</button>
                        </div>
                    </form>
                </div>

                <!-- instructions for file requirement -->
                <div class="instructions-section">
                    <h4>Instructions</h4>
                    <ul>
                        <li>Use the template provided by the scholarship office.</li>
                        <li>Required Columns: Student ID, Last Name, Given Name, Sex, Birthdate, Program, Year Level</li>
                        <li>Make sure the file format is <strong>.xlsx</strong> or <strong>.xls</strong>.</li>
                        <li>If you have extra columns, you will be prompted to name them.</li>
                    </ul>
                </div>

                <?php if($import_processed): ?>
                    <?php if(!empty($errors_by_row) || !empty($valid_rows)): ?>
                        <!-- import summary -->
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
                                <!-- display row errors -->
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
                                            <?php foreach($errors_by_row as $row => $rowError):
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

                            <!-- insert confirmation -->
                            <div class="confirm-section">
                                <form action="../functions/admin/upload_data_insert.php" method="POST">
                                    <!-- if there are extra columns. allow user to name each column -->
                                    <?php if($extra_cols) : ?>
                                        <div class="extra-columns-section">
                                            <h4>Extra Columns Detected!</h4>
                                            <p class="help-text">Please provide a name for each extra column.</p>
                                            <table>
                                                <thead>
                                                    <tr>
                                                        <th>Column Data (Sample)</th>
                                                        <th>Column Name</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php for($i = 22; $i < 22 + $num_extra_cols; $i++) : 
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

                                    <input type="hidden" name="scholarship_program_id" value="<?= $scholarship_program_id ?>" />
                                    <input type="hidden" name="valid_rows" value="<?= urlencode(json_encode($valid_rows)) ?>" />
                                    <input type="hidden" name="file_name" value="<?= htmlspecialchars($file_name) ?>" />

                                    <div class="form-actions">
                                        <button type="submit" name="confirm" class="btn btn-success">Add Scholar Records</button>
                                        <a href="upload_excel.php" class="btn btn-secondary">Cancel</a>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <!-- Import History -->
                <div class="history-section">
                    <h3>Import History</h3>
                    <?php 
                    $history_sql = "SELECT imported_at, filename, scholarship_users.role FROM import_history LEFT JOIN scholarship_users ON import_history.user_id = scholarship_users.id ORDER BY imported_at DESC";
                    $history_stmt = $conn->prepare($history_sql);
                    ?>
                    <table>
                        <thead>
                            <tr>
                                <th>File Name</th>
                                <th>Imported At</th>
                                <th>User</th>
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