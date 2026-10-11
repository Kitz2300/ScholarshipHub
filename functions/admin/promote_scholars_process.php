<?php 
require_once __DIR__ . "/../../includes/conn.php";
session_start();

// Helper function to calculate next school year (e.g. "2025 - 2026" -> "2026 - 2027")
function getNextSchoolYear($sy) {
    $parts = explode('-', str_replace(' ', '', $sy));
    if (count($parts) === 2 && is_numeric($parts[0]) && is_numeric($parts[1])) {
        return ((int)$parts[0] + 1) . ' - ' . ((int)$parts[1] + 1);
    }
    return $sy;
}

// Helper function to get or duplicate scholarship program for target school year & 1st semester
function getOrCreateProgramForSY($conn, $source_program_id, $target_sy, &$was_duplicated = false) {
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

        // 2. Check if program entry already exists for this code in target school year (1st Semester)
        $check_sql = "SELECT id FROM scholarship_program 
                      WHERE code = ? 
                        AND (REPLACE(school_year, ' ', '') = ? OR REPLACE(school_year, ' ', '') = ?)
                        AND (semester = '1st Semester' OR semester IS NULL OR semester = '')
                        AND is_archived = 0 
                      LIMIT 1";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("sss", $code, $raw_sp_sy, $raw_sp_sy);
        $check_stmt->execute();
        $check_res = $check_stmt->get_result();

        if ($existing = $check_res->fetch_assoc()) {
            $check_stmt->close();
            return (int)$existing['id'];
        }
        $check_stmt->close();

        // 3. Duplicate program for target school year & 1st Semester if missing
        $dup_sp_sql = "INSERT INTO scholarship_program (
                          name, code, school_year, semester, provider_id, scholarship_type, 
                          description, pay_amount, min_gwa_req, is_archived, is_active
                       ) VALUES (?, ?, ?, '1st Semester', ?, ?, ?, ?, ?, 0, 1)";
        $dup_sp_stmt = $conn->prepare($dup_sp_sql);
        $dup_sp_stmt->bind_param(
            "sssissdd", 
            $src_row['name'], 
            $src_row['code'], 
            $formatted_sp_sy, 
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['promote_scholars'])) {
    $user_id = $_SESSION['user_id'] ?? $_SESSION['id'] ?? 0;
    $selected_ids = array_map('intval', $_POST['promote_scholars']);
    $target_choices = isset($_POST['target_standing_choice']) && is_array($_POST['target_standing_choice']) 
                      ? $_POST['target_standing_choice'] 
                      : [];

    if ($user_id <= 0 || empty($selected_ids)) {
        $_SESSION['fail'] = "No scholars selected for promotion.";
        header("Location: ../../admin/promote_scholars.php");
        exit();
    }

    $conn->begin_transaction();

    try {
        $audit_sql = "INSERT INTO audit_trail (user_id, action) VALUES (?, ?)";
        $audit_stmt = $conn->prepare($audit_sql);

        // Inserts new year-level snapshot row resetting semester to '1st Semester'
        $duplicate_sql = "INSERT INTO scholar_info (
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
                            profile_picture, program_id, ?, ?, ?, '1st Semester',
                            ?, loa_semester, loa_school_year, 0
                          FROM scholar_info WHERE id = ?
                          ON DUPLICATE KEY UPDATE 
                            scholarship_program_id = VALUES(scholarship_program_id),
                            year_level = VALUES(year_level),
                            academic_status = VALUES(academic_status)";
        
        $dup_stmt = $conn->prepare($duplicate_sql);

        $promoted_count = 0;
        $graduated_count = 0;
        $irregular_count = 0;
        $overrides_count = 0;
        $duplicated_programs = [];
        $target_sy_recorded = '';

        foreach ($selected_ids as $scholar_id) {
            $eval_sql = "SELECT 
                            si.id, 
                            si.student_id,
                            si.given_name, 
                            si.last_name, 
                            si.year_level, 
                            si.school_year,
                            si.scholarship_program_id,
                            sp.name AS scholarship_program_name,
                            sp.min_gwa_req,
                            COUNT(DISTINCT CASE WHEN ap.semester IN ('1st Semester', '2nd Semester', '1st', '2nd') THEN ap.semester END) AS required_sems_count,
                            MAX(ap.gwa) AS worst_gwa,
                            next_si.id AS already_promoted_id
                         FROM scholar_info si
                         INNER JOIN scholarship_program sp ON si.scholarship_program_id = sp.id
                         INNER JOIN scholar_info all_si ON all_si.student_id = si.student_id AND all_si.school_year = si.school_year
                         LEFT JOIN academic_performance ap ON all_si.id = ap.scholar_id AND ap.school_year = si.school_year
                         LEFT JOIN scholar_info next_si ON next_si.student_id = si.student_id AND next_si.school_year = ?
                         WHERE si.id = ?
                         GROUP BY si.id";

            $eval_stmt = $conn->prepare($eval_sql);
            
            // Fetch current SY first to calculate and bind target SY
            $sy_lookup = $conn->query("SELECT school_year FROM scholar_info WHERE id = $scholar_id LIMIT 1");
            if ($sy_row = $sy_lookup->fetch_assoc()) {
                $calc_next_sy = getNextSchoolYear($sy_row['school_year']);
                $eval_stmt->bind_param("si", $calc_next_sy, $scholar_id);
            }

            $eval_stmt->execute();
            $eval_res = $eval_stmt->get_result();

            if ($s_row = $eval_res->fetch_assoc()) {
                if (!empty($s_row['already_promoted_id'])) {
                    continue; // Skip if already promoted to target SY
                }

                $curr_yl_str = trim((string)$s_row['year_level']);
                $curr_sy = $s_row['school_year'];
                $next_sy = getNextSchoolYear($curr_sy);
                $target_sy_recorded = $next_sy;
                $source_sp_id = (int)$s_row['scholarship_program_id'];
                $sp_name = $s_row['scholarship_program_name'] ?? 'Scholarship Program';

                // Ensure scholarship program entry exists for target school year (1st Semester)
                $was_duplicated = false;
                $target_sp_id = getOrCreateProgramForSY($conn, $source_sp_id, $next_sy, $was_duplicated);
                if ($was_duplicated) {
                    $duplicated_programs[$sp_name] = $next_sy;
                }

                $min_gwa = (float) $s_row['min_gwa_req'];
                $sems_count = (int) $s_row['required_sems_count'];
                $worst_gwa = (float) $s_row['worst_gwa'];
                $full_name = $s_row['last_name'] . ", " . $s_row['given_name'];

                // Calculate target year_level and target status ('Graduated' or 'Irregular' for 4th year)
                if ($curr_yl_str === '4th Year' || $curr_yl_str === '4' || (is_numeric($curr_yl_str) && (int)$curr_yl_str >= 4)) {
                    $choice = $target_choices[$scholar_id] ?? 'Graduated';
                    if ($choice === 'Irregular') {
                        $target_yl = 'Irregular';
                        $target_status = 'Enrolled';
                        $irregular_count++;
                        $next_standing = "Irregular";
                    } else {
                        $target_yl = '4th Year';
                        $target_status = 'Graduated';
                        $graduated_count++;
                        $next_standing = "Graduated";
                    }
                } elseif ($curr_yl_str === '1st Year' || $curr_yl_str === '1') {
                    $target_yl = '2nd Year';
                    $target_status = 'Enrolled';
                    $promoted_count++;
                    $next_standing = "2nd Year";
                } elseif ($curr_yl_str === '2nd Year' || $curr_yl_str === '2') {
                    $target_yl = '3rd Year';
                    $target_status = 'Enrolled';
                    $promoted_count++;
                    $next_standing = "3rd Year";
                } elseif ($curr_yl_str === '3rd Year' || $curr_yl_str === '3') {
                    $target_yl = '4th Year';
                    $target_status = 'Enrolled';
                    $promoted_count++;
                    $next_standing = "4th Year";
                } elseif ($curr_yl_str === 'Irregular') {
                    $target_yl = 'Irregular';
                    $target_status = 'Enrolled';
                    $irregular_count++;
                    $next_standing = "Irregular";
                } else {
                    $target_yl = $curr_yl_str;
                    $target_status = 'Enrolled';
                    $promoted_count++;
                    $next_standing = $curr_yl_str;
                }

                if ($min_gwa > 0) {
                    if ($sems_count < 2) {
                        $overrides_count++;
                        $override_log = "Override Promotion: Scholar #$scholar_id ($full_name) was manually promoted to $next_standing for SY $next_sy despite incomplete semester grade records.";
                        $audit_stmt->bind_param("is", $user_id, $override_log);
                        $audit_stmt->execute();
                    } elseif ($worst_gwa > $min_gwa) {
                        $overrides_count++;
                        $override_log = "Override Promotion: Scholar #$scholar_id ($full_name) was manually promoted to $next_standing for SY $next_sy despite failing GWA requirement.";
                        $audit_stmt->bind_param("is", $user_id, $override_log);
                        $audit_stmt->execute();
                    }
                }

                // Bind parameters for insertion
                $dup_stmt->bind_param("isssi", $target_sp_id, $target_yl, $next_sy, $target_status, $scholar_id);
                $dup_stmt->execute();
            }
            $eval_stmt->close();
        }

        $dup_stmt->close();

        $summary_log = "Executed bulk year-level promotion for " . count($selected_ids) . " scholar(s) ($promoted_count Standard Advancement, $graduated_count Graduated, $irregular_count Irregular, $overrides_count Manual Overrides).";
        $audit_stmt->bind_param("is", $user_id, $summary_log);
        $audit_stmt->execute();
        $audit_stmt->close();

        $conn->commit();

        $_SESSION['toasts'] = [];

        $msg_parts = [];
        if ($promoted_count > 0) $msg_parts[] = "$promoted_count promoted to next year level";
        if ($graduated_count > 0) $msg_parts[] = "$graduated_count set to Graduated";
        if ($irregular_count > 0) $msg_parts[] = "$irregular_count set to Irregular";

        $scholar_toast_msg = "Successfully processed " . count($selected_ids) . " scholar(s) for SY $target_sy_recorded (" . implode(", ", $msg_parts) . ").";

        $_SESSION['toasts'][] = [
            'title' => 'Year Level Promoted',
            'message' => $scholar_toast_msg,
            'type' => 'success',
            'duration' => 5000
        ];

        if (!empty($duplicated_programs)) {
            $dup_names = array_keys($duplicated_programs);
            $dup_sy = reset($duplicated_programs);
            if (count($dup_names) === 1) {
                $dup_toast_msg = "A duplicate of scholarship program '{$dup_names[0]}' has been created for the new school year ($dup_sy).";
            } else {
                $dup_toast_msg = "Duplicates of " . count($dup_names) . " scholarship programs (" . implode(', ', $dup_names) . ") have been created for the new school year ($dup_sy).";
            }

            $_SESSION['toasts'][] = [
                'title' => 'Program Duplicated',
                'message' => $dup_toast_msg,
                'type' => 'info',
                'duration' => 6500
            ];
        }

        $_SESSION['success'] = $scholar_toast_msg;

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['fail'] = "Promotion failed: " . $e->getMessage();
    }

    header("Location: ../../admin/promote_scholars.php");
    exit();
} else {
    $_SESSION['fail'] = "Invalid form submission.";
    header("Location: ../../admin/promote_scholars.php");
    exit();
}
?>