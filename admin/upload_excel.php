<?php 
include __DIR__ . '/../includes/conn.php';
include __DIR__ . '/../upload_excel_process.php';
session_start();

require '../lib/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ReaderException;

// declare variables for displaying data
$rows = [];
$skip_rows = 0;

// check if file is uploadeds
if(isset($_FILES['excel_file'])) {
    $excel_file = $_FILES['excel_file'];
    $file_name = $excel_file['name'];


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
        die('Invalid file type. Please upload a valid Exce File.');
    }

    // catch error if file fails to load
    try {
        $spreadsheet = IOFactory::load($excel_file['tmp_name']);

    } catch (ReaderException $e) {
        die('Error Loading File: ' . $e->getMessage());
    }

    // get worksheet
    $worksheet = $spreadsheet->getActiveSheet();
    
    // convert worksheet to array and slice array to remove header rows
    $rows = $worksheet->toArray();
    $rows = array_slice($rows, $skip_rows);

    
}

?>

<html>
    <head>
        <title>Excel File Upload</title>
    </head>

        <!-- check if Logged in or not -->
        <?php 
        if(!isset($_SESSION['user_id']) && $_SESSION['role'] !== 'admin') { ?>
            <!-- if not logged in, redirect user to login page -->
            <script>alert('You are not logged in')</script>
            <script>window.location.href='login.php'</script>
        <?php } else { ?>
        <h1>Upload Excel File</h1>
        <a href="scholar_list.php">Back</a>
        
        <form action="<?= $_SERVER['PHP_SELF'] ?>" method="POST" enctype="multipart/form-data">
            <label for="upload">Import Excel File:</label><br>
            <input type="file" name="excel_file" accept=".xls, .xlsx" required/><br><br>
            <button name="open">Import</button>
        </form>

        <label for="instructions">Instructions:</label>
        <ul>
            <li>Use the template provided by the scholarship office</li>
            <li>Required Columns: ???</li>
        </ul>
        <?php } ?> 
    </body>
</html>