<?php 
include __DIR__ . '/../includes/conn.php';
session_start();

// Check if logged in and is student
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

$scholar_id = (int) ($_SESSION['user_id'] ?? 0);

$scholar_sql = "SELECT scholar_info.*, 
                scholarship_program.name AS scholarship_program_name,  
                program.name AS program_name,
                college_name,
                scholarship_program.pay_amount,
                scholarship_program.min_gwa_req
                FROM scholar_info 
                LEFT JOIN scholarship_program ON scholar_info.scholarship_program_id = scholarship_program.id
                LEFT JOIN program ON scholar_info.program_id = program.id
                LEFT JOIN college ON program.college_id = college.id
                WHERE scholar_info.id = ?";
$scholar_stmt = $conn->prepare($scholar_sql);
$scholar_stmt->bind_param('i', $scholar_id);
$scholar_stmt->execute();
$scholar_result = $scholar_stmt->get_result();

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
$allowance = 0;
$min_gwa_req = 0;
$father_last = "";
$father_given = "";
$father_middle = "";
$mother_last = "";
$mother_given = "";
$mother_middle = "";
$latest_gwa = null;
$is_flagged = false;

if($row = $scholar_result->fetch_assoc()) {
    $last_name = $row['last_name'] ?? '';
    $given_name = $row['given_name'] ?? '';
    $ext_name = $row['ext_name'] === "N/A" ? "" : ($row['ext_name'] ?? '');
    $middle_name = $row['middle_name'] ?? '';
    $id_num = $row['student_id'] ?? '';
    $sex = ($row['sex'] ?? 0) === 0 ? "Male" : "Female";
    $birthdate = $row['birthdate'] ?? '';
    $scholarship_program = $row['scholarship_program_name'] ?? 'Not Assigned';
    $program = $row['program_name'] ?? 'Not Assigned';
    $year = $row['year_level'] ?? 0;
    $college = $row['college_name'] ?? 'Not Assigned';
    $academic_status = $row['academic_status'] ?? 'Active';
    $address = $row['permanent_address'] ?? '';
    $zipcode = $row['zipcode'] ?? '';
    $disability = empty($row['disability']) ? "Not Applicable" : $row['disability'];
    $contact_num = $row['contact_num'] ?? '';
    $email = $row['email'] ?? '';
    $ip_group = empty($row['ip_group']) ? "Not Applicable" : $row['ip_group'];
    $allowance = $row['pay_amount'] ?? 0;
    $min_gwa_req = $row['min_gwa_req'] ?? 0;
    $father_last = $row['father_last_name'] ?? '';
    $father_given = $row['father_given_name'] ?? '';
    $father_middle = $row['father_middle_name'] ?? '';
    $mother_last = $row['mother_last_name'] ?? '';
    $mother_given = $row['mother_given_name'] ?? '';
    $mother_middle = $row['mother_middle_name'] ?? '';

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

    // Get latest GWA
    $gwa_sql = "SELECT gwa FROM academic_performance WHERE scholar_id = ? ORDER BY school_year DESC, semester DESC LIMIT 1";
    $gwa_stmt = $conn->prepare($gwa_sql);
    $gwa_stmt->bind_param('i', $scholar_id);
    $gwa_stmt->execute();
    $gwa_result = $gwa_stmt->get_result();
    if($gwa_row = $gwa_result->fetch_assoc()) {
        $latest_gwa = $gwa_row['gwa'];
    }

    // Check if flagged
    if($latest_gwa !== null && $min_gwa_req > 0 && $latest_gwa < $min_gwa_req) {
        $is_flagged = true;
    }
} else {
    $_SESSION['fail'] = "Scholar not found.";
    header("Location: dashboard.php");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>My Profile - PIT ScholarshipHub</title>
    <!-- ✅ Reusing existing CSS file -->
    <link rel="stylesheet" href="../assets/css/scholar_profile.css?v=<?= filemtime('../assets/css/scholar_profile.css') ?>">
    <link rel="icon" href="../assets/images/PIT%20LOGO.png">
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="header-left">
                <img src="../assets/images/PIT%20LOGO.png" alt="PIT Logo">
                <div>
                    <h1>PIT ScholarshipHub</h1>
                    <p>My Profile</p>
                </div>
            </div>
            <div class="header-right">
                <span class="welcome-text">Welcome, <?= htmlspecialchars($given_name) ?>!</span>
                <form action="../functions/logout.php" method="post" style="display: inline;">
                    <button type="submit" class="logout-btn" onclick="return confirm('Logout?')">Logout</button>
                </form>
            </div>
        </div>

        <!-- Navigation -->
        <nav class="nav-bar">
            <ul>
                <li><a href="profile.php" class="active">My Profile</a></li>
            </ul>
        </nav>

        <!-- Page Content -->
        <div class="page-content">
            <!-- Page Header -->
            <div class="page-header">
                <h2>My Profile</h2>
                <!-- ✅ Status badges -->
                <div class="page-actions">
                    <span class="status-badge <?= strtolower($academic_status) === 'active' ? 'status-active' : 'status-inactive' ?>">
                        <?= htmlspecialchars($academic_status) ?>
                    </span>
                    <?php if($is_flagged): ?>
                        <span class="status-badge status-flagged">⚠️ Below GWA Requirement</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Profile Grid -->
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

                <!-- Scholarship & Academic Information -->
                <div class="profile-card">
                    <h3>Scholarship & Academic Information</h3>
                    <div class="info-group">
                        <div class="info-row">
                            <span class="info-label">Scholarship Program</span>
                            <span class="info-value"><?= htmlspecialchars($scholarship_program) ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Allowance</span>
                            <span class="info-value">₱ <?= number_format($allowance, 2) ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Program / Course</span>
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
                            <span class="info-label">Current GWA</span>
                            <span class="info-value <?= $is_flagged ? 'value-warning' : '' ?>">
                                <?= $latest_gwa !== null ? htmlspecialchars($latest_gwa) : 'N/A' ?>
                                <?php if($is_flagged): ?>
                                    <span style="color: #e74c3c; font-size: 12px;">⚠️ Below <?= htmlspecialchars($min_gwa_req) ?></span>
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Minimum GWA Required</span>
                            <span class="info-value"><?= $min_gwa_req > 0 ? htmlspecialchars($min_gwa_req) : 'No requirement' ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Academic Performance History -->
            <div class="profile-card" style="margin-top: 20px;">
                <h3>Academic Performance History</h3>
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

        <!-- Footer -->
        <div class="footer">
            © 2026 PIT ScholarshipHub. All rights reserved.
        </div>
    </div>
</body>
</html>