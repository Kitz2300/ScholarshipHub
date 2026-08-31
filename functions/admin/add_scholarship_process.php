<?php 
include __DIR__ . "/../../includes/conn.php";
include __DIR__ . "/../../includes/address_helper.php";
session_start();

if(isset($_POST['add'])) {

    // ─── Required fields validation ───
    $required_fields = ['last_name', 'first_name', 
                        'email', 'student_id', 'student_contact', 
                        'sex', 'birth_date', 
                        'street', 'country', 'region', 'province', 'city', 'barangay', 'zipcode',
                        'scholarship_program', 'program', 'year_lvl',
                        'father_last', 'father_first', 'father_middle', 
                        'mother_last', 'mother_first', 'mother_middle'];
    
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

    // ─── Get country ───
    $country = trim($_POST['country']);
    if($country === 'Other' && !empty($_POST['other_country'])) {
        $country = trim($_POST['other_country']);
    }

    // ─── Get PSGC IDs ───
    $region_id = (int) ($_POST['region'] ?? 0);
    $province_id = (int) ($_POST['province'] ?? 0);
    $city_id = (int) ($_POST['city'] ?? 0);
    $barangay_id = (int) ($_POST['barangay'] ?? 0);

    // ─── Street ───
    $street = trim($_POST['street'] ?? '');

    // ─── Zipcode ───
    $zipcode = trim($_POST['zipcode'] ?? '');

    // ─── Student Information ───
    $last_name = trim($_POST['last_name']);
    $first_name = trim($_POST['first_name']);
    $middle_name = trim($_POST['middle_name'] ?? '');

    // Student ID validation
    $student_id = trim($_POST['student_id']);
    if(!preg_match('/^[0-9]{2}-[0-9]{4}-[0-9]{3}$/', $student_id)) {
        $_SESSION['fail'] = "Student ID does not match format";
        header("Location: ../../admin/add_scholar.php");
        exit();
    }

    // Sex (0 = Male, 1 = Female)
    $sex = $_POST['sex'] == 'Male' ? 0 : 1;
    $birth_date = $_POST['birth_date'];
    $year_lvl = (int) $_POST['year_lvl'];

    // Contact validation
    $student_contact = trim($_POST['student_contact']);
    if(!preg_match('/^09[0-9]{9}$/', $student_contact)) {
        $_SESSION['fail'] = "Contact number does not match format";
        header("Location: ../../admin/add_scholar.php");
        exit();
    }

    // Email validation
    $email = trim($_POST['email']);
    if(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['fail'] = "Email format is invalid.";
        header("Location: ../../admin/add_scholar.php");
        exit();
    }

    $program_id = (int) $_POST['program'];
    $scholarship_program_id = (int) $_POST['scholarship_program'];

    // Get school year from selected scholarship program
    $school_year_sql = "SELECT school_year FROM scholarship_program WHERE id = ?";
    $school_year_stmt = $conn->prepare($school_year_sql);
    $school_year_stmt->bind_param('i', $scholarship_program_id);
    $school_year_stmt->execute();
    $school_year_result = $school_year_stmt->get_result();
    $school_year = "";
    if($row = $school_year_result->fetch_assoc()) {
        $school_year = $row['school_year'];
    }

    // ─── Optional fields ───
    $ext_name = $_POST['ext_name'] ?? "N/A";
    $ip_group = trim($_POST['ip_group']) ?? "Not applicable";
    $disability = trim($_POST['disability']) ?? "Not applicable";

    // ─── New fields ───
    $philsys_id = trim($_POST['philsys_id'] ?? '');
    $lrn = trim($_POST['lrn'] ?? '');
    $four_ps_id = trim($_POST['four_ps_id'] ?? '');

    // ─── Family Background ───
    $father_last_name = trim($_POST['father_last']);
    $father_first_name = trim($_POST['father_first']);
    $father_middle_name = trim($_POST['father_middle']);
    $mother_last_name = trim($_POST['mother_last']);
    $mother_first_name = trim($_POST['mother_first']);
    $mother_middle_name = trim($_POST['mother_middle']);

    // ─── Fetch location names for full address ───
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

    // ─── Build permanent_address ───
    $address_parts = [];
    if(!empty($street)) $address_parts[] = $street;
    if(!empty($location_names['barangay'])) $address_parts[] = $location_names['barangay'];
    if(!empty($location_names['city'])) $address_parts[] = $location_names['city'];
    if(!empty($location_names['province'])) $address_parts[] = $location_names['province'];
    if(!empty($location_names['region'])) $address_parts[] = $location_names['region'];
    if(!empty($country)) $address_parts[] = $country;
    $permanent_address = implode(', ', $address_parts);

    // ─── For non‑Philippine addresses, also capture town_city and province from the "other" fields ───
    if($country != 'Philippines') {
        $town_city = trim($_POST['other_city'] ?? '');
        $province = trim($_POST['other_state'] ?? '');
        // also override street from other_address1
        $street = trim($_POST['other_address1'] ?? '');
        $zipcode = trim($_POST['other_zip'] ?? '');
    } else {
        // For Philippine addresses, town_city and province come from PSGC lookup
        $town_city = $location_names['city'] ?? '';
        $province = $location_names['province'] ?? '';
    }

    // ─── Password ───
    $hashed_pass = password_hash($student_id, PASSWORD_BCRYPT);

    // ─── Insert Query ───
    $insert_sql = "INSERT INTO scholar_info(
        student_id, philsys_id, lrn, four_ps_id,
        last_name, given_name, ext_name, middle_name,
        sex, birthdate, scholarship_program_id, program_id, year_level,
        academic_status,
        father_last_name, father_given_name, father_middle_name,
        mother_last_name, mother_given_name, mother_middle_name,
        street, barangay_id, city_id, province_id, region_id, zipcode, permanent_address, country,
        town_city, province,
        disability, contact_num, email, ip_group,
        username, password, school_year
    ) VALUES(
        ?, ?, ?, ?,
        ?, ?, ?, ?,
        ?, ?, ?, ?, ?,
        'Active',
        ?, ?, ?,
        ?, ?, ?,
        ?, ?, ?, ?, ?, ?, ?, ?,
        ?, ?,
        ?, ?, ?, ?,
        ?, ?, ?
    )";

    $stmt = $conn->prepare($insert_sql);

    // Build type string (35 variables)
    $types = 'ssssssss'; // 1-8: student_id, philsys_id, lrn, four_ps_id, last_name, given_name, ext_name, middle_name
    $types .= 'i';       // 9: sex (int)
    $types .= 's';       // 10: birthdate
    $types .= 'iii';     // 11-13: scholarship_program_id, program_id, year_level
    $types .= 'sssssss'; // 14-20: father_last, father_given, father_middle, mother_last, mother_given, mother_middle, street
    $types .= 'iiii';    // 21-24: barangay_id, city_id, province_id, region_id
    $types .= 'sssssssssss'; // 25-35: zipcode, permanent_address, country, town_city, province, disability, contact_num, email, ip_group, username, password, school_year
    // Actually count: 25 zipcode, 26 perm_address, 27 country, 28 town_city, 29 province, 30 disability, 31 contact_num, 32 email, 33 ip_group, 34 username, 35 password, 36 school_year? Wait, we have 36? Let's recount columns in the INSERT.

    // Let's list the columns and their placeholders:
    // 1 student_id, 2 philsys_id, 3 lrn, 4 four_ps_id,
    // 5 last_name, 6 given_name, 7 ext_name, 8 middle_name,
    // 9 sex, 10 birthdate, 11 scholarship_program_id, 12 program_id, 13 year_level,
    // (14 academic_status is hardcoded 'Active' – no placeholder)
    // 15 father_last_name, 16 father_given_name, 17 father_middle_name,
    // 18 mother_last_name, 19 mother_given_name, 20 mother_middle_name,
    // 21 street, 22 barangay_id, 23 city_id, 24 province_id, 25 region_id,
    // 26 zipcode, 27 permanent_address, 28 country,
    // 29 town_city, 30 province,
    // 31 disability, 32 contact_num, 33 email, 34 ip_group,
    // 35 username, 36 password, 37 school_year.
    // That's 37 placeholders.

    // So we need 37 characters in $types.
    // Let's build carefully:
    $types = 'ssssssss'; // 1-8 (8)
    $types .= 'i';       // 9 (9)
    $types .= 's';       // 10 (10)
    $types .= 'iii';     // 11-13 (13)
    $types .= 'sssssss'; // 14-20 (20)
    $types .= 'iiii';    // 21-24 (24)
    $types .= 'sssssssssssss'; // 25-37 (13) => 24+13 = 37, yes.

    $stmt->bind_param(
        $types,
        $student_id, $philsys_id, $lrn, $four_ps_id,
        $last_name, $first_name, $ext_name, $middle_name,
        $sex, $birth_date, $scholarship_program_id, $program_id, $year_lvl,
        $father_last_name, $father_first_name, $father_middle_name,
        $mother_last_name, $mother_first_name, $mother_middle_name,
        $street, $barangay_id, $city_id, $province_id, $region_id,
        $zipcode, $permanent_address, $country,
        $town_city, $province,
        $disability, $student_contact, $email, $ip_group,
        $student_id, $hashed_pass, $school_year
    );

    if($stmt->execute()) {
        $_SESSION['success'] = "Scholar added successfully.";
        // Get the inserted scholar_id for redirect
        $scholar_id = $conn->insert_id;
        header("Location: ../../admin/scholar_profile.php?scholar_id=" . $scholar_id);
        exit();
    } else {
        $_SESSION['fail'] = "Failed to add scholar: " . $stmt->error;
        header("Location: ../../admin/add_scholar.php");
        exit();
    }
}
?>