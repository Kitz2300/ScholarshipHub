<?php 
require_once __DIR__ . "/../../includes/conn.php";
session_start();

// Helper function to get or create scholarship program for target term (School Year & Semester)
function getOrCreateProgramForTerm($conn, $source_program_id, $target_sy, $target_sem, &$was_duplicated = false) {
    $was_duplicated = false;
    $sy_parts = explode('-', str_replace(' ', '', $target_sy));
    $formatted_sp_sy = (count($sy_parts) === 2) ? $sy_parts[0] . ' - ' . $sy_parts[1] : $target_sy;
    $raw_sp_sy = (count($sy_parts) === 2) ? $sy_parts[0] . '-' . $sy_parts[1] : $target_sy;

    // 1. Fetch source program info
    $src_sql = "SELECT * FROM scholarship_program WHERE id = ?";
    $src_stmt = $conn->prepare($src_sql);
    $src_stmt->bind_param("i", $source_program_id);
    $src_stmt->execute();
    $src_res = $src_stmt->get_result();

    if ($src_row = $src_res->fetch_assoc()) {
        $code = $src_row['code'];
        $src_stmt->close();

        // 2. Check if program entry already exists for this code in target school year & semester
        $check_sql = "SELECT id FROM scholarship_program 
                      WHERE code = ? 
                        AND (REPLACE(school_year, ' ', '') = ? OR REPLACE(school_year, ' ', '') = ?)
                        AND semester = ?
                        AND is_archived = 0 
                      LIMIT 1";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ssss", $code, $raw_sp_sy, $raw_sp_sy, $target_sem);
        $check_stmt->execute();
        $check_res = $check_stmt->get_result();

        if ($existing = $check_res->fetch_assoc()) {
            $check_stmt->close();
            return (int)$existing['id'];
        }
        $check_stmt->close();

        // 3. Duplicate program for target school year & semester if missing
        $dup_sp_sql = "INSERT INTO scholarship_program (
                          name, code, school_year, semester, provider_id, scholarship_type, 
                          description, pay_amount, min_gwa_req, is_archived, is_active
                       ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 1)";
        $dup_sp_stmt = $conn->prepare($dup_sp_sql);
        $dup_sp_stmt->bind_param(
            "ssssissdd", 
            $src_row['name'], 
            $src_row['code'], 
            $formatted_sp_sy,
            $target_sem, 
            $src_row['provider_id'], 
            $src_row['scholarship_type'], 
            $src_row['description'], 
            $src_row['pay_amount'], 
            $src_row['min_gwa_req']
        );
        if ($dup_sp_stmt->execute()) {
            $new_id = (int)$dup_sp_stmt->insert_id;
            $dup_sp_stmt->close();
            $was_duplicated = true;
            return $new_id;
        }
    }
    return $source_program_id;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['advance_scholars'])) {
    $user_id = $_SESSION['user_id'] ?? $_SESSION['id'] ?? 0;
    $selected_ids = isset($_POST['advance_scholars']) && is_array($_POST['advance_scholars']) 
                    ? array_map('intval', $_POST['advance_scholars']) 
                    : [];
    $target_school_year = trim($_POST['target_school_year'] ?? '');
    $target_semester = trim($_POST['target_semester'] ?? '');

    if ($user_id <= 0) {
        $_SESSION['fail'] = "Unauthorized access. Session expired or user not logged in.";
        header("Location: ../../admin/term_advancement.php");
        exit();
    }

    if (empty($selected_ids)) {
        $_SESSION['fail'] = "No scholars were selected for term advancement.";
        header("Location: ../../admin/term_advancement.php");
        exit();
    }

    if (empty($target_school_year) || empty($target_semester)) {
        $_SESSION['fail'] = "Target School Year and Target Semester are required.";
        header("Location: ../../admin/term_advancement.php");
        exit();
    }

    $conn->begin_transaction();

    try {
        $placeholders = implode(',', array_fill(0, count($selected_ids), '?'));
        
        // Joined query checking for existing later snapshots to prevent re-advancing older term snapshots
        $check_sql = "SELECT 
                        si.id AS scholar_id,
                        si.student_id,
                        si.school_year,
                        si.semester,
                        si.scholarship_program_id,
                        sp.min_gwa_req,
                        ap.gwa AS current_gwa,
                        later_si.id AS already_advanced_id
                      FROM scholar_info si
                      INNER JOIN scholarship_program sp ON si.scholarship_program_id = sp.id
                      LEFT JOIN academic_performance ap ON ap.scholar_id = si.id 
                          AND ap.school_year = si.school_year 
                          AND ap.semester = si.semester
                      LEFT JOIN scholar_info later_si ON later_si.student_id = si.student_id 
                          AND later_si.is_archived = 0
                          AND (
                              (later_si.school_year = si.school_year AND (
                                  (si.semester = '1st Semester' AND later_si.semester IN ('2nd Semester', 'Summer')) OR
                                  (si.semester = '2nd Semester' AND later_si.semester = 'Summer')
                              ))
                              OR (REPLACE(later_si.school_year, ' ', '') > REPLACE(si.school_year, ' ', ''))
                          )
                      WHERE si.id IN ($placeholders) AND si.is_archived = 0";

        $check_stmt = $conn->prepare($check_sql);
        $types = str_repeat('i', count($selected_ids));
        $check_stmt->bind_param($types, ...$selected_ids);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        $valid_scholars = [];
        $skipped_count = 0;

        while ($row = $check_result->fetch_assoc()) {
            $requires_gwa = (!empty($row['min_gwa_req']) && floatval($row['min_gwa_req']) > 0);
            $has_gwa = ($row['current_gwa'] !== null && $row['current_gwa'] !== '');
            $gwa_failed = ($requires_gwa && $has_gwa && floatval($row['current_gwa']) > floatval($row['min_gwa_req']));
            $already_advanced = !empty($row['already_advanced_id']);

            // Skip if scholar already advanced to a later term or failed GWA requirement
            if ($already_advanced || ($requires_gwa && (!$has_gwa || $gwa_failed))) {
                $skipped_count++;
            } else {
                $valid_scholars[] = $row;
            }
        }
        $check_stmt->close();

        if (empty($valid_scholars)) {
            throw new Exception("None of the selected scholars are eligible for advancement. Scholars may already be in a later term or missing passing GWA grades.");
        }

        // Inserts new term snapshot reassigning scholarship_program_id to target term instance
        $advance_sql = "INSERT INTO scholar_info (
                            student_id, username, philsys_id, lrn, four_ps_id, last_name, given_name, middle_name, ext_name,
                            sex, birthdate, disability, ip_group, is_solo_parent, is_raised_by_solo_parent, is_gida, gida_details,
                            is_farmer_fisherfolk, is_rebel_returnee, permanent_address,
                            street, barangay_id, city_id, province_id, region_id, town_city, province, zipcode,
                            country, contact_num, email, father_last_name, father_given_name, father_middle_name,
                            father_contact, mother_last_name, mother_given_name, mother_middle_name, mother_contact,
                            profile_picture, program_id, scholarship_program_id, year_level, school_year, semester,
                            academic_status, loa_semester, loa_school_year, is_archived
                        )
                        SELECT 
                            student_id, COALESCE(username, student_id), philsys_id, lrn, four_ps_id, last_name, given_name, middle_name, ext_name,
                            sex, birthdate, disability, ip_group,
                            COALESCE(is_solo_parent, 0), COALESCE(is_raised_by_solo_parent, 0), COALESCE(is_gida, 0), gida_details,
                            COALESCE(is_farmer_fisherfolk, 0), COALESCE(is_rebel_returnee, 0), permanent_address,
                            street, barangay_id, city_id, province_id, region_id, town_city, province, zipcode,
                            country, contact_num, email, father_last_name, father_given_name, father_middle_name,
                            father_contact, mother_last_name, mother_given_name, mother_middle_name, mother_contact,
                            profile_picture, program_id, ?, year_level, ?, ?,
                            academic_status, loa_semester, loa_school_year, 0
                        FROM scholar_info WHERE id = ?
                        ON DUPLICATE KEY UPDATE 
                            scholarship_program_id = VALUES(scholarship_program_id),
                            academic_status = VALUES(academic_status)";

        $advance_stmt = $conn->prepare($advance_sql);

        $advanced_count = 0;
        foreach ($valid_scholars as $v_scholar) {
            $scholar_id = (int)$v_scholar['scholar_id'];
            $source_sp_id = (int)$v_scholar['scholarship_program_id'];

            // Ensure matching scholarship program exists for target SY and Semester
            $target_sp_id = getOrCreateProgramForTerm($conn, $source_sp_id, $target_school_year, $target_semester);

            $advance_stmt->bind_param("issi", $target_sp_id, $target_school_year, $target_semester, $scholar_id);
            if ($advance_stmt->execute()) {
                $advanced_count++;
            }
        }
        $advance_stmt->close();

        if ($advanced_count === 0) {
            throw new Exception("No scholar records were updated or advanced.");
        }

        $audit_log = "Advanced active term for $advanced_count scholar(s) to SY $target_school_year - $target_semester." . ($skipped_count > 0 ? " ($skipped_count skipped due to missing/failing GWA or existing later term)" : "");
        $audit_sql = "INSERT INTO audit_trail (user_id, action) VALUES (?, ?)";
        $audit_stmt = $conn->prepare($audit_sql);

        if ($audit_stmt) {
            $audit_stmt->bind_param("is", $user_id, $audit_log);
            $audit_stmt->execute();
            $audit_stmt->close();
        }

        $conn->commit();

        if ($skipped_count > 0) {
            $_SESSION['success'] = "Successfully advanced $advanced_count scholar(s) to SY $target_school_year - $target_semester. ($skipped_count scholar(s) skipped as they are already advanced or missing passing GWA).";
        } else {
            $_SESSION['success'] = "Successfully advanced $advanced_count scholar(s) to SY $target_school_year - $target_semester.";
        }

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['fail'] = "Term advancement failed: " . $e->getMessage();
    }

    header("Location: ../../admin/term_advancement.php");
    exit();

} else {
    $_SESSION['fail'] = "Invalid form submission or request method.";
    header("Location: ../../admin/term_advancement.php");
    exit();
}
?>