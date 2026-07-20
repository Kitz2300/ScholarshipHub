<?php 
require_once __DIR__ . "/../../includes/conn.php";
session_start();

// count of success and errors 
$success_count = 0;
$error_count = 0;

if(isset($_POST['scholarship_program_id']) && !empty($_POST['valid_rows'])) {
    // get values and row array from input form
    $file_name = $_POST['file_name'];
    $scholarship_program_id = $_POST['scholarship_program_id'];
    $valid_rows_json = urldecode($_POST['valid_rows']);
    $valid_rows = json_decode($valid_rows_json, true);
    $column_names = isset($_POST['column_names']) ? $_POST['column_names'] : [];

    foreach($valid_rows as $rows => $rowData) {
        // store each values from each row to variables
        $student_id = $rowData[1];
        $last_name = $rowData[2];
        $given_name = $rowData[3];

        // optional: if empty store "N/A"
        $ext_name = $rowData[4] == "" || $rowData[4] == null ? "N/A" : $rowData[4];
        $middle_name = $rowData[5];
        $sex = $rowData[6];
        $birthdate = $rowData[7];
        $program = $rowData[8];
        $program_id = 0;
        $year_lvl = $rowData[9];
        $father_last_name = $rowData[10];
        $father_given_name = $rowData[11];
        $father_middle_name = $rowData[12];
        $mother_last_name = $rowData[13];
        $mother_given_name = $rowData[14];
        $mother_middle_name = $rowData[15];
        $permanent_address = $rowData[16];
        $zipcode = $rowData[17];
        $contact_num = $rowData[19];
        $email = $rowData[20];
        
        // optional: if empty store "Not applicable"
        $disability = empty($rowData[18]) ? "Not applicable" : $rowData[18];
        $ip_group = empty($rowData[21]) ? "Not applicable" : $rowData[21];

        // bcrypt hashing password for scholar
        $hashed_pass = password_hash($student_id, PASSWORD_BCRYPT);

        $program_sql = "SELECT id FROM program WHERE name = ?";
        $program_stmt = $conn->prepare($program_sql);
        $program_stmt->bind_param('s', $program);
        $program_stmt->execute();
        $program_result = $program_stmt->get_result();
        
        // get program id and store in the variable
        if($row=$program_result->fetch_assoc()) $program_id = $row['id'];

        $insert_sql = "INSERT INTO scholar_info(student_id, last_name, given_name, ext_name, middle_name, sex, birthdate, scholarship_program_id, program_id, year_level, academic_status, father_last_name, father_given_name, father_middle_name, mother_last_name, mother_given_name, mother_middle_name, permanent_address, zipcode, disability, contact_num, email, ip_group, username, password) VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?,'Active', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("sssssisiiisssssssissssss", $student_id, $last_name, $given_name, $ext_name, $middle_name, $sex, $birthdate, $scholarship_program_id, $program_id, $year_lvl, $father_last_name, $father_given_name, $father_middle_name, $mother_last_name, $mother_given_name, $mother_middle_name, $permanent_address, $zipcode, $disability, $contact_num, $email, $ip_group, $student_id, $hashed_pass);
        
        // if row insertion is success full increment success count else increment error count
        if($insert_stmt->execute()) {
            $success_count++;
        } else {
            $error_count++;
            continue;
        }

        // insert extra columns to scholar notes after scholar_info insert
        if(count($rowData) > 21) {
            $extra_data = []; 
            for($i = 22; $i < count($rowData); $i++) {
                // get column values of each extra column per row
                $col_name = isset($column_names[$i]) ? $column_names[$i] : "col_" . $i;
                $col_value = trim($rowData[$i] ?? '');
                if(!empty($col_name) && !empty($col_value)) {
                    $extra_data[$col_name] = $col_value;
                }
            }
        }

        if(!empty($extra_data)) {
            // get the newly inserted scholar_id
            $scholar_id = $conn->insert_id;

            // convert extra data into JSON
            $note_content = json_encode($extra_data);

            // insert notes query
            $note_sql = "INSERT INTO scholar_notes(scholar_id, notes) VALUES(?, ?)";
            $note_stmt = $conn->prepare($note_sql);
            $note_stmt->bind_param("is", $scholar_id, $note_content);
            $note_stmt->execute();

        }

    }

$history_sql = "INSERT INTO import_history(user_id, filename) VALUE( ?, ?)";
$history_stmt = $conn->prepare($history_sql);
$history_stmt->bind_param('is', $_SESSION['user_id'], $file_name);
$history_stmt->execute();

// success / fail message
if($success_count > 0 && $error_count == 0) {
    $_SESSION['success'] = "Scholar record successfully imported.";
    header("Location: ../../admin/scholar_list.php");
    exit();
} else {
    $_SESSION['fail'] = "Scholar record import failed. row errors: $error_count";
    header("Location: ../../admin/upload_excel.php");
    exit();
}

}
?>