<?php 
include __DIR__ . "/includes/conn.php";
?>

<html>
    <head>
        <title>Scholarship Portal</title>
        <link rel="stylesheet" href="assets/css/portal.css">
        <link rel="icon" href="assets/images/PIT LOGO.png">
    </head>
    <body>
        <div class="container">
            <!-- header -->
            <div class="header">
                <img src="assets/images/PIT LOGO.png" alt="PIT Logo">
                <p>Palompon Institute of Technology</p>
                <h1>PIT ScholarshipHub</h1>
            </div>

            <!-- welcome -->
            <div class="welcome">
                <h2>Welcome to PIT ScholarshipHub</h2>
                <p>A centralized platform for managing student scholarship records.</p>
            </div>

            <!-- role cards -->
            <div class="role-cards">
                <a href="admin/login.php" class="role-card admin">
                    <img src="assets/images/admin-icon.png" alt="Admin Icon">
                    <div class="role-name">Admin / Coordinator</div>
                    <div class="role-desc">Manage scholars, imports, exports</div>
                </a>
                <a href="staff/login.php" class="role-card staff">
                    <img src="assets/images/staff-icon.png" alt="Admin Icon">
                    <div class="role-name">Staff</div>
                    <div class="role-desc">View and edit scholar records</div>
                </a>
                <a href="student/login.php" class="role-card student">
                    <img src="assets/images/student-icon.png" alt="Admin Icon">
                    <div class="role-name">Scholar / Student</div>
                    <div class="role-desc">View your scholarship information</div>
                </a>
            </div>

            <!-- announcements -->
            <div class="announcements">
                <h2>Announcements</h2>
                
                <?php 
                $announcement_sql = "SELECT title, content, CAST(created_at AS DATE) AS created_at_date FROM announcements WHERE is_active = 1 ORDER BY created_at DESC LIMIT 5";
                $announcement_stmt = $conn->prepare($announcement_sql);
                $announcement_stmt->execute();
                $announcement_result = $announcement_stmt->get_result();

                if($announcement_result->num_rows > 0) :
                    while($row = $announcement_result->fetch_assoc()) : ?>
                        <div class="announcement-item">
                            <div class="title">
                                <?= htmlspecialchars($row['title']) ?>
                                <span class="date">📅 <?= htmlspecialchars($row['created_at_date']) ?></span>
                            </div>
                            <div class="content"><?= htmlspecialchars($row['content'], ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                    <?php endwhile;
                else: ?>
                    <p>No announcements available at this time.</p>
                <?php endif; ?>
            </div>
        </div>
    </body>
</html>