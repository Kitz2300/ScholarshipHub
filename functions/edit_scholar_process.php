<?php
session_start();
require_once __DIR__ . '/../includes/conn.php';

$required_fields = ['last_name', 'first_name', 'middle_name', 'ext_name', 'email', 'student_id', 'student_contact', 'sex', 'birth_date', 'disability', 'ip_group', 'address', 'scholarship_program', 'program', 'academic_status', 'father_last', 'father_first', 'father_middle', 'mother_last', 'mother_first', 'mother_middle'];

$missing_fields = [];

foreach ($required_fields as $field) {
    if(empty($_POST[$field])) {
        $missing_fields[] = $field;
    }
}

if(!empty($missing_fields)) {
    die("Missing Fields: " . implode(",", $missing_fields));
}

// student information
$id_num = $_POST['scholar_id'];
$last_name = $_POST['last_name'];
$first_name = $_POST['first_name'];
$middle_name = $_POST['middle_name'];
$ext_name = $_POST['ext_name'];
$email = $_POST['email'];
$student_id = $_POST['student_id'];
$student_contact = $_POST['student_contact'];
$sex = $_POST['sex'] == 'Male' ? 0 : 1; // 0 for male, 1 for female
$birth_date = $_POST['birth_date'];
$disability = $_POST['disability'];
$ip_group = $_POST['ip_group'];
$address = $_POST['address'];
$scholarship_program_id = $_POST['scholarship_program'];
$program_id = $_POST['program'];
$academic_status = $_POST['academic_status'];

// father information
$father_last_name = $_POST['father_last'];
$father_first_name = $_POST['father_first'];
$father_middle_name = $_POST['father_middle'];

// mother information
$mother_last_name = $_POST['mother_last'];
$mother_first_name = $_POST['mother_first'];
$mother_middle_name = $_POST['mother_middle'];

$scholar_sql = "UPDATE scholar_info SET 
                student_id = ?, 
                last_name = ?, 
                given_name = ?, 
                ext_name = ?, 
                middle_name = ?, 
                sex = ?, 
                birthdate = ?, 
                scholarship_program_id = ?, 
                program_id = ?, academic_status = ?, 
                father_last_name = ?, 
                father_given_name = ?, 
                father_middle_name = ?, 
                mother_last_name = ?, 
                mother_given_name = ?, 
                mother_middle_name = ?, 
                address_id = ?, 
                disability = ?, 
                contact_num = ?, 
                email = ?,
                ip_group = ? 
                WHERE id = ?";

$scholar_stmt = $conn->prepare($scholar_sql);
$scholar_stmt->bind_param("sssssisiissssssssssssi", 
                $student_id, 
                $last_name, 
                $first_name, 
                $ext_name, 
                $middle_name, 
                $sex, 
                $birth_date,
                $scholarship_program_id, 
                $program_id, 
                $academic_status, 
                $father_last_name, 
                $father_first_name, 
                $father_middle_name, 
                $mother_last_name, 
                $mother_first_name, 
                $mother_middle_name, 
                $address, 
                $disability, 
                $student_contact, 
                $email, 
                $ip_group, 
                $id_num);
if($scholar_stmt->execute()) {
    // success
    $_SESSION['edit_success'] = "Scholar Updated";
} else {
    // failure
    $_SESSION['edit_fail'] = "Update Failded " . $scholar_stmt->error;
}

header("Location: ../admin/scholar_list.php?success=1"); 
?>