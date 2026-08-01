<?php 
include __DIR__ . '/../includes/conn.php';
session_start();

// get scholar id
$scholar_id = isset($_GET['scholar_id']) ? (int) $_GET['scholar_id'] : 0;

// scholar info query
$scholar_sql = "SELECT scholar_info.*, 
                scholarship_program.name AS scholarship_program_name,  
                program.name AS program_name,
                college_name
                FROM scholar_info 
                INNER JOIN scholarship_program ON scholar_info.scholarship_program_id = scholarship_program.id
                INNER JOIN program ON scholar_info.program_id = program.id
                INNER JOIN college ON program.college_id = college.id
                WHERE scholar_info.id = ?";
$scholar_stmt = $conn->prepare($scholar_sql);
$scholar_stmt->bind_param('i', $scholar_id);
$scholar_stmt->execute();
$scholar_result = $scholar_stmt->get_result();

// initialize variables
$last_name = "";
$given_name = "";
$ext_name = "";
$middle_name = "";
$id_num = "";
$sex = 0;
$birthdate = "";
$scholarship_program = "";
$program = "";
$year_lvl = "";
$college = "";
$academic_status = "";
$address = "";
$zipcode = "";
$disability = "";
$contact_num = "";
$email = "";
$ip_group = "";
$father_last = "";
$father_given = "";
$father_middle = "";
$mother_last = "";
$mother_given = "";
$mother_middle = "";

