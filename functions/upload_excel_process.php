<?php
require '../lib/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ReaderException;

// check if file is uploadeds
if(isset($_FILES['excel_file'])) {
    $excel_file = $_FILES['excel_file'];

    // check for upload errors
    if ($excel_file['error'] !== UPLOAD_ERR_OK) {
        die('Upload failed with error code: ' . $file['error']);
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
    finfo_close($file_info);

    if(!in_array($mime_type, $allowedMimeTypes)) {
        die('Invalid file type. Please upload a valid Exce File.');
    }

    try {
        $spreadsheet = IOFactory::load($excel_file['tmp_name']);

    } catch (ReaderException $e) {
        die('Error Loading File: ' . $e->getMessage());
    }

    $worksheet = $spreadsheet->getActiveSheet();
    $rows = $worksheet->toArray();

    echo '<h2>Data Read From File</h2>';

    echo '<table border="1" cellpadding="5">';

    foreach($rows as $rowIndex => $rowData) {
        echo '<tr>';

        foreach($rowData as $cell) {
            echo '<td>' . htmlspecialchars((string)$cell) . '</td>';
        }

        echo '</tr>';
    }
    echo '</table>';
} else {
    echo 'No file uploaded or invalid request';
}

?>