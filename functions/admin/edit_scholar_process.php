<?php
require_once __DIR__ . '/../../includes/conn.php';
session_start();

if(isset($_POST['update'])) {

    // store every input names in an array
    $required_fields = ['last_name', 'first_name', 
                        'email', 'student_id', 'student_contact', 
                        'sex', 'birth_date', 
                        'street', 'country', 'region', 'province', 'city', 'barangay', 'zipcode',
                        'scholarship_program', 'program', 'academic_status',
                        'father_last', 'father_first', 'father_middle', 
                        'mother_last', 'mother_first', 'mother_middle'];
    
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
        header("Location: ../../admin/edit_scholar.php?id=$id_num");
        exit();
    }

    $sex = $_POST['sex'] == 'Male' ? 0 : 1; // 0 for male, 1 for female
    $birth_date = $_POST['birth_date'];
    $year_lvl = $_POST['year_lvl'];
    
    // student contact regex validation
    $student_contact = trim($_POST['student_contact']);
    $contact_pattern = "/^09[0-9]{9}$/";
    if(!preg_match($contact_pattern, $student_contact)) {
        $_SESSION['fail'] = "Contact number does not match format";
        header("Location: ../../admin/edit_scholar.php?id=$id_num");
        exit();
    }
    
    // email regex validation
    $email = trim($_POST['email']);
    $email_pattern = "/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/";
    if(!preg_match($email_pattern, $email)) {
        $_SESSION['fail'] = "Email does not match format";
        header("Location: ../../admin/edit_scholar.php?id=$id_num");
        exit();
    }
    
    $program_id = $_POST['program'];
    $scholarship_program_id = $_POST['scholarship_program'];
    $academic_status = $_POST['academic_status'];

    $school_year_sql = "SELECT school_year FROM scholarship_program WHERE id = ?";
    $school_year_stmt = $conn->prepare($school_year_sql);
    $school_year_stmt->bind_param('i', $scholarship_program_id);
    $school_year_stmt->execute();
    $school_year_result = $school_year_stmt->get_result();
    $school_year = "";
    if($row=$school_year_result->fetch_assoc()) $school_year = $row['school_year'];
    
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
    
    // ─── NEW FIELDS: PHILSYS ID, LRN, 4P's ID ───
    $philsys_id = trim($_POST['philsys_id'] ?? '');
    $lrn = trim($_POST['lrn'] ?? '');
    $four_ps_id = trim($_POST['four_ps_id'] ?? '');
    
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

    // ─── UPDATE QUERY ───
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
                    street = ?, 
                    barangay_id = ?, 
                    city_id = ?, 
                    province_id = ?, 
                    region_id = ?, 
                    country = ?,
                    zipcode = ?, 
                    disability = ?, 
                    contact_num = ?, 
                    email = ?,
                    ip_group = ?,
                    username = ?,
                    password = ?,
                    school_year = ?,
                    philsys_id = ?,
                    lrn = ?,
                    four_ps_id = ?
                    WHERE id = ?";
    
    $scholar_stmt = $conn->prepare($scholar_sql);
    
    // 34 parameters + 1 for WHERE id = ?
    // Types: 40 characters (sssssisiiisssssssssiiiisssssssssssss)
    $scholar_stmt->bind_param(
        "sssssisiiisssssssssiiiisssssssssssss", 
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
        $permanent_address,
        $street,
        $barangay_id,
        $city_id,
        $province_id,
        $region_id, 
        $country,
        $zipcode,
        $disability, 
        $student_contact, 
        $email, 
        $ip_group,
        $student_id,
        $hashed_pass,
        $school_year,
        $philsys_id,
        $lrn,
        $four_ps_id,
        $id_num
    );
    
    if($scholar_stmt->execute()) {
        // success
        $_SESSION['success'] = "Scholar Updated";
    } else {
        // failure
        $_SESSION['fail'] = "Update Failed: " . $scholar_stmt->error;
    }
    
    // redirect user to scholar_profile page
    header("Location: ../../admin/scholar_profile.php?scholar_id=" . $id_num); 
    exit();
}
?>