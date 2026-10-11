<?php 
include __DIR__ . "/../includes/conn.php";
include __DIR__ . "/../includes/check_permission.php";
session_start();

include __DIR__ . "/../includes/school_year_helper.php";

// Ensure user is logged in
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'staff')) {
    $_SESSION['fail'] = "You are not logged in.";
    header("Location: login.php");
    exit();
}

if ($_SESSION['role'] !== 'admin') {
    checkPageAccess($conn, $_SESSION['user_id'], 'promote_scholars');
}

// Fetch filter selections and target semester logic
$program_filter = (int) ($_GET['program_id'] ?? 0);
$school_year_filter = $_GET['school_year'] ?? getSelectedSchoolYear();

$target_semester_get = $_GET['target_semester'] ?? '';
$semester_get = $_GET['semester'] ?? '';

if (!empty($target_semester_get)) {
    $target_semester = $target_semester_get;
    if ($target_semester === 'Summer') {
        $semester_filter = '2nd Semester';
    } elseif ($target_semester === '2nd Semester') {
        $semester_filter = '1st Semester';
    } elseif ($target_semester === '1st Semester') {
        $semester_filter = 'Summer';
    } else {
        $semester_filter = $semester_get ?: getSelectedSemester();
    }
} else {
    $semester_filter = $semester_get ?: getSelectedSemester();
    if ($semester_filter === '1st Semester') {
        $target_semester = '2nd Semester';
    } elseif ($semester_filter === '2nd Semester') {
        $target_semester = 'Summer';
    } else {
        $target_semester = '1st Semester';
    }
}

// Fetch active scholarship programs for dropdown (Deduplicated by name & code)
$sp_sql = "SELECT MIN(id) AS id, name, code 
           FROM scholarship_program 
           WHERE is_archived = 0 
           GROUP BY name, code 
           ORDER BY name ASC";
$sp_result = $conn->query($sp_sql);

// Fetch available school years
$available_years = getAvailableSchoolYears($conn);
if (empty($available_years)) {
    $available_years = [date('Y') . '-' . (date('Y') + 1)];
}
$available_semesters = ['1st Semester', '2nd Semester', 'Summer'];

// Build query for current scholars in target filter term
$where_clauses = ["si.is_archived = 0", "sp.is_archived = 0", "si.academic_status IN ('Enrolled', 'Below GWA Requirement')"];
$params = [];
$types = "";

if ($program_filter > 0) {
    $where_clauses[] = "sp.name = (SELECT name FROM scholarship_program WHERE id = ?)";
    $params[] = $program_filter;
    $types .= "i";
}

if (!empty($school_year_filter)) {
    $where_clauses[] = "si.school_year = ?";
    $params[] = $school_year_filter;
    $types .= "s";
}

if (!empty($semester_filter)) {
    $where_clauses[] = "si.semester = ?";
    $params[] = $semester_filter;
    $types .= "s";
}

$where_sql = implode(" AND ", $where_clauses);

// Joined academic_performance & check that ONLY the scholar's LATEST OVERALL active record is selected
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
                    sp.name AS scholarship_program_name,
                    sp.min_gwa_req,
                    p.name AS program_name,
                    ap.gwa AS current_gwa,
                    later_si.id AS already_advanced_id
                 FROM scholar_info si
                 INNER JOIN scholarship_program sp ON si.scholarship_program_id = sp.id
                 LEFT JOIN program p ON si.program_id = p.id
                 LEFT JOIN scholar_info overall_newer ON overall_newer.student_id = si.student_id 
                     AND overall_newer.is_archived = 0 
                     AND (
                         REPLACE(overall_newer.school_year, ' ', '') > REPLACE(si.school_year, ' ', '')
                         OR (
                             REPLACE(overall_newer.school_year, ' ', '') = REPLACE(si.school_year, ' ', '') 
                             AND (
                                 (si.semester = '1st Semester' AND overall_newer.semester IN ('2nd Semester', 'Summer'))
                                 OR (si.semester = '2nd Semester' AND overall_newer.semester = 'Summer')
                                 OR (overall_newer.id > si.id)
                             )
                         )
                     )
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
                 WHERE $where_sql AND overall_newer.id IS NULL
                 ORDER BY sp.name ASC, si.last_name ASC";

