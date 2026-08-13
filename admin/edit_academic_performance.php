<?php 
include __DIR__ . "/../includes/conn.php";
session_start();

// get scholar id from url param
$record_id = (int) ($_GET['record_id'] ?? 0);
$scholar_id = (int) ($_GET['scholar_id'] ?? 0);

// validate id
if($scholar_id == 0) {
    $message = "Scholar";
    if($record_id == 0) {
        $message .= " & Record";
    }
    $_SESSION['fail'] = "$message does not exist.";
    header("Location: academic_performance.php?scholar_id=$scholar_id");
    exit();
} 

// initialize variables
$semester = "";
$gwa = 0.00;
$units = 0.00;

// scholar's academic performance query
$academic_sql = "SELECT * FROM academic_performance WHERE id = ?";
$academic_stmt = $conn->prepare($academic_sql);
$academic_stmt->bind_param('i', $record_id);
$academic_stmt->execute();
$academic_result = $academic_stmt->get_result();

if($academic_result->num_rows > 0) {
    // store values into initialized variables
    if($row=$academic_result->fetch_assoc()) {
        $semester = $row['semester'];
        $gwa = $row['gwa'];
        $units = $row['units'];
    }
} else {
    $_SESSION['fail'] = "Scholar does not exist!";
    header("Location: academic_performance.php?scholar_id=$scholar_id");
    exit();
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Academic Performance - PIT ScholarshipHub</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/form.css?v=<?= filemtime('../assets/css/form.css') ?>">
    <link rel="icon" href="../assets/images/PIT%20LOGO.png">
</head>
<body>
    <?php 
    if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') : ?>
        <!-- if not logged in, redirect user to login page -->
        <script>alert('You are not logged in')</script>
        <script>window.location.href='login.php'</script>
    <?php else : ?>
        <div class="container">
            <!-- Header -->
            <div class="header">
                <div class="header-left">
                    <img src="../assets/images/PIT%20LOGO.png" alt="PIT Logo">
                    <div>
                        <h1>PIT ScholarshipHub</h1>
                        <p>Edit Academic Performance</p>
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
                    <!-- Dashboard -->
                    <li><a href="dashboard.php">Dashboard</a></li>

                    <!-- Records Dropdown -->
                    <li class="dropdown">
                        <a href="#" class="dropdown-toggle active">Records <div class="arrow-down">▼</div></a>
                        <ul class="dropdown-menu">
                            <li><a href="scholar_list.php">Scholars</a></li>
                            <li><a href="scholarship_program.php" class="active">Scholarship Programs</a></li>
                            <li><a href="archive.php">Archives</a></li>
                        </ul>
                    </li>

                    <!-- Import/Export Dropdown -->
                    <!-- <li class="dropdown">
                        <a href="#" class="dropdown-toggle">Import / Export <div class="arrow-down">▼</div></a>
                        <ul class="dropdown-menu">
                            <li><a href="upload_excel.php">Import Excel</a></li>
                            <li><a href="export_scholars.php">Export Excel</a></li>
                        </ul>
                    </li> -->

                    <!-- Announcements -->
                    <li><a href="announcements.php">Announcements</a></li>

                    <!-- Program / College Management -->
                    <li><a href="program_management.php">Program & College Management</a></li>

                    <!-- Account Management -->
                    <?php if($_SESSION['role'] == "admin") : ?>
                        <li><a href="account_management.php">Account Management</a></li>
                    <?php endif; ?>
                </ul>
            </nav>

            <!-- Page Content -->
            <div class="page-content">
                <!-- Page Header -->
                <div class="page-header">
                    <h2>Edit Academic Performance</h2>
                    <div class="page-actions">
                        <a href="academic_performance.php?scholar_id=<?= $scholar_id ?>" class="btn btn-secondary">Back to List</a>
                    </div>
                </div>

                <!-- Edit Form -->
                <!-- edit form -->
                <form action="../functions/admin/edit_academic_performance_process.php" method="POST" class="reusable-form">
                    <div class="form-section">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="semester">Semester <span class="required">*</span></label>
                                <select name="sem" id="semester" required>
                                    <option selected disabled>--Select Semester--</option>
                                    <option value="1st Semester" <?= $semester == "1st Semester" ? "selected" : "" ?>>1st Semester</option>
                                    <option value="2nd Semester" <?= $semester == "2nd Semester" ? "selected" : "" ?>>2nd Semester</option>
                                    <option value="3rd Semester" <?= $semester == "3rd Semester" ? "selected" : "" ?>>3rd Semester</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="gwa">General Weighted Average (GWA) <span class="required">*</span></label>
                                <input type="number" name="gwa" id="gwa" step="0.01" min="1.00" max="4.00" placeholder="e.g., 1.75" value="<?= htmlspecialchars($gwa) ?>" required/>
                            </div>

                            <div class="form-group">
                                <label for="units">Units <span class="required">*</span></label>
                                <input type="number" name="units" id="units" step="0.5" min="1" placeholder="e.g., 20.0" value="<?= htmlspecialchars($units) ?>" required/>
                            </div>
                        </div>

                        <!-- <div class="form-row">
                            <div class="form-group full-width">
                                <label for="start_year">School Year <span class="required">*</span></label>
                                <div class="school-year-group">
                                    <select name="start_year" id="start_year" required>
                                        <?php for($y = date('Y') - 5; $y <= date('Y'); $y++) : ?>
                                            <option value="<?= $y ?>" <?= $start_year == $y ? "selected" : "" ?>><?= $y ?></option>
                                        <?php endfor; ?>
                                    </select>
                                    <span class="dash">–</span>
                                    <select name="end_year" id="end_year" required>
                                        <?php for($y = date('Y'); $y <= date('Y') + 5; $y++) : ?>
                                            <option value="<?= $y ?>" <?= $end_year == $y ? "selected" : "" ?>><?= $y ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <small style="color: #999; font-size: 12px; margin-top: 4px;">Select the start and end year of the academic year.</small>
                            </div>
                        </div> -->
                    </div>

                    <input type="hidden" name="scholar_id" value="<?= $scholar_id ?>"/>
                    <input type="hidden" name="record_id" value="<?= $record_id ?>">

                    <div class="form-actions">
                        <button type="submit" name="update" class="btn btn-success">Update Record</button>
                        <a href="academic_performance.php?scholar_id=<?= $scholar_id ?>" class="btn btn-secondary">Cancel</a>
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