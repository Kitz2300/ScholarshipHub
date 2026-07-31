<?php 
include '../includes/conn.php';
session_start();

// sql query for total count of scholars
$scholars_sql = "SELECT id, COUNT(*) AS scholar_count FROM scholar_info";
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
                    GROUP BY
                        scholarship_program.id, scholarship_program.name
                    ";
$scholarship_stmt = $conn->prepare($scholarship_sql);
$scholarship_stmt->execute();
$scholarship_result = $scholarship_stmt->get_result();

// sql query for total count of scholars per status (active, inactive, dropped, graduated)
$status_sql = " SELECT
                    id,
                    academic_status, 
                    COUNT(*) AS status_count 
                FROM 
                    scholar_info 
                GROUP BY 
                    academic_status";
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
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="icon" href="../assets/images/PIT%20LOGO.png">
</head>
<body>
    <?php if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') : ?>
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
                    <span class="welcome-text">Welcome, Admin!</span>
                    <form action="../functions/logout.php" method="post" style="display: inline;">
                        <button type="submit" class="logout-btn" onclick="return confirm('Logout?')">Logout</button>
                    </form>
                </div>
            </div>

            <!-- Navigation -->
            <nav class="nav-bar">
                <ul>
                    <li><a href="dashboard.php" class="active">Dashboard</a></li>
                    <li><a href="scholar_list.php">Scholars</a></li>
                    <li><a href="announcements.php">Announcements</a></li>
                    <li><a href="scholarship_program.php">Scholarship Programs</a></li>
                    <li><a href="upload_excel.php">Import Excel</a></li>
                    <li><a href="export_scholars.php">Export Excel</a></li>
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
                    <div class="stat-card total">
                        <div class="stat-icon">🎓</div>
                        <div class="stat-info">
                            <h3>Total Scholars</h3>
                            <p class="stat-number"><?= htmlspecialchars($total_scholars) ?></p>
                        </div>
                    </div>

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
                    <div class="stat-card active">
                        <div class="stat-icon">✅</div>
                        <div class="stat-info">
                            <h3>Active Scholars</h3>
                            <p class="stat-number"><?= htmlspecialchars($active_count) ?></p>
                        </div>
                    </div>

                    <!-- Scholarship Programs Count -->
                    <div class="stat-card programs">
                        <div class="stat-icon">📚</div>
                        <div class="stat-info">
                            <h3>Programs</h3>
                            <p class="stat-number"><?= htmlspecialchars(count($scholarship_data)) ?></p>
                        </div>
                    </div>

                    <!-- Flagged Scholars (Optional - if you have flagging) -->
                    <div class="stat-card flagged">
                        <div class="stat-icon">⚠️</div>
                        <div class="stat-info">
                            <h3>Flagged Scholars</h3>
                            <p class="stat-number">0</p>
                            <span class="stat-sub">Needs attention</span>
                        </div>
                    </div>
                </div>

                <!-- Scholarship Programs Breakdown -->
                <div class="section-card">
                    <h3>Scholars per Scholarship Program</h3>
                    <div class="program-list">
                        <?php if(!empty($scholarship_data)) : ?>
                            <?php foreach($scholarship_data as $program) : ?>
                                <div class="program-item">
                                    <span class="program-name"><?= htmlspecialchars($program['program_name']) ?></span>
                                    <div class="program-bar">
                                        <div class="program-bar-fill" style="width: <?= ($total_scholars > 0) ? (($program['scholar_count'] / $total_scholars) * 100) : 0 ?>%;"></div>
                                    </div>
                                    <span class="program-count"><?= htmlspecialchars($program['scholar_count']) ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <div class="program-item">
                                <span class="program-name">No Scholars</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

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