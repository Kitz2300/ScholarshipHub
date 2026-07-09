<?php 
require_once __DIR__ . "/../includes/conn.php";

if(isset($_POST['scholarship_program_id']) && !empty($_POST['valid_rows'])) {
    $scholarship_program_id = $_POST['scholarship_program_id'];
    $valid_rows_json = urldecode($_POST['valid_rows']);
    $valid_rows = json_decode($valid_rows_json, true);

    foreach($valid_rows as $rows => $rowData) {
        foreach($rowData as $cell) {
            echo (string)$cell . " " ;
        }
    }

} else {
    echo "something did not work";
}
?>