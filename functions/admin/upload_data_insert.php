<?php
require_once __DIR__ . "/../../includes/conn.php";
require_once __DIR__ . "/../../includes/address_helper.php";
session_start();

$success_count = 0;
$error_count = 0;

if(isset($_POST['confirm']) && isset($_POST['scholarship_program_id']) && !empty($_POST['valid_rows'])) {
    $file_name = $_POST['file_name'];
    $scholarship_program_id = $_POST['scholarship_program_id'];
    $valid_rows_json = urldecode($_POST['valid_rows']);
    $valid_rows = json_decode($valid_rows_json, true);
    $column_names = isset($_POST['column_names']) ? $_POST['column_names'] : [];

    // Get the mapped columns from session
    $mapped_columns = isset($_SESSION['mapped_columns']) ? $_SESSION['mapped_columns'] : [];
    if(empty($mapped_columns)) {
        $_SESSION['fail'] = "No column mapping found. Please go back and select columns.";
        header("Location: ../../admin/upload_excel.php");
        exit();
    }

    // Get school_year from scholarship program
    $school_year_sql = "SELECT school_year FROM scholarship_program WHERE id = ?";
    $school_year_stmt = $conn->prepare($school_year_sql);
    $school_year_stmt->bind_param('i', $scholarship_program_id);
    $school_year_stmt->execute();
    $school_year_result = $school_year_stmt->get_result();
    if ($row = $school_year_result->fetch_assoc()) {
        $school_year = $row['school_year'];
    } else {
        $_SESSION['fail'] = "Invalid scholarship program ID.";
        header("Location: ../../admin/upload_excel.php");
        exit();
    }

    foreach($valid_rows as $rows => $rowData) {
        // Build data array from mapping
        $data = [];
        foreach($mapped_columns as $field => $index) {
            $data[$field] = $rowData[$index] ?? '';
        }

        // Extract values (with defaults)
        $student_id    = $data['student_id'] ?? '';
        $philsys_id    = $data['philsys_id'] ?? '';
        $lrn           = $data['lrn'] ?? '';
        $four_ps_id    = $data['four_ps_id'] ?? '';
        $last_name     = $data['last_name'] ?? '';
        $given_name    = $data['given_name'] ?? '';
        $ext_name      = $data['ext_name'] ?? '';
        $middle_name   = $data['middle_name'] ?? '';
        $sex           = $data['sex'] ?? '';
        $birthdate     = $data['birthdate'] ?? '';
        $program       = $data['program'] ?? '';
        $year_lvl      = $data['year_level'] ?? '';
        $father_last   = $data['father_last'] ?? '';
        $father_given  = $data['father_given'] ?? '';
        $father_middle = $data['father_middle'] ?? '';
        $mother_last   = $data['mother_last'] ?? '';
        $mother_given  = $data['mother_given'] ?? '';
        $mother_middle = $data['mother_middle'] ?? '';
        $street        = $data['street'] ?? '';
        $town_city     = $data['town_city'] ?? '';
        $province      = $data['province'] ?? '';
        $zipcode       = $data['zipcode'] ?? '';
        $disability    = $data['disability'] ?? '';
        $contact_num   = $data['contact_num'] ?? '';
        $email         = $data['email'] ?? '';
        $ip_group      = $data['ip_group'] ?? '';

        // ─── Normalize optional fields ───
        $ext_name = empty($ext_name) ? "N/A" : $ext_name;
        $disability = empty($disability) ? "Not applicable" : $disability;
        $ip_group = empty($ip_group) ? "Not applicable" : $ip_group;

        // ─── Convert sex to integer if needed ───
        if (is_numeric($sex)) {
            $sex = (int)$sex;
        } else {
            $sex = strtolower(trim($sex)) === 'female' ? 1 : 0;
        }

        // ─── Address Lookup ───
        $province_data = getProvinceId($conn, $province);
        $province_id = $province_data ? $province_data['id'] : null;
        $region_id = $province_data ? $province_data['region_id'] : null;
        $city_id = getCityId($conn, $town_city, $province_id);
        $barangay_name = extractBarangayName($street);
        $barangay_id = getBarangayId($conn, $barangay_name, $city_id);

        // ─── Build permanent_address ───
        $permanent_address = buildPermanentAddress($street, $barangay_name, $town_city, $province);

        // ─── Get program_id ───
        $program_id = 0;
        if(!empty($program)) {
            $program_sql = "SELECT id FROM program WHERE name = ?";
            $program_stmt = $conn->prepare($program_sql);
            $program_stmt->bind_param('s', $program);
            $program_stmt->execute();
            $program_result = $program_stmt->get_result();
            if($row = $program_result->fetch_assoc()) {
                $program_id = $row['id'];
            }
        }

        // ─── Password ───
        $hashed_pass = password_hash($student_id, PASSWORD_BCRYPT);

        // ─── Build the type string dynamically ───
        // Data types: 1..8 string, 9 int, 10 string, 11-13 int, 14-20 string (father/mother/street), 21-24 int, 25-35 string (zipcode, perm_address, town, province, disability, contact, email, ip_group, username, password, school_year)
        // We'll use an array of types for clarity
        $types = 'ssssssss'; 
        $types .= 'i';       
        $types .= 's';       
        $types .= 'iii';     
        $types .= 'sssssss'; 
        $types .= 'iiii';    
        $types .= 'sssssssssss'; 

        // ─── INSERT ───
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
            'Enrolled',
            ?, ?, ?,
            ?, ?, ?,
            ?, ?, ?, ?, ?, ?, ?, 'Philippines',
            ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?
        )";

        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param(
            $types,
            $student_id, $philsys_id, $lrn, $four_ps_id,
            $last_name, $given_name, $ext_name, $middle_name,
            $sex, $birthdate, $scholarship_program_id, $program_id, $year_lvl,
            $father_last, $father_given, $father_middle,
            $mother_last, $mother_given, $mother_middle,
            $street, $barangay_id, $city_id, $province_id, $region_id, $zipcode, $permanent_address,
            $town_city, $province,
            $disability, $contact_num, $email, $ip_group,
            $student_id, $hashed_pass, $school_year
        );

        if($insert_stmt->execute()) {
            $success_count++;
        } else {
            $error_count++;
            continue;
        }

        // ─── Handle extra columns (beyond the mapped ones) ───
        $mapped_count = count($mapped_columns) + 1; // +1 for SEQ
        if(count($rowData) > $mapped_count) {
            $extra_data = [];
            for($i = $mapped_count; $i < count($rowData); $i++) {
                $col_name = isset($column_names[$i]) ? $column_names[$i] : "col_" . $i;
                $col_value = trim($rowData[$i] ?? '');
                if(!empty($col_name) && !empty($col_value)) {
                    $extra_data[$col_name] = $col_value;
                }
            }
            if(!empty($extra_data)) {
                $scholar_id = $conn->insert_id;
                $note_content = json_encode($extra_data);
                $note_sql = "INSERT INTO scholar_notes(scholar_id, notes) VALUES(?, ?)";
                $note_stmt = $conn->prepare($note_sql);
                $note_stmt->bind_param("is", $scholar_id, $note_content);
                $note_stmt->execute();
            }
        }
    }

    // ─── Log history ───
    $history_sql = "INSERT INTO import_history(user_id, filename) VALUES(?, ?)";
    $history_stmt = $conn->prepare($history_sql);
    $history_stmt->bind_param('is', $_SESSION['user_id'], $file_name);
    $history_stmt->execute();

    if($success_count > 0 && $error_count == 0) {
        $_SESSION['success'] = "Scholar record successfully imported.";
        // Clear session variables
        unset($_SESSION['mapped_columns'], $_SESSION['valid_rows'], $_SESSION['errors_by_row'], $_SESSION['import_processed']);
        header("Location: ../../admin/scholar_list.php");
        exit();
    } else {
        $_SESSION['fail'] = "Scholar record import failed. row errors: $error_count";
        header("Location: ../../admin/upload_excel.php");
        exit();
    }
}   