$stmt = $conn->prepare($scholars_sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$scholars_result = $stmt->get_result();

$scholars_list = [];
$eligible_count = 0;
$ineligible_count = 0;
$already_advanced_count = 0;

while ($row = $scholars_result->fetch_assoc()) {
    $requires_gwa = (!empty($row['min_gwa_req']) && floatval($row['min_gwa_req']) > 0);
    $has_gwa = ($row['current_gwa'] !== null && $row['current_gwa'] !== '');
    $already_advanced = !empty($row['already_advanced_id']);

    if ($already_advanced) {
        $row['is_eligible'] = false;
        $row['eligibility_reason'] = 'Already Advanced to Next Semester / Term';
        $already_advanced_count++;
        $ineligible_count++;
    } elseif ($requires_gwa && !$has_gwa) {
        $row['is_eligible'] = false;
        $row['eligibility_reason'] = 'Missing GWA for current semester';
        $ineligible_count++;
    } else {
        $row['is_eligible'] = true;
        $row['eligibility_reason'] = 'Eligible';
        $eligible_count++;
    }

    $scholars_list[] = $row;
}
$stmt->close();

// Sort scholars: Eligible at top first, then secondary sort by Scholarship Program and Last Name
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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Term Advancement - PIT ScholarshipHub</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/navigation.css?v=<?= file_exists('../assets/css/navigation.css') ? filemtime('../assets/css/navigation.css') : time() ?>">
    <link rel="stylesheet" href="../assets/css/term_advancement.css?v=<?= file_exists('../assets/css/term_advancement.css') ? filemtime('../assets/css/term_advancement.css') : time() ?>">
    <link rel="stylesheet" href="../assets/css/modals.css?v=<?= file_exists('../assets/css/modals.css') ? filemtime('../assets/css/modals.css') : time() ?>">
    <link rel="icon" href="../assets/images/PIT%20LOGO.png">
    <script src="../assets/js/disable_button.js"></script>
</head>
<body>
    <?php include __DIR__ . '/../includes/modal_alerts.php'; ?>

    <div class="container">
        <?php include __DIR__ . "/../includes/navigation.php"; ?>

        <div class="page-content">
            <div class="page-header">
                <h2>Scholar Promotion & Term Management</h2>
            </div>

            <!-- Sub-Navigation Tabs -->
            <div class="promotion-tabs">
                <a href="promote_scholars.php" class="tab-link">
                    Year-Level Promotion
                </a>
                <a href="term_advancement.php" class="tab-link active">
                    Term / Semester Advancement
                </a>
            </div>

            <!-- Filter & Search Card -->
            <div class="filter-card">
                <form method="GET" action="<?= $_SERVER['PHP_SELF'] ?>" class="filter-form">
                    <input type="hidden" name="target_semester" id="hidden_target_semester" value="<?= htmlspecialchars($target_semester) ?>">

                    <div class="form-group search-group">
                        <label for="searchQuery">Search Scholar</label>
                        <input type="text" 
                               id="searchQuery" 
                               placeholder="Search by Scholar Name or Student ID..." 
                               onkeyup="filterScholarsTable()" 
                               autocomplete="off">
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
                        <label for="semester">Current Semester</label>
                        <select name="semester" id="semester" onchange="handleCurrentSemesterChange(this)">
                            <?php foreach ($available_semesters as $sem) : ?>
                                <option value="<?= $sem ?>" <?= $semester_filter == $sem ? 'selected' : '' ?>>
                                    <?= $sem ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>

            <!-- Term Advancement Form -->
            <form action="../functions/admin/term_advancement_process.php" method="POST" id="termForm" onsubmit="return confirmTermAdvancement(event)">

                <!-- Target Term Config Bar -->
                <div class="target-term-card">
                    <div class="target-term-title">
                        <strong>Select Target Term for Selected Scholars:</strong>
                    </div>
                    <div class="target-term-controls">
                        <div class="target-group">
                            <label for="target_school_year">Target School Year:</label>
                            <select name="target_school_year" id="target_school_year" required onchange="updateTargetPreview()">
                                <?php foreach ($available_years as $yr) : ?>
                                    <option value="<?= $yr ?>" <?= $school_year_filter == $yr ? 'selected' : '' ?>>
                                        <?= $yr ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="target-group">
                            <label for="target_semester">Target Semester:</label>
                            <select name="target_semester" id="target_semester" required onchange="handleTargetSemesterChange(this)">
                                <option value="1st Semester" <?= $target_semester == '1st Semester' ? 'selected' : '' ?>>1st Semester</option>
                                <option value="2nd Semester" <?= $target_semester == '2nd Semester' ? 'selected' : '' ?>>2nd Semester</option>
                                <option value="Summer" <?= $target_semester == 'Summer' ? 'selected' : '' ?>>Summer</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="table-toolbar">
                    <div class="toolbar-left">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="selectAll()">Select All</button>
                        <button type="button" class="btn btn-primary btn-sm" onclick="selectEligibleOnly()">Select All Eligible</button>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="deselectAll()">Deselect All</button>
                    </div>
                    <div class="toolbar-right">
                        <span class="stat-badge stat-eligible">Eligible: <?= $eligible_count ?></span>
                        <?php if ($already_advanced_count > 0) : ?>
                            <span class="stat-badge stat-ineligible">Already Advanced: <?= $already_advanced_count ?></span>
                        <?php endif; ?>
                        <?php if (($ineligible_count - $already_advanced_count) > 0) : ?>
                            <span class="stat-badge stat-ineligible">Missing GWA: <?= ($ineligible_count - $already_advanced_count) ?></span>
                        <?php endif; ?>
                        <button type="submit" name="advance_term" class="btn btn-success" disabled>Advance Term</button>
                    </div>
                </div>

                <!-- Fixed-Height & Scrollable Table Wrapper -->
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
                                <th>Current Term & GWA</th>
                                <th>Status</th>
                                <th>Target Term</th>
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
                                    $already_advanced = !empty($scholar['already_advanced_id']);
                                    $curr_gwa = ($scholar['current_gwa'] !== null && $scholar['current_gwa'] !== '') 
                                                ? number_format((float)$scholar['current_gwa'], 2) 
                                                : 'None';
                                    
                                    $curr_yl_raw = trim((string)$scholar['year_level']);
                                    if (in_array($curr_yl_raw, ['1st Year', '2nd Year', '3rd Year', '4th Year', 'Irregular'])) {
                                        $curr_year_str = htmlspecialchars($curr_yl_raw);
                                    } else {
                                        $curr_year_str = htmlspecialchars($curr_yl_raw . match ((int)$curr_yl_raw) {
                                            1 => "st Year",
                                            2 => "nd Year",
                                            3 => "rd Year",
                                            default => "th Year"
                                        });
                                    }
                                ?>
                                    <tr class="scholar-row <?= !$is_eligible ? 'row-ineligible' : '' ?>" data-search="<?= $search_text ?>">
                                        <td style="text-align: center;">
                                            <input type="checkbox" 
                                                   name="advance_scholars[]" 
                                                   value="<?= $scholar_id ?>" 
                                                   class="scholar-checkbox"
                                                   data-eligible="<?= $is_eligible ? '1' : '0' ?>"
                                                   <?= !$is_eligible ? 'disabled' : '' ?>
                                                   onchange="updateMasterCheckboxState()">
                                        </td>
                                        <td>
                                            <strong><?= $full_name ?></strong><br>
                                            <small class="text-muted"><?= $student_id ?></small>
                                        </td>
                                        <td><?= htmlspecialchars($scholar['program_name'] ?? 'N/A') ?></td>
                                        <td>
                                            <?= htmlspecialchars($scholar['scholarship_program_name']) ?><br>
                                            <small class="text-muted">
                                                Req. GWA: <?= !empty($scholar['min_gwa_req']) && floatval($scholar['min_gwa_req']) > 0 ? number_format((float)$scholar['min_gwa_req'], 2) : 'None' ?>
                                            </small>
                                        </td>
                                        <td><span class="year-badge"><?= $curr_year_str ?></span></td>
                                        <td>
                                            <span class="term-badge term-current">
                                                <?= htmlspecialchars($scholar['school_year']) ?> (<?= htmlspecialchars($scholar['semester']) ?>)
                                            </span><br>
                                            <small class="text-muted"><strong>GWA:</strong> <?= $curr_gwa ?></small>
                                        </td>
                                        <td>
                                            <?php if ($is_eligible) : ?>
                                                <span class="status-badge badge-success">Eligible</span>
                                            <?php elseif ($already_advanced) : ?>
                                                <span class="status-badge badge-secondary" title="Scholar has already advanced to a later semester or term">Already Advanced</span>
                                            <?php else : ?>
                                                <span class="status-badge badge-danger" title="<?= htmlspecialchars($scholar['eligibility_reason']) ?>">Missing GWA</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="term-badge term-target target-preview-label">
                                                --
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <tr>
                                    <td colspan="8" class="no-data">No enrolled scholars found matching the selected term filters.</td>
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
    function handleTargetSemesterChange(selectElem) {
        const targetVal = selectElem.value;
        document.getElementById('hidden_target_semester').value = targetVal;

        const semSelect = document.getElementById('semester');
        if (targetVal === 'Summer') {
            semSelect.value = '2nd Semester';
        } else if (targetVal === '2nd Semester') {
            semSelect.value = '1st Semester';
        } else if (targetVal === '1st Semester') {
            semSelect.value = 'Summer';
        }

        document.querySelector('.filter-form').submit();
    }

    function handleCurrentSemesterChange(selectElem) {
        const currVal = selectElem.value;
        const hiddenTarget = document.getElementById('hidden_target_semester');

        if (currVal === '2nd Semester') {
            hiddenTarget.value = 'Summer';
        } else if (currVal === '1st Semester') {
            hiddenTarget.value = '2nd Semester';
        } else if (currVal === 'Summer') {
            hiddenTarget.value = '1st Semester';
        }

        selectElem.form.submit();
    }

    function updateTargetPreview() {
        const targetSY = document.getElementById('target_school_year').value;
        const targetSem = document.getElementById('target_semester').value;
        const labels = document.querySelectorAll('.target-preview-label');
        labels.forEach(lbl => {
            lbl.textContent = targetSY + " (" + targetSem + ")";
        });
    }

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

        const advanceBtn = document.querySelector('button[name="advance_term"]');
        if (advanceBtn) {
            const totalChecked = document.querySelectorAll('.scholar-checkbox:checked:not(:disabled)').length;
            advanceBtn.disabled = (totalChecked === 0);
        }
    }

    function confirmTermAdvancement(event) {
        const selected = Array.from(document.querySelectorAll('.scholar-checkbox:checked:not(:disabled)'));
        if (selected.length === 0) {
            alert("Please select at least one eligible scholar to advance.");
            event.preventDefault();
            return false;
        }

        const targetSY = document.getElementById('target_school_year').value;
        const targetSem = document.getElementById('target_semester').value;

        const msg = "Are you sure you want to advance " + selected.length + " scholar(s) to:\n\nSY " + targetSY + " - " + targetSem + "?";
        return confirmAction(event, msg, 'Confirm Term Advancement', 'primary');
    }

    // Initialize state
    updateTargetPreview();
    updateMasterCheckboxState();
    </script>
</body>
</html>