<?php 
include '../includes/conn.php';
session_start();

// sql query for total count of scholars
$scholars_sql = "SELECT id, COUNT(*) AS scholar_count FROM scholar_info WHERE scholar_info.is_archived = 0";
$scholars_stmt = $conn->prepare($scholars_sql);
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
                    WHERE
                        (scholarship_program.is_archived = 0 AND scholarship_program.is_active = 1) OR scholar_info.is_archived = 0
                    GROUP BY
                        scholarship_program.id, scholarship_program.name
                    -- HAVING
                    --     COUNT(scholar_info.id) > 0  removes scholarships with no scholars
                    ";
$scholarship_stmt = $conn->prepare($scholarship_sql);
$scholarship_stmt->execute();
$scholarship_result = $scholarship_stmt->get_result();

// sql query for total count of scholars per status (active, inactive, dropped, graduated)
$status_sql = "
    SELECT statuses.academic_status, COALESCE(COUNT(scholar_info.id), 0) AS status_count
    FROM (
        SELECT 'Active' AS academic_status
        UNION SELECT 'Graduated'
        UNION SELECT 'Dropped'
        UNION SELECT 'Transferred'
        UNION SELECT 'Inactive'
    ) AS statuses
    LEFT JOIN scholar_info ON scholar_info.academic_status = statuses.academic_status
    GROUP BY statuses.academic_status
";
$status_stmt = $conn->prepare($status_sql);
$status_stmt->execute();
$status_result = $status_stmt->get_result();

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
                        <p>Admin Dashboard</p>
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
                    <li class="dropdown">
                        <a href="#" class="dropdown-toggle">Records <div class="arrow-down">▼</div></a>
                        <ul class="dropdown-menu">
                            <li><a href="scholar_list.php">Scholars</a></li>
                            <li><a href="scholarship_program.php">Scholarship Programs</a></li>
                            <li><a href="archive.php">Archives</a></li>
                        </ul>
                    </li>

                    <!-- Import/Export Dropdown -->
                    <li class="dropdown">
                        <a href="#" class="dropdown-toggle">Import / Export <div class="arrow-down">▼</div></a>
                        <ul class="dropdown-menu">
                            <li><a href="upload_excel.php">Import Excel</a></li>
                            <li><a href="export_scholars.php">Export Excel</a></li>
                        </ul>
                    </li>
                    
                    <!-- Announcements -->
                    <li><a href="announcements.php">Announcements</a></li>

                    <!-- Program / College Management -->
                    <li><a href="program_management.php">Program/College Management</a></li>
                </ul>
            </nav>

            <!-- Dashboard Content -->
            <div class="dashboard-content">
                <!-- Welcome Section -->
                <div class="welcome-card">
                    <h2>Dashboard Overview</h2>
                    <p>Here's a summary of your scholarship records.</p>
                </div>

                <!-- Stats Cards -->
                <div class="stats-grid">
                    <!-- Total Scholars -->
                    <a href="scholar_list.php">
                        <div class="stat-card total">
                            <div class="stat-info">
                                <h3>Total Scholars</h3> 
                                <p class="stat-number"><?= htmlspecialchars($total_scholars) ?></p>
                            </div>
                        </div>
                    </a>

                    <!-- Active Scholars -->
                    <?php 
                    $active_count = 0;
                    foreach($status_data as $status) {
                        if(strtolower($status['academic_status']) === 'active') {
                            $active_count = $status['status_count'];
                            break;
                        }
                    }
                    ?>
                    <a href="scholar_list.php?search=&course_filter=0&college_filter=0&scholarship_filter=0&academic_status=Active&filter=">
                        <div class="stat-card active">
                            <div class="stat-info">
                                <h3>Active Scholars</h3>
                                <p class="stat-number"><?= htmlspecialchars($active_count) ?></p>
                            </div>
                        </div>
                    </a>

                    <!-- Scholarship Programs Count -->
                    <a href="scholarship_program.php">
                        <div class="stat-card programs">
                            <div class="stat-info">
                                <h3>Programs</h3>
                                <p class="stat-number"><?= htmlspecialchars(count($scholarship_data)) ?></p>
                            </div>
                        </div>
                    </a>

                    <!-- Flagged Scholars (Optional - if you have flagging) -->
                    <div class="stat-card flagged">
                        <div class="stat-info">
                            <h3>Flagged Scholars</h3>
                            <p class="stat-number">0</p>
                            <span class="stat-sub">Needs attention</span>
                        </div>
                    </div>
                </div>

                <!-- Scholarship Programs Breakdown Progress Bar -->
                <div class="section-card">
                    <h3>Scholars per Scholarship Program</h3>
                    <div class="program-list">
                        <?php if(!empty($scholarship_data)) : ?>
                            <?php foreach($scholarship_data as $program) : 
                                $percentage = ($total_scholars > 0) ? round(($program['scholar_count'] / $total_scholars) * 100) : 0;
                            ?>
                                <div class="program-item">
                                    <div class="program-name" title="<?= htmlspecialchars($program['program_name']) ?>">
                                        <?= htmlspecialchars($program['program_name']) ?>
                                    </div>
                                    <div class="program-bar-wrapper">
                                        <div class="program-bar">
                                            <?php if($percentage > 0) : ?>
                                            <div class="program-bar-fill" style="width: <?= $percentage ?>%;">
                                                <?php if($percentage > 15) : ?>
                                                    <span class="program-bar-label"><?= $percentage ?>%</span>
                                                <?php endif; ?>
                                            </div>
                                            <?php if($percentage <= 15) : ?>
                                                <span class="program-bar-label-outside"><?= $percentage ?>%</span>
                                            <?php endif; ?>
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
                                <div class="status-item">
                                    <span class="status-label"><?= htmlspecialchars($status['academic_status']) ?></span>
                                    <span class="status-count"><?= htmlspecialchars($status['status_count']) ?></span>
                                </div>
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