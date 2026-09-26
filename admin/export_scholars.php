<?php 
include __DIR__ . "/../includes/conn.php";
include __DIR__ . "/../includes/check_permission.php";
session_start();

if(!hasPermission($conn, $_SESSION['user_id'], 'import_export', 'export')) {
    header("Location: dashboard.php");
    exit();
}

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
        $_SESSION['fail'] = "You are no logged in!";
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
                            <div class="form-group">
                                <label for="scholar_program">Scholarship Program</label>
                                <select name="scholar_program" id="scholar_program">
                                    <?php 
                                    $sp_sql = "SELECT id, name, code FROM scholarship_program
                                               WHERE EXISTS (SELECT 1 FROM scholar_info 
                                                             WHERE scholar_info.scholarship_program_id = scholarship_program.id 
                                                             AND scholar_info.is_archived = 0 
                                                             AND scholarship_program.is_archived = 0)
                                               ORDER BY name ASC";
                                    $sp_stmt = $conn->prepare($sp_sql);
                                    $sp_stmt->execute();
                                    $sp_result = $sp_stmt->get_result();
                                    while($row = $sp_result->fetch_assoc()) : 
                                    ?>
                                        <option value="<?= $row['id'] ?>"><?= htmlspecialchars($row['name']) ?> (<?= htmlspecialchars($row['code']) ?>)</option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="college">College</label>
                                <select name="college" id="college">
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
                            <div class="form-group">
                                <label for="program">Program / Course</label>
                                <select name="program" id="program">
                                    <option value="all">All Programs</option>
                                    <?php 
                                    $p_sql = "SELECT name FROM program ORDER BY name ASC";
                                    $p_stmt = $conn->prepare($p_sql);
                                    $p_stmt->execute();
                                    $p_result = $p_stmt->get_result();
                                    while($row = $p_result->fetch_assoc()) : ?>
                                        <option value="<?= htmlspecialchars($row['name']) ?>"><?= htmlspecialchars($row['name']) ?></option>
                                    <?php endwhile; ?>
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
                                'is_child_of_solo_parent' => 'Child of Solo Parent',
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
                            // Extra fields from scholar_notes
                            $extra_keys = [];
                            $extra_sql = "SELECT notes FROM scholar_notes WHERE notes IS NOT NULL AND notes <> ''";
                            $extra_stmt = $conn->prepare($extra_sql);
                            $extra_stmt->execute();
                            $extra_result = $extra_stmt->get_result();
                            while($row = $extra_result->fetch_assoc()) {
                                $data = json_decode($row['notes'], true);
                                if(is_array($data)) {
                                    foreach(array_keys($data) as $key) {
                                        if(!in_array($key, $extra_keys)) {
                                            $extra_keys[] = $key;
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
                            <?php if(!empty($extra_keys)) : ?>
                                <div class="checkbox-group full-width"><strong>Extra Fields (from scholar notes):</strong></div>
                                <?php foreach($extra_keys as $key) : ?>
                                    <div class="checkbox-group">
                                        <input type="checkbox" id="extra_<?= $key ?>" name="columns[]" value="extra_<?= $key ?>" />
                                        <label for="extra_<?= $key ?>"><?= htmlspecialchars($key) ?></label>
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
                    <div class="form-actions" id="proceed-btn-wrapper" style="margin-top:20px; margin-bottom:30px; display:block;">
                        <button type="button" id="proceed-btn" class="btn btn-success" style="width:100%; padding: 20px 16px">Preview & Rearrange</button>
                    </div>

                    <!-- Preview & Export Area -->
                    <div class="form-section" id="preview-section" style="display:none;">
                        <h3>Step 4: Preview & Rearrange Columns</h3>
                        <p><strong>Drag column headers to reorder. The order in the preview will be used in the exported file.</strong></p>
                        <div class="table-wrapper">
                            <table id="preview-table">
                                <thead id="preview-headers"></thead>
                                <tbody id="preview-body"></tbody>
                            </table>
                        </div>
                        <div class="form-actions" style="margin-top:15px;">
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
                <div class="sys-modal-content" style="text-align: center; padding: 35px 20px;">
                    <div class="sys-spinner"></div>
                    <h3 style="margin-top: 18px; color: #1e4628; font-size: 18px;">Generating Preview...</h3>
                    <p style="color: #666; font-size: 13px; margin-top: 6px;">Please wait while the system prepares your data preview.</p>
                </div>
            </div>

            <!-- Download Loading Modal -->
            <div id="exportDownloadModal" class="sys-modal">
                <div class="sys-modal-content" style="text-align: center; padding: 35px 20px;">
                    <div class="sys-spinner"></div>
                    <h3 style="margin-top: 18px; color: #1e4628; font-size: 18px;">Exporting & Downloading File...</h3>
                    <p style="color: #666; font-size: 13px; margin-top: 6px;">Please wait while your Excel file is generated and downloaded.</p>
                </div>
            </div>

            <!-- Footer -->
            <div class="footer">
                © 2026 PIT ScholarshipHub. All rights reserved.
            </div>
        </div>

        <script src="../assets/js/export.js?v=<?= filemtime('../assets/js/export.js') ?>"></script>

        <!-- Custom JS -->
        <script src="../assets/js/export_reorder.js?v=<?= filemtime('../assets/js/export_reorder.js') ?>"></script>
    <?php endif; ?>
</body>
</html>