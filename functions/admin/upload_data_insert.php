<?php
require_once __DIR__ . "/../../includes/conn.php";
require_once __DIR__ . "/../../includes/address_helper.php";
require_once __DIR__ . "/../../includes/email_helper.php";
session_start();

set_time_limit(300);

$success_count = 0;
$error_count = 0;

if(isset($_POST['confirm']) && isset($_POST['scholarship_program_id']) && !empty($_POST['valid_rows'])) {
    $file_name = $_POST['file_name'];
    $scholarship_program_id = (int)$_POST['scholarship_program_id'];
    $valid_rows_json = urldecode($_POST['valid_rows']);
    $valid_rows = json_decode($valid_rows_json, true);
    $column_names = isset($_POST['column_names']) ? $_POST['column_names'] : [];

    $mapped_columns = isset($_SESSION['mapped_columns']) ? $_SESSION['mapped_columns'] : [];
    if(empty($mapped_columns)) {
        $_SESSION['fail'] = "No column mapping found. Please go back and select columns.";
        header("Location: ../../admin/upload_excel.php");
        exit();
    }

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
    $school_year_stmt->close();

    $emails_to_send = [];

    // ─── START TRANSACTION (Massive speed gain for bulk inserts) ───
    $conn->begin_transaction();

    try {
        $types = 'ssssssss' . 'i' . 's' . 'iii' . 'sssssss' . 'iiii' . 'sssssssssss'; 
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

        $program_sql = "SELECT id FROM program WHERE name = ?";
        $program_stmt = $conn->prepare($program_sql);

        $note_sql = "INSERT INTO scholar_notes(scholar_id, notes) VALUES(?, ?)";
        $note_stmt = $conn->prepare($note_sql);

        foreach($valid_rows as $rows => $rowData) {
            $data = [];
            foreach($mapped_columns as $field => $index) {
                $data[$field] = $rowData[$index] ?? '';
            }

            $student_id    = $data['student_id'] ?? '';
            $philsys_id    = $data['philsys_id'] ?? '';
            $lrn           = $data['lrn'] ?? '';
            $four_ps_id    = $data['four_ps_id'] ?? '';
            $last_name     = $data['last_name'] ?? '';
            $given_name    = $data['given_name'] ?? '';
            $ext_name      = empty($data['ext_name']) ? "N/A" : $data['ext_name'];
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
            $disability    = empty($data['disability']) ? "Not applicable" : $data['disability'];
            $contact_num   = $data['contact_num'] ?? '';
            $email         = $data['email'] ?? '';
            $ip_group      = empty($data['ip_group']) ? "Not applicable" : $data['ip_group'];

            if (is_numeric($sex)) {
                $sex = (int)$sex;
            } else {
                $sex = strtolower(trim($sex)) === 'female' ? 1 : 0;
            }

            $province_data = getProvinceId($conn, $province);
            $province_id = $province_data ? $province_data['id'] : null;
            $region_id = $province_data ? $province_data['region_id'] : null;
            $city_id = getCityId($conn, $town_city, $province_id);
            $barangay_name = extractBarangayName($street);
            $barangay_id = getBarangayId($conn, $barangay_name, $city_id);

            $permanent_address = buildPermanentAddress($street, $barangay_name, $town_city, $province);

            $program_id = 0;
            if(!empty($program)) {
                $program_stmt->bind_param('s', $program);
                $program_stmt->execute();
                $program_result = $program_stmt->get_result();
                if($row = $program_result->fetch_assoc()) {
                    $program_id = $row['id'];
                }
            }

            $hashed_pass = password_hash($student_id, PASSWORD_BCRYPT);

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

                if (!empty($email)) {
                    $emails_to_send[] = [
                        'email' => $email,
                        'given_name' => $given_name,
                        'student_id' => $student_id
                    ];
                }
            } else {
                $error_count++;
                continue;
            }

            $mapped_count = count($mapped_columns) + 1;
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
                    $note_stmt->bind_param("is", $scholar_id, $note_content);
                    $note_stmt->execute();
                }
            }
        }

        // Fetch user details for import history log
        $u_id = $_SESSION['user_id'] ?? null;
        $u_name = null;
        $u_role = $_SESSION['role'] ?? null;
        if ($u_id) {
            $u_stmt = $conn->prepare("SELECT CONCAT_WS(' ', given_name, middle_name, last_name) AS full_name, role FROM scholarship_users WHERE id = ?");
            $u_stmt->bind_param("i", $u_id);
            $u_stmt->execute();
            $u_res = $u_stmt->get_result();
            if ($u_row = $u_res->fetch_assoc()) {
                $u_name = $u_row['full_name'];
                if (empty($u_role)) {
                    $u_role = $u_row['role'];
                }
            }
            $u_stmt->close();
        }

        $history_sql = "INSERT INTO import_history(user_id, user_name, role, filename) VALUES(?, ?, ?, ?)";
        $history_stmt = $conn->prepare($history_sql);
        $history_stmt->bind_param('isss', $u_id, $u_name, $u_role, $file_name);
        $history_stmt->execute();

        $user_id = $_SESSION['user_id'] ?? $_SESSION['id'] ?? 0;
        $action = "Imported $success_count scholar record(s) from Excel file '$file_name' (Failed: $error_count)";
        $audit_sql = "INSERT INTO audit_trail (user_id, action) VALUES (?, ?)";
        $audit_stmt = $conn->prepare($audit_sql);
        $audit_stmt->bind_param("is", $user_id, $action);
        $audit_stmt->execute();

        $conn->commit();

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['fail'] = "Import transaction failed: " . $e->getMessage();
        header("Location: ../../admin/upload_excel.php");
        exit();
    }

    // Send deferred emails
    foreach ($emails_to_send as $item) {
        sendAccountCreatedEmail($item['email'], $item['given_name'], $item['student_id']);
    }

    if($success_count > 0 && $error_count == 0) {
        $_SESSION['success'] = "Scholar record successfully imported and email notifications sent.";
        unset($_SESSION['mapped_columns'], $_SESSION['valid_rows'], $_SESSION['errors_by_row'], $_SESSION['import_processed']);
        header("Location: ../../admin/scholar_list.php");
        exit();
    } else {
        $_SESSION['fail'] = "Scholar record import completed with $error_count error(s).";
        header("Location: ../../admin/upload_excel.php");
        exit();
    }
}
?>