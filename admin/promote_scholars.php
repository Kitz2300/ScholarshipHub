<?php 
include __DIR__ . "/../includes/conn.php";
include __DIR__ . "/../includes/check_permission.php";
session_start();

include __DIR__ . "/../includes/school_year_helper.php";

// Helper function to calculate next school year (e.g. "2025 - 2026" -> "2026 - 2027")
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

// Fetch filter selections
$program_filter = (int) ($_GET['program_id'] ?? 0);
$year_filter = (int) ($_GET['year_level'] ?? 0);
$school_year_filter = $_GET['school_year'] ?? getSelectedSchoolYear();

$next_school_year = getNextSchoolYear($school_year_filter);
$available_years = getAvailableSchoolYears($conn);

// Fetch active scholarship programs for dropdown
$sp_sql = "SELECT id, name FROM scholarship_program WHERE is_archived = 0 ORDER BY name ASC";
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
    $where_clauses[] = "si.scholarship_program_id = ?";
    $params[] = $program_filter;
    $types .= "i";
}

if ($year_filter > 0) {
    $where_clauses[] = "si.year_level = ?";
    $params[] = $year_filter;
    $types .= "i";
}

$where_sql = implode(" AND ", $where_clauses);

// Joined query checking current term evaluation & whether scholar was already promoted to target year
$scholars_sql = "SELECT 
                    si.id AS scholar_id,
                    si.student_id,
                    si.last_name,
                    si.given_name,
                    si.middle_name,
                    si.year_level,
                    si.academic_status,
                    si.school_year,
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
                 LEFT JOIN academic_performance ap ON si.id = ap.scholar_id AND ap.school_year = si.school_year
                 LEFT JOIN scholar_info next_si ON next_si.student_id = si.student_id AND next_si.school_year = ?
                 WHERE $where_sql
                 GROUP BY si.id
                 ORDER BY sp.name ASC, si.year_level ASC, si.last_name ASC";

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
    $curr_year = (int) $row['year_level'];
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
    
    if ($curr_year >= 4) {
        $row['target_standing'] = "4th Year → Pending Graduation ($next_school_year)";
    } else {
        $row['target_standing'] = ($curr_year + 1) . match ($curr_year + 1) {
            2 => "nd Year",
            3 => "rd Year",
            default => "th Year"
        } . " ($next_school_year)";
    }

    $scholars_list[] = $row;
}
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
                <a href="promote_scholars.php" class="tab-link active">
                    Year-Level Promotion
                </a>
                <a href="term_advancement.php" class="tab-link">
                    Term / Semester Advancement
                </a>
            </div>

            <div class="filter-card">
                <form method="GET" action="<?= $_SERVER['PHP_SELF'] ?>" class="filter-form">
                    <div class="form-group search-group">
                        <label for="searchQuery">Search Scholar</label>
                        <input type="text" 
                               id="searchQuery" 
                               placeholder="Search by Scholar Name or Student ID..." 
                               onkeyup="filterScholarsTable()" 
                               autocomplete="off">
                    </div>

                    <div class="form-group">
                        <label for="school_year">Current School Year</label>
                        <select name="school_year" id="school_year" onchange="this.form.submit()">
                            <?php foreach ($available_years as $yr) : ?>
                                <option value="<?= $yr ?>" <?= $school_year_filter == $yr ? 'selected' : '' ?>>
                                    <?= $yr ?>
                                </option>
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
                                        <?= htmlspecialchars($sp['name']) ?>
                                    </option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="year_level">Current Year Level</label>
                        <select name="year_level" id="year_level" onchange="this.form.submit()">
                            <option value="0">All Year Levels</option>
                            <option value="1" <?= $year_filter == 1 ? 'selected' : '' ?>>1st Year</option>
                            <option value="2" <?= $year_filter == 2 ? 'selected' : '' ?>>2nd Year</option>
                            <option value="3" <?= $year_filter == 3 ? 'selected' : '' ?>>3rd Year</option>
                            <option value="4" <?= $year_filter == 4 ? 'selected' : '' ?>>4th Year</option>
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
                        <button type="submit" name="promote" class="btn btn-success" <?= $total_eligible === 0 ? 'disabled' : '' ?>>Execute Promotion</button>
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
                                    $curr_year_str = $scholar['year_level'] . match ((int)$scholar['year_level']) {
                                        1 => "st Year",
                                        2 => "nd Year",
                                        3 => "rd Year",
                                        default => "th Year"
                                    };
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
                                                   <?= $is_eligible ? 'checked' : '' ?>
                                                   <?= !$is_eligible ? 'disabled' : '' ?>
                                                   onchange="updateMasterCheckboxState()">
                                        </td>
                                        <td>
                                            <strong><?= $full_name ?></strong><br>
                                            <small class="text-muted"><?= $student_id ?></small>
                                        </td>
                                        <td><?= htmlspecialchars($scholar['program_name'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($scholar['scholarship_program_name']) ?></td>
                                        <td><span class="year-badge"><?= $curr_year_str ?></span></td>
                                        <td><strong><?= $scholar['target_standing'] ?></strong></td>
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
    }

    function updateMasterCheckboxState() {
        const visibleCheckboxes = Array.from(document.querySelectorAll('.promotion-table tbody tr.scholar-row:not([style*="display: none"]) .scholar-checkbox:not(:disabled)'));
        if (visibleCheckboxes.length === 0) {
            document.getElementById('masterCheckbox').checked = false;
            return;
        }
        const allChecked = visibleCheckboxes.every(cb => cb.checked);
        document.getElementById('masterCheckbox').checked = allChecked;
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