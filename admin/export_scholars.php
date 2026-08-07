<?php 
include __DIR__ . "/../includes/conn.php";
session_start();

// show error message if any
if(isset($_SESSION['fail'])) {
    echo "<script>alert('" . $_SESSION['fail'] . "')</script>";
    unset($_SESSION['fail']);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Export Scholar Records - PIT ScholarshipHub</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/export_scholars.css?v=<?= filemtime('../assets/css/export_scholars.css') ?>">
    <link rel="icon" href="../assets/images/PIT%20LOGO.png">
</head>
<body>
    <!-- check if logged in or not -->
    <?php if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== "admin") : ?>
        <script>alert('You are not logged in')</script>
        <?php $_SESSION = []; ?>
        <script>window.location.href='login.php'</script>
        <!-- if logged in, load page -->
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

            <!-- Navigation -->
            <nav class="nav-bar">
                <ul>
                    <li><a href="dashboard.php">Dashboard</a></li>
                    <li><a href="scholar_list.php">Scholars</a></li>
                    <li><a href="announcements.php">Announcements</a></li>
                    <li><a href="scholarship_program.php">Scholarship Programs</a></li>
                    <li><a href="upload_excel.php">Import Excel</a></li>
                    <li><a href="export_scholars.php" class="active">Export Excel</a></li>
                </ul>
            </nav>

            <!-- Page Content -->
            <div class="page-content">
                <!-- Page Header -->
                <div class="page-header">
                    <h2>Export Scholar Records</h2>
                    <div class="page-actions">
                        <a href="scholar_list.php" class="btn btn-secondary">← Back to Scholars</a>
                    </div>
                </div>

                <!-- Export Form -->
                <form action="../functions/admin/export_scholars_process.php" method="POST" class="export-form">
                    <!-- Filters Section -->
                    <div class="form-section">
                        <h3>Step 1: Select Filters</h3>
                        <!-- filters and column selection options for exporting the file -->
                        <div class="form-row">
                            <div class="form-group">
                                <label for="scholar_program">Scholarship Program</label>
                                <select name="scholar_program" id="scholar_program" required>
                                    <option value="all">All Scholarship Programs</option>
                                    <?php 
                                    $scholarship_program_sql = "SELECT name, code FROM scholarship_program WHERE is_active = 1";
                                    $scholarship_program_stmt = $conn->prepare($scholarship_program_sql);
                                    $scholarship_program_stmt->execute();
                                    $scholarship_program_result = $scholarship_program_stmt->get_result();
                                    while($row = $scholarship_program_result->fetch_assoc()) : ?>
                                        <option value="<?= $row['name'] ?>"><?= htmlspecialchars($row['name']) ?> (<?= htmlspecialchars($row['code']) ?>)</option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="program">Program / Course</label>
                                <select name="program" id="program" required>
                                    <option value="all">All Programs</option>
                                    <?php 
                                    $program_sql = "SELECT name FROM program";
                                    $program_stmt = $conn->prepare($program_sql);
                                    $program_stmt->execute();
                                    $program_result = $program_stmt->get_result();
                                    while($row = $program_result->fetch_assoc()) : ?>
                                        <option value="<?= $row['name'] ?>"><?= htmlspecialchars($row['name']) ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Column Selection Section -->
                    <div class="form-section">
                        <h3>Step 2: Select Columns to Include</h3>

                        <!-- checkbox required fields which shows checked and user cannot uncheck -->
                        <p><strong>Required Fields (always included):</strong></p>
                        <div class="checkbox-group">
                            <input type="checkbox" id="student_id" checked disabled/>
                            <label for="student_id">Student ID</label>
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" id="last_name" checked disabled/>
                            <label for="last_name">Last Name</label>
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" id="given_name" checked disabled/>
                            <label for="given_name">Given Name</label>
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" id="ext_name" checked disabled/>
                            <label for="ext_name">Extended Name</label>
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" id="middle_name" checked disabled/>
                            <label for="middle_name">Middle Name</label>
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" id="program_name" checked disabled/>
                            <label for="program_name">Program Name</label>
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" id="scholarship" checked disabled/>
                            <label for="scholarship">Scholarship Program</label>
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" id="year_level" checked disabled/>
                            <label for="year_level">Year Level</label>
                        </div>

                        <p style="margin-top: 15px;"><strong>Optional Fields:</strong></p>
                        <!-- checkbox for optional fields -->
                        <div class="checkbox-group">
                            <input type="checkbox" id="father_name" name="columns[]" value="father_name" checked/>
                            <label for="father_name">Father's Name</label>
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" id="mother_name" name="columns[]" value="mother_name" checked/>
                            <label for="mother_name">Mother's Name</label>
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" id="permanent_address" name="columns[]" value="permanent_address" checked/>
                            <label for="permanent_address">Permanent Address</label>
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" id="disability" name="columns[]" value="disability" checked/>
                            <label for="disability">Disability</label>
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" id="contact" name="columns[]" value="contact" checked/>
                            <label for="contact">Contact Number</label>
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" id="birthdate" name="columns[]" value="birthdate" checked/>
                            <label for="birthdate">Birthdate</label>
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" id="sex" name="columns[]" value="sex" checked/>
                            <label for="sex">Sex</label>
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" id="email" name="columns[]" value="email" checked/>
                            <label for="email">Email Address</label>
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" id="ip_group" name="columns[]" value="ip_group" checked/>
                            <label for="ip_group">IP Group</label>
                        </div>

                        <!-- dynamic checkbox for extra fields (if any) to include or not -->
                        <?php 
                        $extra_keys = [];
                        $extra_sql = "SELECT notes FROM scholar_notes WHERE notes IS NOT NULL AND notes <> ''";
                        $extra_stmt = $conn->prepare($extra_sql);
                        $extra_stmt->execute();
                        $extra_result = $extra_stmt->get_result();

                        while($row = $extra_result->fetch_assoc()) {
                            // data from db is a json so we decode it
                            $data = json_decode($row['notes'], true);
                            if(is_array($data)) {
                                foreach(array_keys($data) as $key) {
                                    if(!in_array($key, $extra_keys)) {
                                        $extra_keys[] = $key;
                                    }
                                }
                            }
                        }

                        if(!empty($extra_keys)) : ?>
                            <p style="margin-top: 15px;"><strong>Extra Fields (from scholar notes):</strong></p>
                            <?php foreach($extra_keys as $key) : ?>
                                <div class="checkbox-group">
                                    <input type="checkbox" id="extra_<?= $key ?>" name="columns[]" value="extra_<?= $key ?>"/>
                                    <label for="extra_<?= $key ?>"><?= htmlspecialchars($key) ?></label>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Filename -->
                    <div class="form-section">
                        <h3>Step 3: Set Filename</h3>
                        <div class="form-row">
                            <div class="form-group full-width">
                                <label for="filename">Filename <span class="required">*</span></label>
                                <input type="text" name="filename" id="filename" value="scholars_export_<?= date('Y-m-d') ?>" required/>
                                <small class="help-text">.xlsx will be added automatically.</small>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Buttons -->
                    <div class="form-actions">
                        <button type="submit" name="export" class="btn btn-success" onclick="return confirm('Export scholar list?')">Export</button>
                        <a href="scholar_list.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>

            <!-- Footer -->
            <div class="footer">
                © 2026 PIT ScholarshipHub. All rights reserved.
            </div>
        </div>
    <?php endif; ?>
</body>
</html>