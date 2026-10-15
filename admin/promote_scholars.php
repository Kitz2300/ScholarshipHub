<?php 
include __DIR__ . "/../includes/conn.php";
include __DIR__ . "/../includes/check_permission.php";
session_start();

include __DIR__ . "/../includes/school_year_helper.php";

// Helper function to calculate next school year
function getNextSchoolYear($sy) {
    $parts = explode('-', str_replace(' ', '', $sy));
    if (count($parts) === 2 && is_numeric($parts[0]) && is_numeric($parts[1])) {
        return ((int)$parts[0] + 1) . ' - ' . ((int)$parts[1] + 1);
    }
    return $sy;
}

// Ensure user is logged in
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'staff')) {
    $_SESSION['fail'] = "You are not logged in.";
    header("Location: login.php");
    exit();
}

if ($_SESSION['role'] !== 'admin') {
    checkPageAccess($conn, $_SESSION['user_id'], 'promote_scholars');
}

// Check action permission
$can_promote = ($_SESSION['role'] === 'admin') || hasPermission($conn, $_SESSION['user_id'], 'promote_scholars', 'promote_year_level');

// Fetch filter selections
$program_filter = (int) ($_GET['program_id'] ?? 0);
$year_filter = $_GET['year_level'] ?? '0';
$school_year_filter = $_GET['school_year'] ?? getSelectedSchoolYear();

$next_school_year = getNextSchoolYear($school_year_filter);
$available_years = getAvailableSchoolYears($conn);

// Fetch active scholarship programs for dropdown
$sp_sql = "SELECT MIN(id) AS id, name, code 
           FROM scholarship_program 
           WHERE is_archived = 0 
           GROUP BY name, code 
           ORDER BY name ASC";
$sp_result = $conn->query($sp_sql);

// Build query for scholars evaluation in selected school year
$where_clauses = [
    "si.is_archived = 0", 
    "sp.is_archived = 0", 
    "si.academic_status IN ('Enrolled', 'Below GWA Requirement')",
    "si.school_year = ?"
];
$params = [$school_year_filter];
$types = "s";

if ($program_filter > 0) {
    $where_clauses[] = "sp.name = (SELECT name FROM scholarship_program WHERE id = ?)";
    $params[] = $program_filter;
    $types .= "i";
}

if ($year_filter !== '0' && !empty($year_filter)) {
    $where_clauses[] = "si.year_level = ?";
    $params[] = $year_filter;
    $types .= "s";
}

$where_sql = implode(" AND ", $where_clauses);

// Query ensuring ONLY the scholar's LATEST OVERALL active snapshot is evaluated
$scholars_sql = "SELECT 
                    si.id AS scholar_id,
                    si.student_id,
                    si.last_name,
                    si.given_name,
                    si.middle_name,
                    si.year_level,
                    si.academic_status,
                    si.school_year,
                    si.semester,
                    sp.id AS scholarship_program_id,
                    sp.name AS scholarship_program_name,
                    sp.min_gwa_req,
                    p.name AS program_name,
                    COUNT(DISTINCT CASE WHEN ap.semester IN ('1st Semester', '2nd Semester', '1st', '2nd') THEN ap.semester END) AS required_sems_count,
                    MAX(ap.gwa) AS worst_gwa,
                    next_si.id AS already_promoted_id
                 FROM scholar_info si
                 INNER JOIN scholarship_program sp ON si.scholarship_program_id = sp.id
                 LEFT JOIN program p ON si.program_id = p.id
                 LEFT JOIN scholar_info overall_newer ON overall_newer.student_id = si.student_id 
                     AND overall_newer.is_archived = 0 
                     AND (
                         REPLACE(overall_newer.school_year, ' ', '') > REPLACE(si.school_year, ' ', '')
                         OR (
                             REPLACE(overall_newer.school_year, ' ', '') = REPLACE(si.school_year, ' ', '') 
                             AND overall_newer.id > si.id
                         )
                     )
                 INNER JOIN scholar_info all_si ON all_si.student_id = si.student_id 
                     AND all_si.school_year = si.school_year 
                     AND all_si.is_archived = 0
                 LEFT JOIN academic_performance ap ON ap.scholar_id = all_si.id 
                     AND ap.school_year = si.school_year
                 LEFT JOIN scholar_info next_si ON next_si.student_id = si.student_id 
                     AND next_si.school_year = ? 
                     AND next_si.is_archived = 0
                 WHERE $where_sql AND overall_newer.id IS NULL
                 GROUP BY si.id
                 ORDER BY sp.name ASC, si.last_name ASC";

array_unshift($params, $next_school_year);
$types = "s" . $types;

