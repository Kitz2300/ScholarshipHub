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
    <!-- SortableJS for drag & drop -->
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.14.0/Sortable.min.js"></script>
</head>
<body>
    <?php 
    if(isset($_SESSION['fail'])) {
        echo "<script>alert('" . $_SESSION['fail'] . "')</script>";
        unset($_SESSION['fail']);
    }
    ?>
    <?php if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== "admin") : ?>
        <script>alert('You are not logged in')</script>
        <?php $_SESSION = []; ?>
        <script>window.location.href='login.php'</script>
    <?php else: ?>
        <div class="container">
            <!-- Header -->
            <div class="header">
                <div class="header-left">
                    <img src="../assets/images/PIT%20LOGO.png" alt="PIT Logo">
                    <div>
                        <h1>PIT ScholarshipHub</h1>
                        <p>Export Scholar Records</p>
                    </div>
                </div>
                <div class="header-right">
                    <span class="welcome-text">Welcome, Admin!</span>
                    <form action="../functions/logout.php" method="post" style="display: inline;">
                        <button type="submit" class="logout-btn" onclick="return confirm('Logout?')">Logout</button>
                    </form>
                </div>
            </div>

            <?php include __DIR__ . "/../includes/navigation.php"; ?>

            <!-- Page Content -->
            <div class="page-content">
                <div class="page-header">
                    <h2>Export Scholar Records</h2>
                    <div class="page-actions">
                        <a href="scholar_list.php" class="btn btn-secondary">← Back to Scholars</a>
                    </div>
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
                                    <option value="all">All Scholarship Programs</option>
                                    <?php 
                                    $sp_sql = "SELECT name, code FROM scholarship_program
                                               WHERE EXISTS (SELECT 1 FROM scholar_info 
                                                             WHERE scholar_info.scholarship_program_id = scholarship_program.id 
                                                             AND scholar_info.is_archived = 0 
                                                             AND scholarship_program.is_archived = 0)
                                               ORDER BY name ASC";
                                    $sp_stmt = $conn->prepare($sp_sql);
                                    $sp_stmt->execute();
                                    $sp_result = $sp_stmt->get_result();
                                    while($row = $sp_result->fetch_assoc()) : ?>
                                        <option value="<?= $row['name'] ?>"><?= htmlspecialchars($row['name']) ?> (<?= htmlspecialchars($row['code']) ?>)</option>
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
                                        <option value="<?= $row['name'] ?>"><?= htmlspecialchars($row['name']) ?></option>
                                    <?php endwhile; ?>
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
                                'mother_name' => "Mother's Name",
                                'permanent_address' => 'Permanent Address',
                                'disability' => 'Disability',
                                'contact' => 'Contact Number',
                                'birthdate' => 'Birthdate',
                                'sex' => 'Sex',
                                'email' => 'Email Address',
                                'ip_group' => 'IP Group',
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
                            foreach($all_columns as $col => $label) : ?>
                                <div class="checkbox-group">
                                    <input type="checkbox" id="col_<?= $col ?>" name="columns[]" value="<?= $col ?>" checked />
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

                    <!-- 🔹 Preview & Rearrange button – now stretched full width with spacing -->
                    <div class="form-actions" style="margin-top:20px; margin-bottom:30px; display:block;">
                        <button type="button" id="proceed-btn" class="btn btn-success" style="width:100%; padding: 20px 16px">Preview & Rearrange</button>
                    </div>

                    <!-- Preview & Export Area -->
                    <div class="form-section" id="preview-section" style="display:none;">
                        <h3>Step 4: Preview & Rearrange Columns</h3>
                        <p>Drag column headers to reorder. The order in the preview will be used in the exported file.</p>
                        <div class="table-wrapper">
                            <table id="preview-table">
                                <thead id="preview-headers"></thead>
                                <tbody id="preview-body"></tbody>
                            </table>
                        </div>
                        <div class="form-actions" style="margin-top:15px;">
                            <button type="button" id="export-btn" class="btn btn-success">Export Now</button>
                            <button type="button" id="refresh-preview" class="btn btn-secondary">Refresh Preview</button>
                            <a href="export_scholars.php" class="btn btn-cancel">Cancel</a>
                        </div>
                    </div>

                    <!-- Hidden input to store column order -->
                    <input type="hidden" name="column_order" id="column_order" value="">
                </form>
            </div>

            <!-- Footer -->
            <div class="footer">
                © 2026 PIT ScholarshipHub. All rights reserved.
            </div>
        </div>

        <!-- Custom JS -->
        <script src="../assets/js/export_reorder.js"></script>
    <?php endif; ?>
</body>
</html>