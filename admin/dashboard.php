<?php 
include __DIR__ . '/../includes/conn.php';
include __DIR__ . '/../includes/check_permission.php';
session_start();

include __DIR__ . "/../includes/school_year_helper.php";

if(isset($_POST['set_school_year'])) {
    setSelectedSchoolYear($_POST['school_year']);
    header("Location: dashboard.php");
    exit();
}

$selected_year = getSelectedSchoolYear();
$available_years = getAvailableSchoolYears($conn);

// If the selected year has no data, pick the first available year
if (!empty($available_years) && !in_array($selected_year, $available_years)) {
    $selected_year = $available_years[0];
    setSelectedSchoolYear($selected_year);
    header("Location: dashboard.php");
    exit();
}

// sql query for total count of scholars
$scholars_sql = "SELECT id, COUNT(*) AS scholar_count FROM scholar_info WHERE scholar_info.is_archived = 0 AND school_year = ?";
$scholars_stmt = $conn->prepare($scholars_sql);
$scholars_stmt->bind_param('s', $selected_year);
$scholars_stmt->execute();
$scholars_result = $scholars_stmt->get_result();

// sql query for total count of scholar per scholarship program
$scholarship_sql = "SELECT 
                        scholarship_program.id AS program_id,
                        scholarship_program.name AS program_name,
                        scholarship_program.code AS program_code,
                        COUNT(scholar_info.id) AS scholar_count
                    FROM 
                        scholarship_program
                    LEFT JOIN
                        scholar_info ON scholar_info.scholarship_program_id = scholarship_program.id
                        AND scholar_info.is_archived = 0
                        AND scholar_info.school_year = ?
                    WHERE
                        scholarship_program.is_archived = 0 
                        AND scholarship_program.is_active = 1 
                        AND scholarship_program.school_year = ?
                    GROUP BY
                        scholarship_program.id, scholarship_program.name
                    ORDER BY
                        scholar_count DESC
                    ";
$scholarship_stmt = $conn->prepare($scholarship_sql);
$scholarship_stmt->bind_param('ss', $selected_year, $selected_year);
$scholarship_stmt->execute();
$scholarship_result = $scholarship_stmt->get_result();

// sql query for total count of scholars per status (Enrolled, Not Enrolled, Graduated, Dropped, Transferred, Leave of Abscence)
$status_sql = " SELECT 
                    statuses.academic_status, 
                    COALESCE(COUNT(scholar_info.id), 0) AS status_count
                FROM (
                    SELECT 'Enrolled' AS academic_status
                    UNION SELECT 'Not Enrolled'
                    UNION SELECT 'Graduated'
                    UNION SELECT 'Dropped'
                    UNION SELECT 'Transferred'
                    UNION SELECT 'Leave of Absence'
                ) AS statuses
                LEFT JOIN 
                    scholar_info ON scholar_info.academic_status = statuses.academic_status
                    AND scholar_info.school_year = ? AND scholar_info.is_archived = 0
                GROUP BY 
                    statuses.academic_status
                ORDER BY
                    FIELD(statuses.academic_status, 'Enrolled', 'Not Enrolled', 'Graduated', 'Dropped', 'Transferred', 'Leave of Absence')
";
$status_stmt = $conn->prepare($status_sql);
$status_stmt->bind_param('s', $selected_year);
$status_stmt->execute();
$status_result = $status_stmt->get_result();

// ─── Flagged Scholars Count ───
$flagged_sql = "SELECT COUNT(DISTINCT si.id) AS flagged_count
                FROM scholar_info si
                INNER JOIN scholarship_program sp ON si.scholarship_program_id = sp.id
                INNER JOIN (
                    SELECT scholar_id, MAX(CONCAT(school_year, '-', LPAD(semester, 2, '0'))) AS max_date
                    FROM academic_performance
                    GROUP BY scholar_id
                ) latest ON latest.scholar_id = si.id
                INNER JOIN academic_performance ap ON ap.scholar_id = si.id 
                    AND CONCAT(ap.school_year, '-', LPAD(ap.semester, 2, '0')) = latest.max_date
                WHERE si.is_archived = 0
                  AND si.school_year = ?
                  AND sp.min_gwa_req > 0
                  AND ap.gwa > sp.min_gwa_req";
$flagged_stmt = $conn->prepare($flagged_sql);
$flagged_stmt->bind_param('s', $selected_year);
$flagged_stmt->execute();
$flagged_result = $flagged_stmt->get_result();
$flagged_count = 0;
if ($row = $flagged_result->fetch_assoc()) {
    $flagged_count = $row['flagged_count'];
}

// Get total scholar count
$total_scholars = 0;
if($row = $scholars_result->fetch_assoc()) {
    $total_scholars = $row['scholar_count'];
}

// Store scholarship data
$scholarship_data = [];
while($row = $scholarship_result->fetch_assoc()) {
    $scholarship_data[] = $row;
}

