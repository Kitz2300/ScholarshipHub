<?php
require_once __DIR__ . '/../includes/conn.php';

if(isset($_POST['last_name']) && isset($_POST['first_name']) && isset($_POST['middle_name']) && isset($_POST['ext_name']) && isset($_POST['email']) && isset($_POST['student_id']) && isset($_POST['student_contact']) && isset($_POST['sex']) && isset($_POST['birth_date']) && isset($_POST['disability']) && isset($_POST['ip_group']) && isset($_POST['address']) && isset($_POST['scholarship_program']) && isset($_POST['program']) && isset($_POST['father_last']) && isset($_POST['father_first']) && isset($_POST['father_middle']) && isset($_POST['mother_last']) && isset($_POST['mother_first']) && isset($_POST['mother_middle'])) {

    // student information
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

    // father information
    $father_last_name = $_POST['father_last'];
    $father_first_name = $_POST['father_first'];
    $father_middle_name = $_POST['father_middle'];

    // mother information
    $mother_last_name = $_POST['mother_last'];
    $mother_first_name = $_POST['mother_first'];
    $mother_middle_name = $_POST['mother_middle'];

    // insert process
    $scholar_sql = "INSERT INTO scholar_info(student_id, last_name, given_name, ext_name, middle_name, sex, birthdate, scholarship_program_id, program_id, academic_status, father_last_name, father_given_name, father_middle_name, mother_last_name, mother_given_name, mother_middle_name, address_id, disability, contact_num, email, ip_group, username, password) VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $scholar_stmt = $conn->prepare($scholar_sql);
    $scholar_stmt->bind_param("sssssisiisssssssssssss", $student_id, $last_name, $first_name, $ext_name, $middle_name, $sex, $birth_date, $scholarship_program_id, $program_id, $father_last_name, $father_first_name, $father_middle_name, $mother_last_name, $mother_first_name, $mother_middle_name, $address, $disability, $student_contact, $email, $ip_group, $student_id, $student_id);
    $scholar_stmt->execute();

    header("Location: ../admin/scholar_list.php?success=1");
} else {
    header("Location: ../admin/scholar_list.php?error=1");
}

?>