// insert values to initialized variables
if($row = $scholar_result->fetch_assoc()) {
    $last_name = $row['last_name'];
    $given_name = $row['given_name'];
    $ext_name = $row['ext_name'] === "N/A" ? "" : $row['ext_name'];
    $middle_name = $row['middle_name'];
    $id_num = $row['student_id'];
    $sex = $row['sex'] === 0 ? "Male" : "Female";
    $birthdate = $row['birthdate'];
    $scholarship_program = $row['scholarship_program_name'];
    $program = $row['program_name'];
    $year = $row['year_level'] ?? 0;
    $college = $row['college_name'];
    $academic_status = $row['academic_status'];
    $address = $row['permanent_address'];
    $zipcode = $row['zipcode'];
    $disability = empty($row['disability']) ? "Not Applicable" : $row['disability']; // display Not Applicable if empty
    $contact_num = $row['contact_num'];
    $email = $row['email'];
    $ip_group = empty($row['ip_group']) ? "Not Applicable" : $row['ip_group']; // display Not Applicable if empty
    $father_last = $row['father_last_name'];
    $father_given = $row['father_given_name'];
    $father_middle = $row['father_middle_name'];
    $mother_last = $row['mother_last_name'];
    $mother_given = $row['mother_given_name'];
    $mother_middle = $row['mother_middle_name'];

    // conditions for year level
    if($year == 1) {
        $year_lvl = "1st Year";
    } elseif($year == 2) {
        $year_lvl = "2nd Year";
    } elseif($year == 3) {
        $year_lvl = "3rd Year";
    } elseif($year >= 4 && $year <= 6) {
        $year_lvl = $year . "th Year";
    } else {
        $year_lvl = "Not specified";
    }

} else {
    // alert user's if no results in query
    $_SESSION['fail'] = "Scholar not available";
    header("Location: scholar_list.php");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Scholar Profile - PIT ScholarshipHub</title>
    <link rel="stylesheet" href="../assets/css/scholar_profile.css?v=<?= filemtime('../assets/css/scholar_profile.css') ?>">
    <link rel="icon" href="../assets/images/PIT%20LOGO.png">
</head>
<body>
    <?php 
    // success/fail messages
    if(isset($_SESSION['success'])) {
        echo "<script>alert('" . $_SESSION['success'] . "')</script>";
        unset($_SESSION['success']);
    } else if(isset($_SESSION['fail'])) {
        echo "<script>alert('" . $_SESSION['fail'] . "')</script>";
        unset($_SESSION['fail']);
    }
    ?>
    <?php if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') : ?>
        <script>alert('You are not logged in')</script>
        <script>window.location.href='login.php'</script>
    <?php else : ?>
        <div class="container">
            <!-- Header -->
            <div class="header" >
                <div class="header-left">
                    <img src="../assets/images/PIT%20LOGO.png" alt="PIT Logo">
                    <div>
                        <h1>PIT ScholarshipHub</h1>
                        <p>Scholar Profile</p>
                    </div>
                </div>
                <div class="header-right">
                    <span class="welcome-text">Welcome, Admin!</span>
                    <!-- logout button -->
                    <form action="../functions/logout.php" method="post" style="display: inline;">
                        <button type="submit" class="logout-btn" onclick="return confirm('Logout?')">Logout</button>
                    </form>
                </div>
            </div>

            <!-- Navigation -->
            <nav class="nav-bar">
                <ul>
                    <li><a href="dashboard.php">Dashboard</a></li>
                    <li><a href="scholar_list.php" class="active">Scholars</a></li>
                    <li><a href="announcements.php">Announcements</a></li>
                    <li><a href="scholarship_program.php">Scholarship Programs</a></li>
                    <li><a href="upload_excel.php">Import Excel</a></li>
                    <li><a href="export_scholars.php">Export Excel</a></li>
                </ul>
            </nav>

            <!-- Page Content -->
            <div class="page-content">
                <!-- Page Header -->
                <div class="page-header">
                    <h2>Scholar Profile</h2>
                    <div class="page-actions">
                        <a href="scholar_list.php" class="btn btn-secondary">Back to List</a>
                        <a href="edit_scholar.php?id=<?= $scholar_id ?>" class="btn btn-warning">Edit Scholar</a>
                        <a href="academic_performance.php?scholar_id=<?= $scholar_id ?>" class="btn btn-info">Manage GWA & Units</a>
                    </div>
                </div>

                <!-- Scholar Profile Content -->
                <div class="profile-grid">
                    <!-- Personal Information -->
                    <div class="profile-card">
                        <h3>Personal Information</h3>
                        <div class="info-group">
                            <div class="info-row">
                                <span class="info-label">Student ID</span>
                                <span class="info-value"><?= htmlspecialchars($id_num) ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Full Name</span>
                                <span class="info-value"><?= htmlspecialchars($last_name) ?>, <?= htmlspecialchars($given_name) ?> <?= htmlspecialchars($middle_name) ?> <?= htmlspecialchars($ext_name) ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Sex</span>
                                <span class="info-value"><?= htmlspecialchars($sex) ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Birthdate</span>
                                <span class="info-value"><?= htmlspecialchars($birthdate) ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Disability</span>
                                <span class="info-value"><?= htmlspecialchars($disability) ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">IP Group</span>
                                <span class="info-value"><?= htmlspecialchars($ip_group) ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Contact Information -->
                    <div class="profile-card">
                        <h3>Contact Information</h3>
                        <div class="info-group">
                            <div class="info-row">
                                <span class="info-label">Permanent Address</span>
                                <span class="info-value"><?= htmlspecialchars($address) ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Zipcode</span>
                                <span class="info-value"><?= htmlspecialchars($zipcode) ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Contact Number</span>
                                <span class="info-value"><?= htmlspecialchars($contact_num) ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Email</span>
                                <span class="info-value"><?= htmlspecialchars($email) ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Family Background -->
                    <div class="profile-card">
                        <h3>Family Background</h3>
                        <div class="family-section">
                            <h4>Father</h4>
                            <div class="info-group">
                                <div class="info-row">
                                    <span class="info-label">Name</span>
                                    <span class="info-value"><?= htmlspecialchars($father_last) ?>, <?= htmlspecialchars($father_given) ?> <?= htmlspecialchars($father_middle) ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="family-section">
                            <h4>Mother</h4>
                            <div class="info-group">
                                <div class="info-row">
                                    <span class="info-label">Name</span>
                                    <span class="info-value"><?= htmlspecialchars($mother_last) ?>, <?= htmlspecialchars($mother_given) ?> <?= htmlspecialchars($mother_middle) ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Academic Information -->
                    <div class="profile-card">
                        <h3>Scholarship & Academic Information</h3>
                        <div class="info-group">
                            <div class="info-row">
                                <span class="info-label">Scholarship Program</span>
                                <span class="info-value"><?= htmlspecialchars($scholarship_program) ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Program</span>
                                <span class="info-value"><?= htmlspecialchars($program) ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">College</span>
                                <span class="info-value"><?= htmlspecialchars($college) ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Year Level</span>
                                <span class="info-value"><?= htmlspecialchars($year_lvl) ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Academic Status</span>
                                <span class="info-value"><?= htmlspecialchars($academic_status) ?></span>
                            </div>
                        </div>
                        <div class="info-group">
                            <h4>Academic Performance</h4>
                            <?php 
                            $academic_performance_sql = "SELECT * FROM academic_performance WHERE scholar_id = ? ORDER BY school_year DESC, semester DESC";
                            $academic_performance_stmt = $conn->prepare($academic_performance_sql);
                            $academic_performance_stmt->bind_param('i', $scholar_id);
                            $academic_performance_stmt->execute();
                            $academic_performance_result = $academic_performance_stmt->get_result();
                            
                            if($academic_performance_result->num_rows > 0) : ?>
                                <div class="academic-table-wrapper">
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>Semester</th>
                                                <th>GWA</th>
                                                <th>Units</th>
                                                <th>Year Level</th>
                                                <th>School Year</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while($row = $academic_performance_result->fetch_assoc()) : ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($row['semester']) ?></td>
                                                    <td><?= htmlspecialchars($row['gwa']) ?></td>
                                                    <td><?= htmlspecialchars($row['units']) ?></td>
                                                    <td><?= htmlspecialchars($row['year_level']) ?></td>
                                                    <td><?= htmlspecialchars($row['school_year']) ?></td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="no-data">No academic records found.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="footer">
                © 2026 PIT ScholarshipHub. All rights reserved.
            </div>
        </div>
    <?php endif; ?>
</body>
</html>