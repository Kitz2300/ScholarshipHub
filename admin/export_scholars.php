<?php 
include __DIR__ . "/../includes/conn.php";
include __DIR__ . "/../includes/check_permission.php";
include __DIR__ . "/../includes/school_year_helper.php";
session_start();

if(!hasPermission($conn, $_SESSION['user_id'], 'import_export', 'export')) {
    header("Location: dashboard.php");
    exit();
}

$available_years = getScholarshipSchoolYears($conn);
if (empty($available_years)) {
    $available_years = getAvailableSchoolYears($conn);
}
$selected_sy = getSelectedSchoolYear();

$available_semesters = ['1st Semester', '2nd Semester', 'Summer'];
$selected_sem = getSelectedSemester();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Export Scholar Records - PIT ScholarshipHub</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/export_scholars.css?v=<?= filemtime('../assets/css/export_scholars.css') ?>">
    <link rel="icon" href="../assets/images/PIT%20LOGO.png">
    <link rel="stylesheet" href="../assets/css/modals.css">
    <!-- SortableJS for drag & drop -->
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.14.0/Sortable.min.js"></script>
</head>
<body>
    <!-- alert messages -->
    <?php include __DIR__ . "/../includes/modal_alerts.php"; ?>
    <?php if(!isset($_SESSION['user_id']) || ($_SESSION['role'] !== "admin" && $_SESSION['role'] !== "staff")) : 
        $_SESSION['fail'] = "You are not logged in!";
        header("Location: ../portal.php");
        exit();
    else: ?>
        <div class="container">

            <?php include __DIR__ . "/../includes/navigation.php"; ?>

            <!-- Page Content -->
            <div class="page-content">
                <div class="page-header">
                    <h2>Export Scholar Records</h2>
                </div>

                <!-- Export Form -->
                <form id="exportForm" action="../functions/admin/export_scholars_process.php" method="POST">
                    <!-- Step 1: Filters -->
                    <div class="form-section">
                        <h3>Step 1: Select Filters</h3>
                        <div class="form-row">
                            <!-- School Year Filter -->
                            <div class="form-group">
                                <label for="school_year">School Year <span class="required-asterisk">*</span></label>
                                <select name="school_year" id="school_year" required onchange="filterProgramsBySYAndSemester()">
                                    <?php foreach ($available_years as $yr) : ?>
                                        <option value="<?= htmlspecialchars($yr) ?>" <?= $yr === $selected_sy ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($yr) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Semester Filter -->
                            <div class="form-group">
                                <label for="semester">Semester <span class="required-asterisk">*</span></label>
                                <select name="semester" id="semester" required onchange="filterProgramsBySYAndSemester()">
                                    <?php foreach ($available_semesters as $sem) : ?>
                                        <option value="<?= htmlspecialchars($sem) ?>" <?= $sem === $selected_sem ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($sem) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Scholarship Program (Filtered by SY & Semester) -->
                            <div class="form-group">
                                <label for="scholar_program">Scholarship Program <span class="required-asterisk">*</span></label>
                                <select name="scholar_program" id="scholar_program" required>
                                    <option value="" disabled selected>Select Scholarship Program</option>
                                    <?php 
                                    $sp_sql = "SELECT sp.id, sp.name, sp.code, sp.school_year, sp.semester 
                                               FROM scholarship_program sp 
                                               WHERE sp.is_archived = 0 AND sp.is_active = 1 
                                               ORDER BY sp.name ASC";
                                    $sp_stmt = $conn->prepare($sp_sql);
                                    $sp_stmt->execute();
                                    $sp_result = $sp_stmt->get_result();
                                    while($row = $sp_result->fetch_assoc()) : 
                                    ?>
                                        <option value="<?= $row['id'] ?>" 
                                                data-sy="<?= htmlspecialchars($row['school_year']) ?>" 
                                                data-sem="<?= htmlspecialchars($row['semester'] ?? '') ?>">
                                            <?= htmlspecialchars($row['name']) ?> (<?= htmlspecialchars($row['code']) ?>)
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                                <small id="no_program_msg">
                                    No active scholarship programs found for the selected School Year and Semester.
                                </small>
                            </div>

                            <!-- College Selector -->
                            <div class="form-group">
                                <label for="college">College</label>
                                <select name="college" id="college" onchange="filterProgramsByCollege()">
                                    <option value="all" selected>All Colleges</option>
                                    <?php 
                                    $c_sql = "SELECT id, college_name FROM college ORDER BY college_name ASC";
                                    $c_stmt = $conn->prepare($c_sql);
                                    $c_stmt->execute();
                                    $c_result = $c_stmt->get_result();
                                    while($row = $c_result->fetch_assoc()) : ?>
                                        <option value="<?= $row['id'] ?>"><?= htmlspecialchars($row['college_name']) ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <!-- Academic Program Selector (Sectioned by College) -->
                            <div class="form-group">
                                <label for="program">Program / Course</label>
                                <select name="program" id="program">
                                    <option value="all">All Programs</option>
                                    <?php 
                                    $p_sql = "SELECT p.name AS program_name, p.college_id, COALESCE(c.college_name, 'Other Programs') AS college_name 
                                              FROM program p 
                                              LEFT JOIN college c ON p.college_id = c.id 
                                              ORDER BY c.college_name ASC, p.name ASC";
                                    $p_stmt = $conn->prepare($p_sql);
                                    $p_stmt->execute();
                                    $p_result = $p_stmt->get_result();

                                    $grouped_programs = [];
                                    while($row = $p_result->fetch_assoc()) {
                                        $c_id = $row['college_id'] ?? 0;
                                        $grouped_programs[$c_id]['college_name'] = $row['college_name'];
                                        $grouped_programs[$c_id]['programs'][] = $row['program_name'];
                                    }

                                    foreach ($grouped_programs as $c_id => $group) : ?>
                                        <optgroup label="<?= htmlspecialchars($group['college_name']) ?>" data-college="<?= $c_id ?>">
                                            <?php foreach ($group['programs'] as $prog_name) : ?>
                                                <option value="<?= htmlspecialchars($prog_name) ?>" data-college="<?= $c_id ?>">
                                                    <?= htmlspecialchars($prog_name) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="academic_status">Academic Status</label>
                                <select name="academic_status" id="academic_status">
                                    <option value="Enrolled" selected>Enrolled</option>
                                    <option value="Not Enrolled">Not Enrolled</option>
                                    <option value="Graduated">Graduated</option>
                                    <option value="Transferred">Transferred</option>
                                    <option value="Dropped">Dropped</option>
                                    <option value="Leave of Absence">Leave of Absence</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2: Column Selection -->
                    <div class="form-section">
                        <h3>Step 2: Select Columns to Include</h3>
                        <div class="checkbox-grid">
                            <!-- Required / Core -->
                            <?php
                            $all_columns = [
                                'student_id' => 'Student ID',
                                'philsys_id' => 'PHILSYS ID',
                                'lrn' => 'LRN',
                                'four_ps_id' => "4P's ID",
                                'last_name' => 'Last Name',
                                'given_name' => 'Given Name',
                                'ext_name' => 'Ext. Name',
                                'middle_name' => 'Middle Name',
                                'program_name' => 'Program Name',
                                'scholarship_program' => 'Scholarship Program',
                                'year_level' => 'Year Level',
                                'academic_status' => 'Academic Status',
                                'father_name' => "Father's Name",
                                'father_contact' => "Father's Contact Number",
                                'mother_name' => "Mother's Name",
                                'mother_contact' => "Mother's Contact Number",
                                'is_solo_parent' => 'Solo Parent',
                                'is_raised_by_solo_parent' => 'Raised by Solo Parent',
                                'is_gida' => 'GIDA Resident',
                                'gida_details' => 'GIDA Details',
                                'is_farmer_fisherfolk' => 'Farmer / Fisherfolk Family',
                                'is_rebel_returnee' => 'Rebel Returnee Family',
                                'permanent_address' => 'Permanent Address',
                                'disability' => 'Disability',
                                'contact' => 'Contact Number',
                                'birthdate' => 'Birthdate',
                                'sex' => 'Sex',
                                'email' => 'Email Address',
                                'ip_group' => 'IP Group',
                                'gwa' => 'GWA',
                                'loa_semester' => 'LOA Semester',
                                'loa_school_year' => 'LOA School Year',
                            ];

                            // Map extra fields from scholar_notes by Scholarship Program ID
                            $all_extra_keys_map = [];
                            $extra_sql = "SELECT si.scholarship_program_id, sn.notes 
                                          FROM scholar_notes sn 
                                          INNER JOIN scholar_info si ON sn.scholar_id = si.id 
                                          WHERE sn.notes IS NOT NULL AND sn.notes <> '' AND si.is_archived = 0";
                            $extra_stmt = $conn->prepare($extra_sql);
                            $extra_stmt->execute();
                            $extra_result = $extra_stmt->get_result();

                            while($row = $extra_result->fetch_assoc()) {
                                $sp_id = (string)$row['scholarship_program_id'];
                                $data = json_decode($row['notes'], true);
                                if(is_array($data)) {
                                    foreach(array_keys($data) as $key) {
                                        if(!isset($all_extra_keys_map[$key])) {
                                            $all_extra_keys_map[$key] = [];
                                        }
                                        if(!in_array($sp_id, $all_extra_keys_map[$key])) {
                                            $all_extra_keys_map[$key][] = $sp_id;
                                        }
                                    }
                                }
                            }

                            foreach($all_columns as $col => $label) : 
                                $is_loa = in_array($col, ['loa_semester', 'loa_school_year']);
                                $checked = $is_loa ? '' : 'checked';
                                $disabled = $is_loa ? 'disabled' : '';
                            ?>
                                <div class="checkbox-group" id="group_<?= $col ?>">
                                    <input type="checkbox" id="col_<?= $col ?>" name="columns[]" value="<?= $col ?>" <?= $checked ?> <?= $disabled ?> />
                                    <label for="col_<?= $col ?>"><?= $label ?></label>
                                </div>
                            <?php endforeach; ?>

                            <?php if(!empty($all_extra_keys_map)) : ?>
                                <div class="checkbox-group full-width" id="extra_fields_header">
                                    <strong>Extra Fields (from scholar notes):</strong>
                                </div>
                                <?php foreach($all_extra_keys_map as $key => $sp_ids) : ?>
                                    <div class="checkbox-group extra-field-item" data-sp-ids="<?= implode(',', $sp_ids) ?>">
                                        <input type="checkbox" id="extra_<?= htmlspecialchars($key) ?>" name="columns[]" value="extra_<?= htmlspecialchars($key) ?>" disabled />
                                        <label for="extra_<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($key) ?></label>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Step 3: Filename -->
                    <div class="form-section">
                        <h3>Step 3: Set Filename</h3>
                        <div class="form-row">
                            <div class="form-group full-width">
                                <label for="filename">Filename <span class="required">*</span></label>
                                <input type="text" name="filename" id="filename" value="scholars_export_<?= date('Y-m-d') ?>" required />
                                <small class="help-text">.xlsx will be added automatically.</small>
                            </div>
                        </div>
                    </div>

                    <!-- Preview & Rearrange button -->
                    <div class="form-actions" id="proceed-btn-wrapper">
                        <button type="button" id="proceed-btn" class="btn btn-success">Preview & Rearrange</button>
                    </div>

                    <!-- Preview & Export Area -->
                    <div class="form-section" id="preview-section">
                        <h3>Step 4: Preview & Rearrange Columns</h3>
                        
                        <!-- Selected Filters Summary Card -->
                        <div class="filter-summary-box">
                            <h4>Selected Filters:</h4>
                            <div id="filter-summary-content" class="filter-summary-grid"></div>
                        </div>

                        <p><strong>Drag column headers to reorder. The order in the preview will be used in the exported file.</strong></p>
                        <div class="table-wrapper">
                            <table id="preview-table">
                                <thead id="preview-headers"></thead>
                                <tbody id="preview-body"></tbody>
                            </table>
                        </div>
                        <div class="form-actions preview-actions">
                            <button type="button" id="export-btn" class="btn btn-success" onclick="return confirmAction(event, 'Are you sure you want to export and download the selected scholar records?', 'Export & Download', 'primary')">Export & Download Now</button>
                            <button type="button" id="refresh-preview" class="btn btn-secondary">Reset</button>
                            <a href="export_scholars.php" class="btn btn-cancel">Cancel</a>
                        </div>
                    </div>

                    <!-- Hidden input to store column order -->
                    <input type="hidden" name="column_order" id="column_order" value="">
                </form>
            </div>

            <!-- Preview Loading Modal -->
            <div id="exportPreviewModal" class="sys-modal">
                <div class="sys-modal-content export-modal-content">
                    <div class="sys-spinner"></div>
                    <h3 class="export-modal-title">Generating Preview...</h3>
                    <p class="export-modal-desc">Please wait while the system prepares your data preview.</p>
                </div>
            </div>

            <!-- Footer -->
            <div class="footer">
                © 2026 PIT ScholarshipHub. All rights reserved.
            </div>
        </div>

        <!-- Download Success / Refresh Modal -->
        <div id="exportDownloadModal" class="sys-modal">
            <div class="sys-modal-content export-modal-content">
                <h3 class="export-modal-title">File is Downloading</h3>
                <p class="export-modal-desc">Your scholar records export has started. Once your file download is complete, click the button below to refresh the page.</p>
                <div class="export-modal-actions">
                    <button type="button" id="refreshPageBtn" class="btn btn-success">Refresh Page</button>
                </div>
            </div>
        </div>

        <script>
        function filterProgramsBySYAndSemester() {
            const sySelect = document.getElementById('school_year');
            const semSelect = document.getElementById('semester');
            const programSelect = document.getElementById('scholar_program');
            const noProgMsg = document.getElementById('no_program_msg');
            
            if (!sySelect || !semSelect || !programSelect) return;

            const selectedSY = sySelect.value.replace(/\s+/g, '').toLowerCase();
            const selectedSem = semSelect.value.replace(/\s+/g, '').toLowerCase();

            let visibleCount = 0;

            Array.from(programSelect.options).forEach(opt => {
                if (opt.value === "") return;

                const progSY = (opt.getAttribute('data-sy') || '').replace(/\s+/g, '').toLowerCase();
                const progSem = (opt.getAttribute('data-sem') || '').replace(/\s+/g, '').toLowerCase();

                const syMatch = (progSY === selectedSY);
                const semMatch = (!progSem || progSem === selectedSem);

                if (syMatch && semMatch) {
                    opt.style.display = "";
                    opt.disabled = false;
                    visibleCount++;
                } else {
                    opt.style.display = "none";
                    opt.disabled = true;
                }
            });

            if (visibleCount === 0) {
                programSelect.value = "";
                if (noProgMsg) noProgMsg.style.display = "block";
            } else {
                if (noProgMsg) noProgMsg.style.display = "none";
                if (programSelect.selectedOptions.length === 0 || programSelect.selectedOptions[0].disabled) {
                    programSelect.value = "";
                }
            }
            updateExtraFields();
        }

        function filterProgramsByCollege() {
            const collegeSelect = document.getElementById('college');
            const academicProgramSelect = document.getElementById('program');

            if (!collegeSelect || !academicProgramSelect) return;

            const selectedCollege = collegeSelect.value;
            const optgroups = academicProgramSelect.querySelectorAll('optgroup');

            optgroups.forEach(og => {
                const ogCollegeId = og.getAttribute('data-college');
                const options = og.querySelectorAll('option');

                if (selectedCollege === "all" || ogCollegeId === selectedCollege) {
                    og.style.display = "";
                    options.forEach(opt => {
                        opt.style.display = "";
                        opt.disabled = false;
                    });
                } else {
                    og.style.display = "none";
                    options.forEach(opt => {
                        opt.style.display = "none";
                        opt.disabled = true;
                    });
                }
            });

            if (academicProgramSelect.selectedOptions.length === 0 || academicProgramSelect.selectedOptions[0].disabled) {
                academicProgramSelect.value = "all";
            }
        }

        function updateExtraFields() {
            const programSelect = document.getElementById('scholar_program');
            const selectedProgramId = programSelect ? programSelect.value : '';
            const extraHeader = document.getElementById('extra_fields_header');
            const extraItems = document.querySelectorAll('.extra-field-item');

            let visibleExtraCount = 0;

            extraItems.forEach(item => {
                const spIds = (item.getAttribute('data-sp-ids') || '').split(',');
                const checkbox = item.querySelector('input[type="checkbox"]');

                if (selectedProgramId && spIds.includes(selectedProgramId)) {
                    item.style.display = "";
                    if (checkbox) checkbox.disabled = false;
                    visibleExtraCount++;
                } else {
                    item.style.display = "none";
                    if (checkbox) {
                        checkbox.checked = false;
                        checkbox.disabled = true;
                    }
                }
            });

            if (extraHeader) {
                if (visibleExtraCount > 0) {
                    extraHeader.style.display = "";
                } else {
                    extraHeader.style.display = "none";
                }
            }
        }

        document.addEventListener("DOMContentLoaded", function() {
            filterProgramsBySYAndSemester();
            filterProgramsByCollege();
            updateExtraFields();

            const scholarProgramSelect = document.getElementById('scholar_program');
            if (scholarProgramSelect) {
                scholarProgramSelect.addEventListener('change', updateExtraFields);
            }
        });
        </script>

        <script src="../assets/js/export.js?v=<?= filemtime('../assets/js/export.js') ?>"></script>
        <script src="../assets/js/export_reorder.js?v=<?= filemtime('../assets/js/export_reorder.js') ?>"></script>
    <?php endif; ?>
</body>
</html>