$stmt = $conn->prepare($scholars_sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$scholars_result = $stmt->get_result();

$scholars_list = [];
$total_eligible = 0;
$total_ineligible = 0;

while ($row = $scholars_result->fetch_assoc()) {
    $min_gwa = (float) $row['min_gwa_req'];
    $sems_count = (int) $row['required_sems_count'];
    $worst_gwa = (float) $row['worst_gwa'];
    $curr_yl_raw = trim((string)$row['year_level']);
    $is_already_promoted = !empty($row['already_promoted_id']);
    
    $is_eligible = false;
    $status_reason = "";
    $badge_class = "";

    if ($is_already_promoted) {
        $is_eligible = false;
        $status_reason = "Already Promoted to $next_school_year";
        $badge_class = "badge-secondary";
    } elseif ($min_gwa <= 0) {
        $is_eligible = true;
        $status_reason = "Eligible (No Grade Required)";
        $badge_class = "badge-success";
    } else {
        if ($sems_count < 2) {
            $is_eligible = false;
            $status_reason = "Incomplete Grades (Missing 1st/2nd Sem)";
            $badge_class = "badge-danger";
        } elseif ($worst_gwa > $min_gwa) {
            $is_eligible = false;
            $status_reason = "Unmet GWA (" . number_format($worst_gwa, 2) . " > " . number_format($min_gwa, 2) . ")";
            $badge_class = "badge-warning";
        } else {
            $is_eligible = true;
            $status_reason = "Eligible (Requirements Met)";
            $badge_class = "badge-success";
        }
    }

    if ($is_eligible) {
        $total_eligible++;
    } else {
        $total_ineligible++;
    }

    $row['is_eligible'] = $is_eligible;
    $row['is_already_promoted'] = $is_already_promoted;
    $row['status_reason'] = $status_reason;
    $row['badge_class'] = $badge_class;
    
    if (in_array($curr_yl_raw, ['1st Year', '2nd Year', '3rd Year', '4th Year', 'Irregular'])) {
        $row['display_year_level'] = $curr_yl_raw;
    } else {
        $row['display_year_level'] = $curr_yl_raw . match ((int)$curr_yl_raw) {
            1 => "st Year",
            2 => "nd Year",
            3 => "rd Year",
            default => "th Year"
        };
    }

    $is_4th_year = ($curr_yl_raw === '4th Year' || $curr_yl_raw === '4' || (is_numeric($curr_yl_raw) && (int)$curr_yl_raw >= 4));
    $row['is_4th_year'] = $is_4th_year;

    if ($curr_yl_raw === '1st Year' || $curr_yl_raw === '1') {
        $row['target_standing'] = "2nd Year ($next_school_year)";
    } elseif ($curr_yl_raw === '2nd Year' || $curr_yl_raw === '2') {
        $row['target_standing'] = "3rd Year ($next_school_year)";
    } elseif ($curr_yl_raw === '3rd Year' || $curr_yl_raw === '3') {
        $row['target_standing'] = "4th Year ($next_school_year)";
    } elseif ($curr_yl_raw === 'Irregular') {
        $row['target_standing'] = "Irregular ($next_school_year)";
    } else {
        $row['target_standing'] = "$curr_yl_raw ($next_school_year)";
    }

    $scholars_list[] = $row;
}

usort($scholars_list, function($a, $b) {
    if ($a['is_eligible'] !== $b['is_eligible']) {
        return $b['is_eligible'] <=> $a['is_eligible'];
    }
    $sp_cmp = strcmp($a['scholarship_program_name'], $b['scholarship_program_name']);
    if ($sp_cmp !== 0) return $sp_cmp;
    return strcmp($a['last_name'], $b['last_name']);
});
?>

<!DOCTYPE html>
<html>
<head>
    <title>Promote Scholars - PIT ScholarshipHub</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/promote_scholars.css?v=<?= filemtime('../assets/css/promote_scholars.css') ?>">
    <link rel="stylesheet" href="../assets/css/modals.css?v=<?= filemtime('../assets/css/modals.css') ?>">
    <link rel="icon" href="../assets/images/PIT%20LOGO.png">
    <script src="../assets/js/disable_button.js"></script>
</head>
<body>
    <?php include __DIR__ . '/../includes/modal_alerts.php'; ?>

    <div class="container">
        <?php include __DIR__ . "/../includes/navigation.php"; ?>

        <div class="page-content">
            <div class="page-header">
                <h2>Batch Year-Level Promotion</h2>
            </div>

            <div class="promotion-tabs">
                <a href="promote_scholars.php" class="tab-link active">Year-Level Promotion</a>
                <a href="term_advancement.php" class="tab-link">Term / Semester Advancement</a>
            </div>

            <div class="filter-card">
                <form method="GET" action="<?= $_SERVER['PHP_SELF'] ?>" class="filter-form">
                    <div class="form-group search-group">
                        <label for="searchQuery">Search Scholar</label>
                        <input type="text" id="searchQuery" placeholder="Search by Scholar Name or Student ID..." onkeyup="filterScholarsTable()" autocomplete="off">
                    </div>

                    <div class="form-group">
                        <label for="school_year">Current School Year</label>
                        <select name="school_year" id="school_year" onchange="this.form.submit()">
                            <?php foreach ($available_years as $yr) : ?>
                                <option value="<?= $yr ?>" <?= $school_year_filter == $yr ? 'selected' : '' ?>><?= $yr ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="program_id">Scholarship Program</label>
                        <select name="program_id" id="program_id" onchange="this.form.submit()">
                            <option value="0">All Scholarship Programs</option>
                            <?php if ($sp_result && $sp_result->num_rows > 0) : ?>
                                <?php while ($sp = $sp_result->fetch_assoc()) : ?>
                                    <option value="<?= $sp['id'] ?>" <?= $program_filter == $sp['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($sp['name']) ?><?= !empty($sp['code']) ? ' (' . htmlspecialchars($sp['code']) . ')' : '' ?>
                                    </option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="year_level">Current Year Level</label>
                        <select name="year_level" id="year_level" onchange="this.form.submit()">
                            <option value="0">All Year Levels</option>
                            <option value="1st Year" <?= $year_filter === '1st Year' ? 'selected' : '' ?>>1st Year</option>
                            <option value="2nd Year" <?= $year_filter === '2nd Year' ? 'selected' : '' ?>>2nd Year</option>
                            <option value="3rd Year" <?= $year_filter === '3rd Year' ? 'selected' : '' ?>>3rd Year</option>
                            <option value="4th Year" <?= $year_filter === '4th Year' ? 'selected' : '' ?>>4th Year</option>
                            <option value="Irregular" <?= $year_filter === 'Irregular' ? 'selected' : '' ?>>Irregular</option>
                        </select>
                    </div>
                </form>
            </div>

            <form action="../functions/admin/promote_scholars_process.php" method="POST" id="promotionForm" onsubmit="return confirmPromotion(event)">
                <div class="table-toolbar">
                    <div class="toolbar-left">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="selectAll()">Select All</button>
                        <button type="button" class="btn btn-primary btn-sm" onclick="selectEligibleOnly()">Select Eligible Only</button>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="deselectAll()">Deselect All</button>
                    </div>
                    <div class="toolbar-right">
                        <span class="stat-badge stat-eligible">Eligible: <?= $total_eligible ?></span>
                        <span class="stat-badge stat-ineligible">Ineligible: <?= $total_ineligible ?></span>
                        
                        <?php if ($can_promote): ?>
                            <button type="submit" name="promote" class="btn btn-success" disabled>Promote Scholar/s</button>
                        <?php else: ?>
                            <button type="button" class="btn btn-success" disabled title="You do not have permission to promote year levels.">Promote Scholar/s</button>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="table-wrapper">
                    <table class="promotion-table">
                        <thead>
                            <tr>
                                <th style="width: 40px; text-align: center;">
                                    <input type="checkbox" id="masterCheckbox" onclick="toggleMasterCheckbox(this)">
                                </th>
                                <th>Student ID & Name</th>
                                <th>Program / Course</th>
                                <th>Scholarship Program</th>
                                <th>Current Year</th>
                                <th>Target Standing</th>
                                <th>GWA Eligibility Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($scholars_list)) : ?>
                                <?php foreach ($scholars_list as $scholar) : 
                                    $scholar_id = $scholar['scholar_id'];
                                    $full_name = htmlspecialchars($scholar['last_name'] . ", " . $scholar['given_name'] . " " . $scholar['middle_name']);
                                    $student_id = htmlspecialchars($scholar['student_id']);
                                    $search_text = strtolower($full_name . " " . $student_id);
                                    $is_eligible = $scholar['is_eligible'];
                                    $curr_year_str = htmlspecialchars($scholar['display_year_level']);
                                ?>
                                    <tr class="scholar-row <?= !$is_eligible ? 'row-ineligible' : '' ?>" data-search="<?= $search_text ?>">
                                        <td style="text-align: center;">
                                            <input type="checkbox" 
                                                   name="promote_scholars[]" 
                                                   value="<?= $scholar_id ?>" 
                                                   class="scholar-checkbox"
                                                   data-eligible="<?= $is_eligible ? '1' : '0' ?>"
                                                   data-name="<?= $full_name ?>"
                                                   data-reason="<?= htmlspecialchars($scholar['status_reason']) ?>"
                                                   <?= (!$is_eligible || !$can_promote) ? 'disabled' : '' ?>
                                                   onchange="updateMasterCheckboxState()">
                                        </td>
                                        <td>
                                            <strong><?= $full_name ?></strong><br>
                                            <small class="text-muted"><?= $student_id ?></small>
                                        </td>
                                        <td><?= htmlspecialchars($scholar['program_name'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($scholar['scholarship_program_name']) ?></td>
                                        <td><span class="year-badge"><?= $curr_year_str ?></span></td>
                                        <td>
                                            <?php if ($scholar['is_4th_year']) : ?>
                                                <select name="target_standing_choice[<?= $scholar_id ?>]" 
                                                        style="padding: 5px 8px; border-radius: 6px; border: 1px solid #2a6b37; font-weight: 600; color: #1e4628; background-color: #f4fbf5; cursor: pointer;">
                                                    <option value="Graduated">Graduated (SY <?= $next_school_year ?>)</option>
                                                    <option value="Irregular">Irregular (SY <?= $next_school_year ?>)</option>
                                                </select>
                                            <?php else : ?>
                                                <strong><?= $scholar['target_standing'] ?></strong>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="status-badge <?= $scholar['badge_class'] ?>">
                                                <?= $scholar['status_reason'] ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <tr id="noDataRow">
                                    <td colspan="7" class="no-data">No active scholars found matching the selected filters.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </form>
        </div>

        <div class="footer">
            © 2026 PIT ScholarshipHub. All rights reserved.
        </div>
    </div>

    <script>
    function filterScholarsTable() {
        const query = document.getElementById('searchQuery').value.toLowerCase().trim();
        const rows = document.querySelectorAll('.promotion-table tbody tr.scholar-row');

        rows.forEach(row => {
            const searchText = row.getAttribute('data-search') || '';
            if (searchText.includes(query)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });

        updateMasterCheckboxState();
    }

    function toggleMasterCheckbox(master) {
        const visibleCheckboxes = document.querySelectorAll('.promotion-table tbody tr.scholar-row:not([style*="display: none"]) .scholar-checkbox:not(:disabled)');
        visibleCheckboxes.forEach(cb => cb.checked = master.checked);
        updateMasterCheckboxState();
    }

    function selectAll() {
        const visibleCheckboxes = document.querySelectorAll('.promotion-table tbody tr.scholar-row:not([style*="display: none"]) .scholar-checkbox:not(:disabled)');
        visibleCheckboxes.forEach(cb => cb.checked = true);
        updateMasterCheckboxState();
    }

    function selectEligibleOnly() {
        const visibleCheckboxes = document.querySelectorAll('.promotion-table tbody tr.scholar-row:not([style*="display: none"]) .scholar-checkbox:not(:disabled)');
        visibleCheckboxes.forEach(cb => {
            cb.checked = (cb.getAttribute('data-eligible') === '1');
        });
        updateMasterCheckboxState();
    }

    function deselectAll() {
        const visibleCheckboxes = document.querySelectorAll('.promotion-table tbody tr.scholar-row:not([style*="display: none"]) .scholar-checkbox');
        visibleCheckboxes.forEach(cb => cb.checked = false);
        document.getElementById('masterCheckbox').checked = false;
        updateMasterCheckboxState();
    }

    function updateMasterCheckboxState() {
        const visibleCheckboxes = Array.from(document.querySelectorAll('.promotion-table tbody tr.scholar-row:not([style*="display: none"]) .scholar-checkbox:not(:disabled)'));
        const checkedCheckboxes = visibleCheckboxes.filter(cb => cb.checked);

        const masterCb = document.getElementById('masterCheckbox');
        if (visibleCheckboxes.length === 0) {
            masterCb.checked = false;
        } else {
            masterCb.checked = (checkedCheckboxes.length === visibleCheckboxes.length && visibleCheckboxes.length > 0);
        }

        const promoteBtn = document.querySelector('button[name="promote"]');
        if (promoteBtn) {
            const totalChecked = document.querySelectorAll('.scholar-checkbox:checked:not(:disabled)').length;
            promoteBtn.disabled = (totalChecked === 0);
        }
    }

    function confirmPromotion(event) {
        const selected = Array.from(document.querySelectorAll('.scholar-checkbox:checked:not(:disabled)'));
        if (selected.length === 0) {
            showToast("Please select at least one scholar to promote.", "warning", 4000, "Selection Required");
            event.preventDefault();
            return false;
        }

        let msg = "Are you sure you want to promote " + selected.length + " scholar(s) to SY <?= $next_school_year ?>?";
        return confirmAction(event, msg, 'Confirm Promotion', 'primary');
    }

    updateMasterCheckboxState();
    </script>
</body>
</html>