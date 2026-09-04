<?php
require_once __DIR__ . '/../../includes/conn.php';
require_once __DIR__ . '/../../functions/admin/upload_validation.php';
session_start();

if(isset($_POST['add'])) {
    // Required fields validation
    $required_fields = ['last_name', 'first_name', 
                        'student_id', 'student_contact', 'email', 
                        'philsys_id', 'lrn',
                        'sex', 'birth_date',
                        'street', 'country', 'region', 'province', 'city', 'barangay', 'zipcode', 
                        'scholarship_program', 'program', 'academic_status',
                        'father_last', 'father_first', 'father_middle', 
                        'mother_last', 'mother_first', 'mother_middle'];

    // Dynamically require LOA date if Leave of Absence is chosen
    if (isset($_POST['academic_status']) && $_POST['academic_status'] === 'Leave of Absence') {
        $required_fields[] = 'loa_date';
    }

    $missing_fields = [];
    
    foreach ($required_fields as $field) {
        if(empty($_POST[$field])) {
            $missing_fields[] = $field;
        }
    }
    
    if(!empty($missing_fields)) {
        $_SESSION['fail'] = "Missing Fields: " . implode(", ", $missing_fields);
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

    // Get street & zipcode
    $street = trim($_POST['street'] ?? '');
    $zipcode = trim($_POST['zipcode'] ?? '');

    // Student information
    $last_name = trim($_POST['last_name']);
    $first_name = trim($_POST['first_name']);
    $middle_name = trim($_POST['middle_name']);

    // Student ID regex validation
    $student_id = trim($_POST['student_id']);
    $id_pattern = "/^[0-9]{2}-[0-9]{4}-[0-9]{3}$/";
    if(!preg_match($id_pattern, $student_id)) {
        $_SESSION['fail'] = "Student ID does not match format";
        header("Location: ../../admin/add_scholar.php");
        exit();
    }

    $sex = $_POST['sex'] == 'Male' ? 0 : 1;
    $birth_date = $_POST['birth_date'];
    $year_lvl = (int) $_POST['year_lvl'];

    // Student contact regex validation
    $student_contact = trim($_POST['student_contact']);
    $contact_pattern = "/^09[0-9]{9}$/";
    if(!preg_match($contact_pattern, $student_contact)) {
        $_SESSION['fail'] = "Contact number does not match format";
        header("Location: ../../admin/add_scholar.php");
        exit();
    }

    // Email regex validation
    $email = trim($_POST['email']);
    $email_pattern = "/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/";
    if(!preg_match($email_pattern, $email)) {
        $_SESSION['fail'] = "Email does not match format";
        header("Location: ../../admin/add_scholar.php");
        exit();
    }

    $program_id = (int) $_POST['program'];
    $scholarship_program_id = (int) $_POST['scholarship_program'];
    $academic_status = trim($_POST['academic_status']);

    // Handle LOA date
    $loa_date = ($academic_status === 'Leave of Absence' && !empty($_POST['loa_date'])) ? trim($_POST['loa_date']) : NULL;

    $school_year_sql = "SELECT school_year FROM scholarship_program WHERE id = ?";
    $school_year_stmt = $conn->prepare($school_year_sql);
    $school_year_stmt->bind_param('i', $scholarship_program_id);
    $school_year_stmt->execute();
    $school_year_result = $school_year_stmt->get_result();
    $school_year = "";
    if($row = $school_year_result->fetch_assoc()) $school_year = $row['school_year'];
    
    // Optional fields
    $ext_name = $_POST['ext_name'] ?? "N/A";
    $ip_group = !empty(trim($_POST['ip_group'])) ? trim($_POST['ip_group']) : "Not applicable";
    $disability = !empty(trim($_POST['disability'])) ? trim($_POST['disability']) : "Not applicable";

    // Standard identification fields
    $philsys_id = trim($_POST['philsys_id'] ?? '');
    $lrn = trim($_POST['lrn'] ?? '');
    $four_ps_id = trim($_POST['four_ps_id'] ?? '');

    // BCrypt hashing password for scholar
    $hashed_pass = password_hash($student_id, PASSWORD_BCRYPT);
    
    // Father information
    $father_last_name = trim($_POST['father_last']);
    $father_first_name = trim($_POST['father_first']);
    $father_middle_name = trim($_POST['father_middle']);
    
    // Mother information
    $mother_last_name = trim($_POST['mother_last']);
    $mother_first_name = trim($_POST['mother_first']);
    $mother_middle_name = trim($_POST['mother_middle']);

    // Fetch location names for address string compilation
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

    // Build full permanent address string
    $address_parts = [];
    if(!empty($street)) $address_parts[] = $street;
    if(!empty($location_names['barangay'])) $address_parts[] = $location_names['barangay'];
    if(!empty($location_names['city'])) $address_parts[] = $location_names['city'];
    if(!empty($location_names['province'])) $address_parts[] = $location_names['province'];
    if(!empty($location_names['region'])) $address_parts[] = $location_names['region'];
    if(!empty($country)) $address_parts[] = $country;

    $permanent_address = implode(', ', $address_parts);

    // ─── INSERT Statement ───
    $scholar_sql = "INSERT INTO scholar_info(
        student_id, last_name, given_name, ext_name, middle_name, 
        sex, birthdate, scholarship_program_id, program_id, year_level, 
        academic_status, loa_date,
        father_last_name, father_given_name, father_middle_name, 
        mother_last_name, mother_given_name, mother_middle_name, 
        street, barangay_id, city_id, province_id, region_id, 
        country, zipcode, permanent_address, 
        disability, contact_num, email, ip_group, 
        username, password, school_year,
        philsys_id, lrn, four_ps_id
    ) VALUES(
        ?, ?, ?, ?, ?, 
        ?, ?, ?, ?, ?, 
        ?, ?, 
        ?, ?, ?, 
        ?, ?, ?, 
        ?, ?, ?, ?, ?,
        ?, ?, ?, 
        ?, ?, ?, ?, 
        ?, ?, ?,
        ?, ?, ?
    )";
    
    $scholar_stmt = $conn->prepare($scholar_sql);
    
    // 36 total bind parameters
    // Types: sssssisiiisssssssssiiiisssssssssssss
    $types = "sssssisiiisssssssssiiiisssssssssssss";
    $scholar_stmt->bind_param(
        $types,
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
        $loa_date,
        $father_last_name,
        $father_first_name,
        $father_middle_name,
        $mother_last_name,
        $mother_first_name,
        $mother_middle_name,
        $street,
        $barangay_id,
        $city_id,
        $province_id,
        $region_id,
        $country,
        $zipcode,
        $permanent_address,
        $disability,
        $student_contact,
        $email,
        $ip_group,
        $student_id,
        $hashed_pass,
        $school_year,
        $philsys_id,
        $lrn,
        $four_ps_id
    );
    
    if($scholar_stmt->execute()) {
        $_SESSION['success'] = "Scholar added successfully";
    } else {
        $_SESSION['fail'] = "Failed to add scholar: " . $scholar_stmt->error;
    }
    
    header("Location: ../../admin/scholar_list.php");
    exit();
}
?>