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

    // check if max columns is greater than mapped columns
    if($max_columns > $mapped_columns) {
        $extra_cols = true;
        $num_extra_cols = $max_columns - $mapped_columns;
    }

    // set true if process is successful, and get a sample row
    $import_processed = true;  
    $sample_row = !empty($valid_rows) ? $valid_rows[0] : [];
}

?>

<html>
    <head>
        <title>Excel File Upload</title>
    </head>
    <body>
        <!-- check if Logged in or not -->
        <?php 
        if(!isset($_SESSION['user_id']) && $_SESSION['role'] !== 'admin') : ?>
            <!-- if not logged in, redirect user to login page -->
            <script>alert('You are not logged in')</script>
            <script>window.location.href='login.php'</script>
        <?php else : ?>
        <h1>Upload Excel File</h1>
        <a href="scholar_list.php">Back</a>
        
        <hr>

        <!-- form for uploading excel file -->
        <form action="<?= $_SERVER['PHP_SELF'] ?>" method="POST" enctype="multipart/form-data">
            <label for="upload">Import Excel File:</label><br>
            <input type="file" name="excel_file" accept=".xls, .xlsx" id="upload" required/><br><br>
            <label for="headers">How many rows as headers:</label>
            <input type="number" name="headers" id="headers" required/><br><br>

            <!-- select which scholarship program does the uploaded scholar record belong -->
            <label for="scholarship">Scholarship Program:</label>
            <select name="scholarship_program" required>
                <option value="" disabled selected>Select Scholarship Program</option>
                <?php 
                $sql = "SELECT id, name FROM scholarship_program";
                $stmt = $conn->prepare($sql);
                $stmt->execute();
                $results = $stmt->get_result();
                
                while($row=$results->fetch_assoc()): ?>
                    <option value="<?= $row['id'] ?>" > <?= $row['name'] ?></option>
                <?php endwhile; ?>
                ?>
            </select><br><br>
            <button>Import</button>
        </form>

        <hr>

        <!-- instructions for file requirement -->
        <label for="instructions">Instructions:</label>
        <ul>
            <li>Use the template provided by the scholarship office</li>
            <li>Required Columns: ???</li>
        </ul>

        <?php if($import_processed):
                if(!empty($errors_by_row) || !empty($valid_rows)): ?>
                    <!-- import summary: num of valid rows and invalid rows -->
                    <h3>Import Summary:</h3>
                    <p>Valid rows: <?= count($valid_rows) ?></p>
                    <p>Rows with errors: <?= count($errors_by_row) ?></p>
                    
                    <?php if(!empty($errors_by_row)): ?>
                        <!-- display row errors -->
                        <table border="1">
                            <tr>
                                <th>Row</th>
                                <th>Field</th>
                                <th>Error</th>
                            </tr>
                        <?php foreach($errors_by_row as $row => $rowError):
                            foreach($rowError['row_error'] as $field => $fieldError):
                                foreach($fieldError as $error): ?>
                                <tr>
                                    <td> <?= htmlspecialchars($rowError['row_number']) ?> </td>
                                    <td> <?= htmlspecialchars($field) ?></td>
                                    <td> <?= htmlspecialchars($error) ?></td>
                                </tr>
                            <?php endforeach;
                            endforeach;
                        endforeach; ?>
                        </table><br>
                    <?php endif; ?>
                    <!-- insert confirmation -->
                    <form action="../functions/admin/upload_data_insert.php" method="POST">
                        <!-- if there are extra columns. allow user to name each column -->
                        <?php if($extra_cols) : ?>
                            <table border="2">
                                <tr>
                                    <th>Column Data</th>
                                    <th>Name Of This Column</th>
                                </tr>
                            <?php for($i = 22; $i < 22 + $num_extra_cols; $i++) : 
                                    $sample_value = $sample_row[$i] ?? "";
                                ?>
                                <tr>
                                    <td>
                                        <strong>Column <?= $i + 1 ?></strong>
                                        <small>Sample: <?= htmlspecialchars($sample_value) ?></small>
                                    </td>
                                    <td>
                                        <input type="text" name="column_names[<?= $i ?>]" required/>
                                    </td>
                                </tr>
                            <?php endfor; ?>

                            </table>
                        <?php endif; ?>
                        <input type="hidden" name="scholarship_program_id" value="<?= $scholarship_program_id ?>" />
                        <input type="hidden" name="valid_rows" value="<?= urlencode(json_encode($valid_rows)) ?>" />
                        <input type="hidden" name="file_name" value="<?= htmlspecialchars($file_name) ?>" />
                        <button name="confirm">Add Scholar Record</button>
                    </form>
                    <button onclick="window.location.href='upload_excel.php'" >Cancel</button>
                    <?php endif;
            endif;

            // import history query
            $history_sql = "SELECT imported_at, filename, scholarship_users.role FROM import_history LEFT JOIN scholarship_users ON import_history.user_id = scholarship_users.id";
            $history_stmt = $conn->prepare($history_sql);?>

            <h3>Import History</h3>
            <table border="2">
                <tr>
                    <th>File Name</th>
                    <th>Imported At</th>
                    <th>User</th>
                </tr>
            <?php if($history_stmt->execute()) :
                $history_result = $history_stmt->get_result();
                if($history_result->num_rows > 0) :
                    while($row=$history_result->fetch_assoc()) : ?>
                        <tr>
                            <td><?= htmlspecialchars($row['filename']) ?></td>
                            <td><?= htmlspecialchars($row['imported_at']) ?></td>
                            <td><?= htmlspecialchars($row['role']) ?></td>
                        </tr>
                    <?php endwhile;
                else:?>
                    <tr>
                        <td colspan="3">No History of Excel Import</td>
                    </tr>
                <?php endif;
            else :?>
                <tr>
                    <td colspan="3">Error Loading Import History</td>
                </tr>
            <?php endif;?>
            </table>
        <?php endif; ?> 
    </body>
</html>