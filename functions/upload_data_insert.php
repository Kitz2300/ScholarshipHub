<?php 
require_once __DIR__ . "/../includes/conn.php";
session_start();

if(isset($_POST['scholarship_program_id']) && !empty($_POST['valid_rows'])) {
    $scholarship_program_id = $_POST['scholarship_program_id'];
    $valid_rows_json = urldecode($_POST['valid_rows']);
    $valid_rows = json_decode($valid_rows_json, true);
    $program_id = 0;

    foreach($valid_rows as $rows => $rowData) {
        $student_id = $rowData[1];
        $last_name = $rowData[2];
        $given_name = $rowData[3];
        $ext_name = $rowData[4] == "" || $rowData[4] == null ? "N/A" : $rowData[4];
        $middle_name = $rowData[5];
        $sex = $rowData[6];
        $birthdate = $rowData[7];
        $program = $rowData[8];
        $year_lvl = $rowData[9];
        $father_last_name = $rowData[10];
        $father_given_name = $rowData[11];
        $father_middle_name = $rowData[12];
        $mother_last_name = $rowData[13];
        $mother_given_name = $rowData[14];
        $mother_middle_name = $rowData[15];
        $permanent_address = $rowData[16];
        $zipcode = $rowData[17];
        $disability = $rowData[18];
        $contact_num = $rowData[19];
        $email = $rowData[20];
        $ip_group = $rowData[21];

        $program_sql = "SELECT id FROM program WHERE name = ?";
        $program_stmt = $conn->prepare($program_sql);
        $program_stmt->bind_param('s', $program);
        $program_stmt->execute();
        $program_result = $program_stmt->get_result();
        
        if($row=$program_result->fetch_assoc()) $program_id = $row['id'];

        $insert_sql = "INSERT INTO scholar_info(student_id, last_name, given_name, ext_name, middle_name, sex, birthdate, scholarship_program_id, program_id, academic_status, father_last_name, father_given_name, father_middle_name, mother_last_name, mother_given_name, mother_middle_name, permanent_address, zipcode, disability, contact_num, email, ip_group, username, password) VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("sssssisiisssssssissssss", $student_id, $last_name, $given_name, $ext_name, $middle_name, $sex, $birthdate, $scholarship_program_id, $program_id, $father_last_name, $father_given_name, $father_middle_name, $mother_last_name, $mother_given_name, $mother_middle_name, $permanent_address, $zipcode, $disability, $contact_num, $email, $ip_group, $student_id, $student_id);

        if($insert_stmt->execute()) {
            $_SESSION['success'] = "Scholar record successfully imported.";
            header("Location: ../admin/scholar_list.php");
            exit();
        } else {
            $_SESSION['fail'] = "Scholar record import failed.";
            header("Location: ../admin/upload_excel.php");
            exit();
        }
    }

}
?>