<?php
require_once __DIR__ . '/../../includes/conn.php';
session_start();

if(isset($_POST['add'])) {
    // required fieds
    $required_fields = ['last_name', 'first_name', 'middle_name', 
                        'student_id', 'student_contact', 'email', 
                        'sex', 'birth_date', 
                        'street', 'country', 'region', 'province', 'city', 'barangay', 'zipcode', 
                        'scholarship_program', 'program', 
                        'father_last', 'father_first', 'father_middle', 
                        'mother_last', 'mother_first', 'mother_middle'];

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
    
    // Get country
    $country = trim($_POST['country']);
    if($country === 'Other' && !empty($_POST['other_country'])) {
        $country = trim($_POST['other_country']);
    }

    // Get PSGC IDs
    $region_id = (int) ($_POST['region'] ?? 0);
    $province_id = (int) ($_POST['province'] ?? 0);
    $city_id = (int) ($_POST['city'] ?? 0);
    $barangay_id = (int) ($_POST['barangay'] ?? 0);

    // Get street
    $street = trim($_POST['street'] ?? '');

    // Get zipcode (optional - can be from PSGC or manual)
    $zipcode = trim($_POST['zipcode'] ?? '');

    // student information
    // required fields
    $last_name = trim($_POST['last_name']);
    $first_name = trim($_POST['first_name']);
    $middle_name = trim($_POST['middle_name']);

    // student id regex validation
    $student_id = trim($_POST['student_id']);
    $id_pattern = "/^[0-9]{2}-[0-9]{4}-[0-9]{3}$/";
    if(!preg_match($id_pattern, $student_id)) {
        $_SESSION['fail'] = "Student ID does not match format";
        header("Location: ../../admin/add_scholar.php");
        exit();
    }

    $sex = $_POST['sex'] == 'Male' ? 0 : 1; // 0 for male, 1 for female
    $birth_date = $_POST['birth_date'];
    $zipcode = $_POST['zipcode'];
    $year_lvl = $_POST['year_lvl'];

    // student contact regex validation
    $student_contact = trim($_POST['student_contact']);
    $contact_pattern = "/^09[0-9]{9}$/";
    if(!preg_match($contact_pattern, $student_contact)) {
        $_SESSION['fail'] = "Contact number does not match format";
        header("Location: ../../admin/add_scholar.php");
    }

    // email regex validation
    $email = trim($_POST['email']);
    $email_pattern = "/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/";
    if(!preg_match($email_pattern, $email)) {
        $_SESSION['fail'] = "Email does not match format";
        header("Location: ../../admin/add_scholar.php");
        exit();
    }

    $program_id = $_POST['program'];
    $scholarship_program_id = $_POST['scholarship_program'];
    
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
    


    // Fetch location names for the full address
    $location_names = [];

    if($region_id > 0) {
        $sql = "SELECT name FROM psgc WHERE id = $region_id";
        $result = $conn->query($sql);
        $location_names['region'] = $result->fetch_assoc()['name'] ?? '';
    }
    if($province_id > 0) {
        $sql = "SELECT name FROM psgc WHERE id = $province_id";
        $result = $conn->query($sql);
        $location_names['province'] = $result->fetch_assoc()['name'] ?? '';
    }
    if($city_id > 0) {
        $sql = "SELECT name FROM psgc WHERE id = $city_id";
        $result = $conn->query($sql);
        $location_names['city'] = $result->fetch_assoc()['name'] ?? '';
    }
    if($barangay_id > 0) {
        $sql = "SELECT name FROM psgc WHERE id = $barangay_id";
        $result = $conn->query($sql);
        $location_names['barangay'] = $result->fetch_assoc()['name'] ?? '';
    }

    // Build full permanent address
    $address_parts = [];
    if(!empty($street)) $address_parts[] = $street;
    if(!empty($location_names['barangay'])) $address_parts[] = $location_names['barangay'];
    if(!empty($location_names['city'])) $address_parts[] = $location_names['city'];
    if(!empty($location_names['province'])) $address_parts[] = $location_names['province'];
    if(!empty($location_names['region'])) $address_parts[] = $location_names['region'];
    if(!empty($country)) $address_parts[] = $country;

    $permanent_address = implode(', ', $address_parts);

    // insert process
    $scholar_sql = "INSERT INTO scholar_info(
    student_id, last_name, given_name, ext_name, middle_name, 
    sex, birthdate, scholarship_program_id, program_id, year_level, 
    academic_status, father_last_name, father_given_name, father_middle_name, 
    mother_last_name, mother_given_name, mother_middle_name, 
    street, barangay_id, city_id, province_id, region_id, 
    country, zipcode, permanent_address, 
    disability, contact_num, email, ip_group, 
    username, password
    ) VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $scholar_stmt = $conn->prepare($scholar_sql);
    $scholar_stmt = $conn->prepare($scholar_sql);
    $scholar_stmt->bind_param(
        "sssssisiiisssssssiiiisssssssss",
        $student_id,       // 1
        $last_name,        // 2
        $first_name,       // 3
        $ext_name,         // 4
        $middle_name,      // 5
        $sex,              // 6
        $birth_date,       // 7
        $scholarship_program_id, // 8
        $program_id,       // 9
        $year_lvl,         // 10
        $father_last_name, // 11
        $father_first_name,// 12
        $father_middle_name,// 13
        $mother_last_name, // 14
        $mother_first_name,// 15
        $mother_middle_name,// 16
        $street,           // 17
        $barangay_id,      // 18
        $city_id,          // 19
        $province_id,      // 20
        $region_id,        // 21
        $country,          // 22
        $zipcode,          // 23
        $permanent_address,// 24
        $disability,       // 25
        $student_contact,  // 26
        $email,            // 27
        $ip_group,         // 28
        $student_id,       // 29 (username)
        $hashed_pass       // 30 (password)
    );
    
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