// Store status data
$status_data = [];
while($row = $status_result->fetch_assoc()) {
    $status_data[] = $row;
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard - PIT ScholarshipHub</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="icon" href="../assets/images/PIT%20LOGO.png">
</head>
<body>
    <?php 
    // fail session messages
    if(isset($_SESSION['fail'])) {
        echo "<script>alert('" . $_SESSION['fail'] . "')</script>";
        unset($_SESSION['fail']);
    }
    ?>
    <?php if(!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'staff')) : ?>
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
                        <p>Dashboard</p>
                    </div>
                </div>
                <div class="header-right">
                    <span class="welcome-text">Welcome, <?= $_SESSION['role'] == 'admin' ? 'Admin!' : 'Staff!' ?></span>
                    <form action="../functions/logout.php" method="post" style="display: inline;">
                        <button type="submit" class="logout-btn" onclick="return confirm('Logout?')">Logout</button>
                    </form>
                </div>
            </div>

            <!-- Navigation -->
            <nav class="nav-bar">
                <ul>
                    <!-- Dashboard -->
                    <li><a href="dashboard.php" class="active">Dashboard</a></li>

                    <!-- Records Dropdown -->
                    <?php if(canAccessPage($conn, $_SESSION['user_id'], 'scholars') || canAccessPage($conn, $_SESSION['user_id'], 'scholarship_programs')) : ?>
                        <li class="dropdown">
                            <a href="#" class="dropdown-toggle">Records <div class="arrow-down">▼</div></a>
                            <ul class="dropdown-menu">
                                <?php if(canAccessPage($conn, $_SESSION['user_id'], 'scholars')) : ?>
                                    <li><a href="scholar_list.php">Scholars</a></li>
                                <?php endif; ?>
                                <?php if(canAccessPage($conn, $_SESSION['user_id'], 'scholarship_programs')) : ?>
                                    <li><a href="scholarship_program.php">Scholarship Programs</a></li>
                                <?php endif; ?>
                                <?php if($_SESSION['role'] == 'admin') : ?>
                                    <li><a href="archive.php">Archives</a></li>
                                <?php endif; ?>
                            </ul>
                        </li>
                    <?php endif; ?>

                    <!-- Import/Export Dropdown -->
                    <?php //if(canAccessPage($conn, $_SESSION['user_id'], 'import_export')) : ?>
                    <?php if(hasPermission($conn, $_SESSION['user_id'], 'import_export', 'import') || hasPermission($conn, $_SESSION['user_id'], 'import_export', 'export')) : ?>
                        <li class="dropdown">
                            <a href="#" class="dropdown-toggle">Import / Export <div class="arrow-down">▼</div></a>
                            <ul class="dropdown-menu">
                                <?php if(hasPermission($conn, $_SESSION['user_id'], 'import_export', 'import')) : ?>
                                    <li><a href="upload_excel.php">Import Excel</a></li>
                                <?php endif; ?>
                                <?php if(hasPermission($conn, $_SESSION['user_id'], 'import_export', 'export')) : ?>
                                    <li><a href="export_scholars.php">Export Excel</a></li>
                                <?php endif; ?>
                            </ul>
                        </li>
                    <?php endif; ?>
                    
                    <!-- Announcements -->
                    <?php if(canAccessPage($conn, $_SESSION['user_id'], 'announcements')) : ?>
                        <li><a href="announcements.php">Announcements</a></li>
                    <?php endif; ?>

                    <!-- Program / College Management -->
                    <?php if(canAccessPage($conn, $_SESSION['user_id'], 'program_management')) : ?>
                        <li><a href="program_management.php">Program & College Management</a></li>
                    <?php endif; ?>
                    
                    <!-- Account Management -->
                    <?php if($_SESSION['role'] == "admin") : ?>
                        <li><a href="account_management.php">Account Management</a></li>
                    <?php endif; ?>
                </ul>
            </nav>

            <!-- Dashboard Content -->
            <div class="dashboard-content">
                <!-- Welcome Section -->
                <div class="welcome-card">
                    <div class="welcome-text">
                        <h2>Dashboard Overview</h2>
                        <p>Here's a summary of your scholarship records.</p>
                    </div>

                    <!-- School Year Selector -->
                    <form action="<?= $_SERVER['PHP_SELF'] ?>" method="POST" class="school-year-selector">
                        <label for="school_year"><strong>School Year:</strong></label>
                        <select name="school_year" id="school_year">
                            <?php if(!empty($available_years)) : ?>
                                <?php foreach($available_years as $year) : ?>
                                    <option value="<?= $year ?>" <?= $year == $selected_year ? 'selected' : '' ?>>
                                        <?= $year ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <option value="">No School Year</option>
                            <?php endif; ?>
                        </select>
                        <button type="submit" name="set_school_year" class="btn btn-sm btn-primary">Apply</button>
                    </form>
                </div>

                <!-- Stats Cards -->
                <div class="stats-grid">
                    <!-- Total Scholars -->
                    <a href="scholar_list.php?search=&course_filter=0&college_filter=0&scholarship_filter=0&academic_status=&school_year_filter=<?= $selected_year ?>&filter=">
                        <div class="stat-card total">
                            <div class="stat-info">
                                <h3>Total Scholars</h3> 
                                <p class="stat-number"><?= htmlspecialchars($total_scholars) ?></p>
                            </div>
                        </div>
                    </a>

                    <!-- Enrolled Scholars -->
                    <?php   
                    $enrolled_count = 0;
                    foreach($status_data as $status) {
                        if($status['academic_status'] === 'Enrolled') {
                            $enrolled_count = $status['status_count'];
                            break;
                        }
                    }
                    ?>
                    <a href="scholar_list.php?search=&course_filter=0&college_filter=0&scholarship_filter=0&academic_status=Enrolled&school_year_filter=<?= $selected_year ?>&filter=">
                        <div class="stat-card active">
                            <div class="stat-info">
                                <h3>Enrolled Scholars</h3>
                                <p class="stat-number"><?= htmlspecialchars($enrolled_count) ?></p>
                            </div>
                        </div>
                    </a>

                    <!-- Scholarship Programs Count -->
                    <a href="scholarship_program.php">
                        <div class="stat-card programs">
                            <div class="stat-info">
                                <h3>Scholarship Programs</h3>
                                <p class="stat-number"><?= htmlspecialchars(count($scholarship_data)) ?></p>
                            </div>
                        </div>
                    </a>

                    <!-- Flagged Scholars -->
                    <a href="scholar_list.php?search=&course_filter=0&college_filter=0&scholarship_filter=0&academic_status=&flagged_filter=1&school_year_filter=<?= $selected_year ?>&filter=">
                        <div class="stat-card flagged">
                            <div class="stat-info">
                                <h3>Flagged Scholars</h3>
                                <p class="stat-number"><?= htmlspecialchars($flagged_count) ?></p>
                                <span class="stat-sub">Needs attention</span>
                            </div>
                            <div class="stat-info">
                             <img class="warning-img" src="../assets/images/warning.png">
                            </div>
                        </div>
                    </a>
                </div>

                <!-- Scholarship Programs Breakdown Progress Bar -->
                <div class="section-card">
                    <h3>Scholars per Scholarship Program</h3>
                    <div class="program-list">
                        <?php if(!empty($scholarship_data)) : ?>
                            <?php foreach($scholarship_data as $program) : 
                                $percentage = ($total_scholars > 0) ? round(($program['scholar_count'] / $total_scholars) * 100) : 0;
                                // Ensure a minimum width so the label always fits
                                $bar_width = ($percentage > 0) ? max($percentage, 2) : 0; // even 1% becomes at least 8%
                            ?>
                                <div class="program-item">
                                    <div class="program-name" title="<?= htmlspecialchars($program['program_name']) ?>">
                                        <?= htmlspecialchars($program['program_name']) ?>
                                    </div>
                                    <div class="program-bar-wrapper">
                                        <div class="program-bar">
                                            <?php if($percentage > 0) : ?>
                                                <div class="program-bar-fill" style="width: <?= $bar_width ?>%;">
                                                    <span class="program-bar-label"><?= $percentage ?>%</span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="program-count"><?= htmlspecialchars($program['scholar_count']) ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <div class="program-item">
                                <span class="program-name">No scholarship programs found.</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Scholarship Programs Breakdown Graph -->
                <!-- <div class="section-card">
                    <h3>Scholars per Scholarship Program</h3>
                    <?php if(!empty($scholarship_data)) : ?>
                        <canvas id="programChart" 
                                data-labels='<?= json_encode(array_column($scholarship_data, 'program_name'), JSON_HEX_APOS) ?>' 
                                data-values='<?= json_encode(array_column($scholarship_data, 'scholar_count')) ?>'>
                        </canvas>
                    <?php else : ?>
                        <p id="no-chart-data">No scholarship programs found.</p>
                    <?php endif; ?>
                </div> -->


                <!-- Status Breakdown -->
                <div class="section-card">
                    <h3>Scholars by Status</h3>
                    <div class="status-grid">
                        <?php if(!empty($status_data)) :?>
                            <?php foreach($status_data as $status) : ?>
                                <a href="scholar_list.php?search=&course_filter=0&college_filter=0&scholarship_filter=0&academic_status=<?= $status['academic_status'] ?>&school_year_filter=<?= $selected_year ?>&filter=">
                                    <div class="status-item">
                                        <span class="status-label"><strong><?= htmlspecialchars($status['academic_status']) ?></strong></span>
                                        <span class="status-count"><?= htmlspecialchars($status['status_count']) ?></span>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <span class="program-name">No Scholars</span>
                        <?php endif;?>
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