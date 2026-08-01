<?php
require_once __DIR__ . '/../../includes/conn.php';
session_start();

if(isset($_POST['add'])) {
    // required fieds
    $required_fields = ['last_name', 'first_name', 'middle_name', 'student_id', 'student_contact', 'email', 'sex', 'birth_date', 'address', 'zipcode', 'scholarship_program', 'program', 'father_last', 'father_first', 'father_middle', 'mother_last', 'mother_first', 'mother_middle'];
    
    $missing_fields = [];
    
    // loop through each fields and validate
    foreach ($required_fields as $field) {
        if(empty($_POST[$field])) {
            $missing_fields[] = $field;
        }
    }
    
    // if there are missing fields alert user
    if(!empty($missing_fields)) {
        $_SESSION['fail'] = "Missing Fields: " . implode(",", $missing_fields);
        header("Location: ../../admin/add_scholar.php");
        exit();
    }
    
    // student information
    // required fields
    $last_name = trim($_POST['last_name']);
    $first_name = trim($_POST['first_name']);
    $middle_name = trim($_POST['middle_name']);
    $student_id = trim ($_POST['student_id']);
    $sex = $_POST['sex'] == 'Male' ? 0 : 1; // 0 for male, 1 for female
    $birth_date = $_POST['birth_date'];
    $address = trim($_POST['address']);
    $zipcode = $_POST['zipcode'];
    $year_lvl = $_POST['year_lvl'];
    $student_contact = trim($_POST['student_contact']);
    $email = trim($_POST['email']);
    $program_id = $_POST['program'];
    $scholarship_program_id = $_POST['scholarship_program'];
    
    // optional
    $ext_name = $_POST['ext_name'] ?? "N/A"; // if empty store N/A
    $ip_group = trim($_POST['ip_group']) ?? "Not applicable"; // if empty store Not applicable
    $disability = trim($_POST['disability']) ?? "Not applicable"; // if empty store Not applicable

    // bcrypt hashing password for scholar
    $hashed_pass = password_hash($student_id, PASSWORD_BCRYPT);
    
    // father information
    $father_last_name = trim($_POST['father_last']);
    $father_first_name = trim($_POST['father_first']);
    $father_middle_name = trim($_POST['father_middle']);
    
    // mother information
    $mother_last_name = trim($_POST['mother_last']);
    $mother_first_name = trim($_POST['mother_first']);
    $mother_middle_name = trim($_POST['mother_middle']);
    
    // insert process
    $scholar_sql = "INSERT INTO scholar_info(student_id, last_name, given_name, ext_name, middle_name, sex, birthdate, scholarship_program_id, program_id, year_level, academic_status, father_last_name, father_given_name, father_middle_name, mother_last_name, mother_given_name, mother_middle_name, permanent_address, zipcode, disability, contact_num, email, ip_group, username, password) VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $scholar_stmt = $conn->prepare($scholar_sql);
    $scholar_stmt->bind_param("sssssisiiisssssssissssss", $student_id, $last_name, $first_name, $ext_name, $middle_name, $sex, $birth_date, $scholarship_program_id, $program_id, $year_lvl,$father_last_name, $father_first_name, $father_middle_name, $mother_last_name, $mother_first_name, $mother_middle_name, $address, $zipcode, $disability, $student_contact, $email, $ip_group, $student_id, $hashed_pass);

    // success / fail message
    if($scholar_stmt->execute()) {
        $_SESSION['success'] = "Scholar added successfully";
    } else {
        $_SESSION['fail'] = "Failed to add scholar";
    }
    
    header("Location: ../../admin/scholar_list.php");
    exit();
}

?>