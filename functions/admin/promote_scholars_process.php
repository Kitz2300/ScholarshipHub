<?php 
require_once __DIR__ . "/../../includes/conn.php";
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['promote_scholars'])) {
    $user_id = $_SESSION['user_id'] ?? $_SESSION['id'] ?? 0;
    $selected_ids = array_map('intval', $_POST['promote_scholars']);

    if ($user_id <= 0 || empty($selected_ids)) {
        $_SESSION['fail'] = "No scholars selected for promotion.";
        header("Location: ../../admin/promote_scholars.php");
        exit();
    }

    $conn->begin_transaction();

    try {
        $audit_sql = "INSERT INTO audit_trail (user_id, action) VALUES (?, ?)";
        $audit_stmt = $conn->prepare($audit_sql);

        $promoted_count = 0;
        $pending_grad_count = 0;
        $overrides_count = 0;

        foreach ($selected_ids as $scholar_id) {
            // Re-evaluating scholar details server-side for integrity
            $eval_sql = "SELECT 
                            si.id, 
                            si.given_name, 
                            si.last_name, 
                            si.year_level, 
                            si.school_year,
                            sp.min_gwa_req,
                            COUNT(DISTINCT CASE WHEN ap.semester IN ('1st Semester', '2nd Semester', '1st', '2nd') THEN ap.semester END) AS required_sems_count,
                            MAX(ap.gwa) AS worst_gwa
                         FROM scholar_info si
                         INNER JOIN scholarship_program sp ON si.scholarship_program_id = sp.id
                         LEFT JOIN academic_performance ap ON si.id = ap.scholar_id AND ap.school_year = si.school_year
                         WHERE si.id = ?
                         GROUP BY si.id";
            $eval_stmt = $conn->prepare($eval_sql);
            $eval_stmt->bind_param("i", $scholar_id);
            $eval_stmt->execute();
            $eval_res = $eval_stmt->get_result();

            if ($s_row = $eval_res->fetch_assoc()) {
                $curr_year = (int) $s_row['year_level'];
                $min_gwa = (float) $s_row['min_gwa_req'];
                $sems_count = (int) $s_row['required_sems_count'];
                $worst_gwa = (float) $s_row['worst_gwa'];
                $full_name = $s_row['last_name'] . ", " . $s_row['given_name'];

                $next_standing = ($curr_year >= 4) ? "4th Year (Pending Graduation)" : ($curr_year + 1) . "th Year";

                // Check and log manual overrides for audit trail
                if ($min_gwa > 0) {
                    if ($sems_count < 2) {
                        $overrides_count++;
                        $override_log = "Override Promotion: Scholar #$scholar_id ($full_name) was manually promoted to $next_standing despite incomplete semester grade records (Missing 1st/2nd Semester).";
                        $audit_stmt->bind_param("is", $user_id, $override_log);
                        $audit_stmt->execute();
                    } elseif ($worst_gwa > $min_gwa) {
                        $overrides_count++;
                        $override_log = "Override Promotion: Scholar #$scholar_id ($full_name) was manually promoted to $next_standing despite failing GWA requirement (Student GWA: " . number_format($worst_gwa, 2) . ", Required: " . number_format($min_gwa, 2) . ").";
                        $audit_stmt->bind_param("is", $user_id, $override_log);
                        $audit_stmt->execute();
                    }
                }

                // Apply year level or status update
                if ($curr_year >= 4) {
                    $update_sql = "UPDATE scholar_info SET academic_status = 'Pending Graduation' WHERE id = ?";
                    $update_stmt = $conn->prepare($update_sql);
                    $update_stmt->bind_param("i", $scholar_id);
                    $update_stmt->execute();
                    $pending_grad_count++;
                } else {
                    $update_sql = "UPDATE scholar_info SET year_level = year_level + 1, academic_status = 'Enrolled' WHERE id = ?";
                    $update_stmt = $conn->prepare($update_sql);
                    $update_stmt->bind_param("i", $scholar_id);
                    $update_stmt->execute();
                    $promoted_count++;
                }
            }
        }

        // Summary Audit Log
        $summary_log = "Executed bulk year-level promotion for " . count($selected_ids) . " scholar(s) ($promoted_count Standard Advancement, $pending_grad_count Moved to Pending Graduation, $overrides_count Manual Overrides).";
        $audit_stmt->bind_param("is", $user_id, $summary_log);
        $audit_stmt->execute();

        $conn->commit();
        $_SESSION['success'] = "Successfully promoted " . count($selected_ids) . " scholar(s).";

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