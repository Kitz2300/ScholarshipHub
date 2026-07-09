<?php 
require_once __DIR__ . "/../includes/conn.php";

if(isset($_POST['scholarship_program_id']) && !empty($_POST['valid_rows'])) {
    $scholarship_program_id = $_POST['scholarship_program_id'];
    $valid_rows = $_POST['valid_rows'];

    foreach($valid_rows as $rows => $rowData) {
        echo "$rowData ";
    }

}
?>