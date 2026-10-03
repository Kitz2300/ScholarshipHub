<?php
require_once __DIR__ . '/../../includes/conn.php';
require_once __DIR__ . '/../../includes/email_helper.php';
session_start();

if (isset($_POST['add'])) {

    // Store required input field names in an array
    $required_fields = [
        'last_name', 'first_name', 
        'email', 'student_id', 'student_contact', 
        'sex', 'birth_date', 
        'country', 'scholarship_program', 'program', 'year_lvl', 'academic_status',
        'father_last', 'father_first', 'father_middle',
        'mother_last', 'mother_first', 'mother_middle',
        'philsys_id', 'lrn'
    ];

    // Check if LOA Fields are required when status is Leave of Absence
    if (isset($_POST['academic_status']) && $_POST['academic_status'] === 'Leave of Absence') {
        $required_fields[] = 'loa_semester';
        $required_fields[] = 'loa_school_year';
    }

    $missing_fields = [];
    
    // Loop through required fields and validate presence
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            $missing_fields[] = $field;
        }
    }
    
    // Notify user of missing fields
    if (!empty($missing_fields)) {
        $_SESSION['fail'] = "Missing Fields: " . implode(", ", $missing_fields);
        header("Location: ../../admin/add_scholar.php"); 
        exit();
    }

    // Get country
    $country = trim($_POST['country'] ?? 'Philippines');
    if ($country === 'Other' && !empty($_POST['other_country'])) {
        $country = trim($_POST['other_country']);
    }

    // Address parameters logic (Philippines vs Other)
    if ($country === 'Philippines') {
        $region_id = (int) ($_POST['region'] ?? 0);
        $province_id = (int) ($_POST['province'] ?? 0);
        $city_id = (int) ($_POST['city'] ?? 0);
        $barangay_id = (int) ($_POST['barangay'] ?? 0);
        $street = trim($_POST['street'] ?? '');
        $zipcode = trim($_POST['zipcode'] ?? '');
    } else {
        $region_id = 0;
        $province_id = 0;
        $city_id = 0;
        $barangay_id = 0;
        $street = trim($_POST['other_address1'] ?? $_POST['street'] ?? '');
        $zipcode = trim($_POST['other_zip'] ?? $_POST['zipcode'] ?? '');
    }

    // Student information
    $last_name = trim($_POST['last_name']);
    $first_name = trim($_POST['first_name']);
    $middle_name = trim($_POST['middle_name']);

    // Student ID regex validation
    $student_id = trim($_POST['student_id']);
    $id_pattern = "/^[0-9]{2}-[0-9]{4}-[0-9]{3}$/";
    if (!preg_match($id_pattern, $student_id)) {
        $_SESSION['fail'] = "Student ID does not match format (e.g. 12-3456-789)";
        header("Location: ../../admin/add_scholar.php");
        exit();
    }

    // Check duplicate Student ID
    $check_id_sql = "SELECT id FROM scholar_info WHERE student_id = ?";
    $check_stmt = $conn->prepare($check_id_sql);
    $check_stmt->bind_param("s", $student_id);
    $check_stmt->execute();
    if ($check_stmt->get_result()->num_rows > 0) {
        $_SESSION['fail'] = "Student ID already exists.";
        header("Location: ../../admin/add_scholar.php");
        exit();
    }

    $sex = $_POST['sex'] == 'Male' ? 0 : 1; // 0 for male, 1 for female
    $birth_date = $_POST['birth_date'];
    $year_lvl = trim($_POST['year_lvl'] ?? '');
    
    // Student contact regex validation
    $student_contact = trim($_POST['student_contact']);
    $contact_pattern = "/^09[0-9]{9}$/";
    if (!preg_match($contact_pattern, $student_contact)) {
        $_SESSION['fail'] = "Contact number does not match format (e.g. 09171234567)";
        header("Location: ../../admin/add_scholar.php");
        exit();
    }
    
    // Email regex validation
    $email = trim($_POST['email']);
    $email_pattern = "/^[^\s@]+@[^\s@]+\.[^\s@]+$/";
    if (!preg_match($email_pattern, $email)) {
        $_SESSION['fail'] = "Email format is invalid.";
        header("Location: ../../admin/add_scholar.php");
        exit();
    }
    
    $program_id = (int) $_POST['program'];
    $scholarship_program_id = (int) $_POST['scholarship_program'];
    $academic_status = trim($_POST['academic_status']);
    
    // Set LOA Fields if status is Leave of Absence, otherwise set NULL
    $loa_semester = ($academic_status === 'Leave of Absence' && !empty($_POST['loa_semester'])) ? trim($_POST['loa_semester']) : NULL;
    $loa_school_year = ($academic_status === 'Leave of Absence' && !empty($_POST['loa_school_year'])) ? trim($_POST['loa_school_year']) : NULL;

    // Get school year from selected scholarship program
    $school_year_sql = "SELECT school_year FROM scholarship_program WHERE id = ?";
    $school_year_stmt = $conn->prepare($school_year_sql);
    $school_year_stmt->bind_param('i', $scholarship_program_id);
    $school_year_stmt->execute();
    $school_year_result = $school_year_stmt->get_result();
    $school_year = "";
    if ($row = $school_year_result->fetch_assoc()) {
        $school_year = $row['school_year'];
    }
    
    // Optional fields
    $ext_name = $_POST['ext_name'] ?? "N/A";
    $ip_group = !empty(trim($_POST['ip_group'])) ? trim($_POST['ip_group']) : "Not applicable";
    $disability = !empty(trim($_POST['disability'])) ? trim($_POST['disability']) : "Not applicable";
    
    // Checkbox flag
    $is_child_of_solo_parent = isset($_POST['is_child_of_solo_parent']) ? 1 : 0;

    // BCrypt password hash (Default password = Student ID)
    $hashed_pass = password_hash($student_id, PASSWORD_BCRYPT);
    
    // Father information
    $father_last_name = trim($_POST['father_last']);
    $father_first_name = trim($_POST['father_first']);
    $father_middle_name = trim($_POST['father_middle']);
    $father_contact = !empty(trim($_POST['father_contact'] ?? '')) ? trim($_POST['father_contact']) : "N/A";
    
    // Mother information with key fallbacks
    $mother_last_name = trim($_POST['mother_last']);
    $mother_first_name = trim($_POST['mother_first'] ?? $_POST['mother_given'] ?? $_POST['mother_first_name'] ?? $_POST['mother_given_name'] ?? '');
    $mother_middle_name = trim($_POST['mother_middle']);
    $mother_contact = !empty(trim($_POST['mother_contact'] ?? '')) ? trim($_POST['mother_contact']) : "N/A";
    
    // PHILSYS ID, LRN, 4P's ID
    $philsys_id = trim($_POST['philsys_id'] ?? '');
    $lrn = trim($_POST['lrn'] ?? '');
    $four_ps_id = trim($_POST['four_ps_id'] ?? '');
    
    // Fetch location names in a single query to build permanent_address string
    $location_ids = array_filter([$region_id, $province_id, $city_id, $barangay_id]);
    $location_names = [];

    if (!empty($location_ids)) {
        $ids_string = implode(',', array_map('intval', $location_ids));
        $psgc_sql = "SELECT id, name FROM psgc WHERE id IN ($ids_string)";
        $psgc_res = $conn->query($psgc_sql);
        
        while ($row = $psgc_res->fetch_assoc()) {
            if ($row['id'] == $region_id)   $location_names['region']   = $row['name'];
            if ($row['id'] == $province_id) $location_names['province'] = $row['name'];
            if ($row['id'] == $city_id)     $location_names['city']     = $row['name'];
            if ($row['id'] == $barangay_id) $location_names['barangay'] = $row['name'];
        }
    }

    $region_name   = $location_names['region']   ?? '';
    $province_name = $location_names['province'] ?? '';
    $town_city     = $location_names['city']     ?? trim($_POST['other_address2'] ?? '');
    $barangay_name = $location_names['barangay'] ?? '';

    // Build permanent address string
    $address_parts = [];
    if (!empty($street)) $address_parts[] = $street;
    if (!empty($barangay_name)) $address_parts[] = $barangay_name;
    if (!empty($town_city)) $address_parts[] = $town_city;
    if (!empty($province_name)) $address_parts[] = $province_name;
    if (!empty($region_name)) $address_parts[] = $region_name;
    if (!empty($country)) $address_parts[] = $country;

    $permanent_address = implode(', ', $address_parts);

    // ─── INSERT QUERY (39 columns) ───
    $scholar_sql = "INSERT INTO scholar_info (
                    student_id, 
                    last_name, 
                    given_name, 
                    ext_name, 
                    middle_name, 
                    sex, 
                    birthdate, 
                    scholarship_program_id, 
                    program_id, 
                    year_level, 
                    academic_status,
                    loa_semester,
                    loa_school_year,
                    father_last_name, 
                    father_given_name, 
                    father_middle_name,
                    father_contact, 
                    mother_last_name, 
                    mother_given_name, 
                    mother_middle_name, 
                    mother_contact,
                    permanent_address,
                    barangay_id, 
                    city_id, 
                    province_id, 
                    region_id, 
                    country,
                    zipcode, 
                    disability, 
                    contact_num, 
                    email,
                    ip_group,
                    is_child_of_solo_parent,
                    username,
                    password,
                    school_year,
                    philsys_id,
                    lrn,
                    four_ps_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $scholar_stmt = $conn->prepare($scholar_sql);
    
    // Exactly 39 type specifiers matching 39 bound variables
    $types = "sssssisiisssssssssssssiiiissssssissssss";
    
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
        $loa_semester,
        $loa_school_year,
        $father_last_name, 
        $father_first_name, 
        $father_middle_name, 
        $father_contact,
        $mother_last_name, 
        $mother_first_name, 
        $mother_middle_name, 
        $mother_contact,
        $permanent_address,
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
        $is_child_of_solo_parent,
        $student_id,
        $hashed_pass,
        $school_year,
        $philsys_id,
        $lrn,
        $four_ps_id
    );
    
    if ($scholar_stmt->execute()) {
        $email_sent = false;

        try {
            $email_sent = sendAccountCreatedEmail($email, $first_name, $student_id);
        } catch (Throwable $e) {
            error_log("Email sending failed for student $student_id: " . $e->getMessage());
        }

        $new_scholar_id = $conn->insert_id;

        // AUDIT TRAIL LOGGING
        $user_id = $_SESSION['user_id'] ?? $_SESSION['id'] ?? 0;
        $action = "Added new scholar: $first_name $last_name (Student ID: $student_id, Scholar ID: $new_scholar_id)";

        $audit_sql = "INSERT INTO audit_trail (user_id, action) VALUES (?, ?)";
        $audit_stmt = $conn->prepare($audit_sql);
        $audit_stmt->bind_param("is", $user_id, $action);
        $audit_stmt->execute();

        if ($email_sent) {
            $_SESSION['success'] = "Scholar added successfully and account notification email sent!";
        } else {
            $_SESSION['fail'] = "Scholar added successfully, but email notification failed to send. Check system error logs for details.";
        }
        
        header("Location: ../../admin/scholar_list.php");
    } else {
        $_SESSION['fail'] = "Failed to add scholar: " . $scholar_stmt->error;
        header("Location: ../../admin/add_scholar.php");
    }
    exit();
}
?>