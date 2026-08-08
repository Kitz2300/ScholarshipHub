<?php 
session_start();

// ✅ Correct role check for student-only pages
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

// Get student's basic info from session or database
$student_id = $_SESSION['student_id'] ?? 0;

// Optional: Fetch additional data from database
include '../includes/conn.php';

$sql = "SELECT scholar_info.*, 
               scholarship_program.name AS scholarship_program_name,
               scholarship_program.pay_amount,
               (SELECT gwa FROM academic_performance WHERE scholar_id = scholar_info.id ORDER BY school_year DESC, semester DESC LIMIT 1) AS latest_gwa,
               (SELECT year_level FROM academic_performance WHERE scholar_id = scholar_info.id ORDER BY school_year DESC, semester DESC LIMIT 1) AS latest_year_level
        FROM scholar_info 
        LEFT JOIN scholarship_program ON scholar_info.scholarship_program_id = scholarship_program.id
        WHERE scholar_info.student_id = ? OR scholar_info.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $student_id, $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$scholar = $result->fetch_assoc();

// Default values if no record found
$scholarship_name = $scholar['scholarship_program_name'] ?? 'No scholarship assigned';
$allowance = $scholar['pay_amount'] ?? 0;
$gwa = $scholar['latest_gwa'] ?? 'N/A';
$year_level = $scholar['latest_year_level'] ?? 'N/A';
$academic_status = $scholar['academic_status'] ?? 'Active';

// Flag check
$is_flagged = false;
if(isset($scholar['latest_gwa']) && isset($scholar['scholarship_program_id'])) {
    $min_gwa_sql = "SELECT min_gwa_req FROM scholarship_program WHERE id = ?";
    $min_stmt = $conn->prepare($min_gwa_sql);
    $min_stmt->bind_param("i", $scholar['scholarship_program_id']);
    $min_stmt->execute();
    $min_result = $min_stmt->get_result();
    if($min_row = $min_result->fetch_assoc()) {
        if($min_row['min_gwa_req'] > 0 && $scholar['latest_gwa'] < $min_row['min_gwa_req']) {
            $is_flagged = true;
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Scholar Dashboard - PIT ScholarshipHub</title>
    <link rel="stylesheet" href="../assets/css/student_dashboard.css?v=<?= filemtime('../assets/css/student_dashboard.css') ?>">
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
                    <p>Scholar Dashboard</p>
                </div>
            </div>
            <div class="header-right">
                <span class="welcome-text">Welcome, Scholar!</span>
                <form action="../functions/logout.php" method="post" style="display: inline;">
                    <button type="submit" class="logout-btn" onclick="return confirm('Logout?')">Logout</button>
                </form>
            </div>
        </div>

        <!-- Navigation -->
        <nav class="nav-bar">
            <ul>
                <li><a href="dashboard.php" class="active">Dashboard</a></li>
                <li><a href="profile.php">My Profile</a></li>
            </ul>
        </nav>

        <!-- Page Content -->
        <div class="page-content">
            <!-- Page Header -->
            <div class="page-header">
                <h2>My Scholarship Information</h2>
            </div>

            <!-- Stats/Info Cards -->
            <div class="info-grid">
                <!-- Scholarship Program -->
                <div class="info-card">
                    <div class="info-details">
                        <h4>Scholarship Program</h4>
                        <p class="info-value"><?= htmlspecialchars($scholarship_name) ?></p>
                    </div>
                </div>

                <!-- Allowance -->
                <div class="info-card">
                    <div class="info-details">
                        <h4>Allowance</h4>
                        <p class="info-value">₱ <?= number_format($allowance, 2) ?></p>
                    </div>
                </div>

                <!-- GWA -->
                <div class="info-card">
                    <div class="info-details">
                        <h4>Current GWA</h4>
                        <p class="info-value"><?= htmlspecialchars($gwa) ?></p>
                    </div>
                </div>

                <!-- Year Level -->
                <div class="info-card">
                    <div class="info-details">
                        <h4>Year Level</h4>
                        <p class="info-value"><?= htmlspecialchars($year_level) ?></p>
                    </div>
                </div>

                <!-- Academic Status -->
                <div class="info-card">
                    <div class="info-details">
                        <h4>Academic Status</h4>
                        <p class="info-value <?= strtolower($academic_status) === 'active' ? 'status-active' : 'status-inactive' ?>">
                            <?= htmlspecialchars($academic_status) ?>
                        </p>
                    </div>
                </div>

                <!-- Flag Status -->
                <div class="info-card <?= $is_flagged ? 'flagged' : '' ?>">
                    <div class="info-details">
                        <h4>GWA Compliance</h4>
                        <p class="info-value <?= $is_flagged ? 'flag-warning' : 'flag-success' ?>">
                            <?= $is_flagged ? 'Below Requirement' : 'Meets Requirement' ?>
                        </p>
                        <?php if($is_flagged): ?>
                            <small class="flag-note">Please contact the scholarship office.</small>
                        <?php endif; ?>
                    </div>
                    <div class="info-icon"><?= $is_flagged ? '⚠️' : '✅' ?></div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            © 2026 PIT ScholarshipHub. All rights reserved.
        </div>
    </div>
</body>
</html>