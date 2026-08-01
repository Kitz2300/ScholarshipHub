<?php
require_once __DIR__ . '/../../includes/conn.php';
session_start();

if(isset($_POST['update'])) {

    // store every input names in an array
    $required_fields = ['last_name', 'first_name', 'middle_name', 'ext_name', 'email', 'student_id', 'student_contact', 'sex', 'birth_date', 'address', 'zipcode', 'scholarship_program', 'program', 'academic_status', 'father_last', 'father_first', 'father_middle', 'mother_last', 'mother_first', 'mother_middle'];
    
    $missing_fields = [];
    
    // loop through the required fields and check if each one is empty, if true insert input name in missing_fields array
    foreach ($required_fields as $field) {
        if(empty($_POST[$field])) {
            $missing_fields[] = $field;
        }
    }
    
    $id_num = $_POST['scholar_id'];
    
    // if there are missing fields notify user of it
    if(!empty($missing_fields)) {
        $_SESSION['fail'] = "Missing Fields: " . implode(",", $missing_fields);
        header("Location: ../../admin/edit_scholar.php?id=$id_num"); 
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
    $academic_status = $_POST['academic_status'];
    
    // optional fields
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
    
    $scholar_sql = "UPDATE scholar_info SET 
                    student_id = ?, 
                    last_name = ?, 
                    given_name = ?, 
                    ext_name = ?, 
                    middle_name = ?, 
                    sex = ?, 
                    birthdate = ?, 
                    scholarship_program_id = ?, 
                    program_id = ?, 
                    year_level = ?, 
                    academic_status = ?,
                    father_last_name = ?, 
                    father_given_name = ?, 
                    father_middle_name = ?, 
                    mother_last_name = ?, 
                    mother_given_name = ?, 
                    mother_middle_name = ?, 
                    permanent_address = ?,
                    zipcode = ?, 
                    disability = ?, 
                    contact_num = ?, 
                    email = ?,
                    ip_group = ?,
                    username = ?,
                    password = ?
                    WHERE id = ?";
    
    $scholar_stmt = $conn->prepare($scholar_sql);
    $scholar_stmt->bind_param("sssssisiiissssssssissssssi", 
                    $student_id, 
                    $last_name, 
                    $first_name, 
                    $ext_name, 
                    $middle_name, 
                    $sex, 
                    $birth_date,
                    $scholarship_program_id, 
                    $program_id, 
                    $year_lvl, 
                    $academic_status,
                    $father_last_name, 
                    $father_first_name, 
                    $father_middle_name, 
                    $mother_last_name, 
                    $mother_first_name, 
                    $mother_middle_name, 
                    $address,
                    $zipcode,
                    $disability, 
                    $student_contact, 
                    $email, 
                    $ip_group,
                    $student_id,
                    $hashed_pass, 
                    $id_num);
    if($scholar_stmt->execute()) {
        // success
        $_SESSION['success'] = "Scholar Updated";
    } else {
        // failure
        $_SESSION['fail'] = "Update Failded " . $scholar_stmt->error;
    }
    
    // redirect user to scholar_profile page
    header("Location: ../../admin/scholar_profile.php?scholar_id=" . $id_num); 
    exit();
}
?>