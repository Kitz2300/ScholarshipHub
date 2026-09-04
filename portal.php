<?php 
include __DIR__ . "/includes/conn.php";

// Helper function to turn plain URLs into clickable links safely
function makeLinksClickable($text) {
    // 1. Escape HTML first to prevent XSS
    $safe_text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

    // 2. Regex to detect HTTP/HTTPS URLs
    $pattern = '/(https?:\/\/[^\s<]+)/i';

    // 3. Wrap URLs in <a> tags
    $replacement = '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>';

    return preg_replace($pattern, $replacement, $safe_text);
}
?>

<!DOCTYPE html>
<html>
    <head>
        <title>Scholarship Portal</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
                    <div class="role-name">Scholarship Coordinator</div>
                    <div class="role-desc">Manage scholars, imports, exports</div>
                </a>
                <a href="staff/login.php" class="role-card staff">
                    <img src="assets/images/staff-icon.png" alt="Staff Icon">
                    <div class="role-name">Staff</div>
                    <div class="role-desc">View and edit scholar records</div>
                </a>
                <a href="student/login.php" class="role-card student">
                    <img src="assets/images/student-icon.png" alt="Student Icon">
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

                $announcements = [];
                while ($row = $announcement_result->fetch_assoc()) {
                    $announcements[] = $row;
                }
                ?>

                <?php if (!empty($announcements)) : ?>
                    <div class="announcements-layout">
                        <!-- Left Column: Titles List -->
                        <div class="announcements-list">
                            <?php foreach ($announcements as $index => $item) : ?>
                                <div class="announcement-title-item <?= $index === 0 ? 'active' : '' ?>" 
                                     onclick="selectAnnouncement(<?= $index ?>)" 
                                     id="title-item-<?= $index ?>">
                                    <div class="title-text"><?= htmlspecialchars($item['title']) ?></div>
                                    <div class="title-date">Posted: <?= htmlspecialchars($item['created_at_date']) ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Right Column: Content Viewer -->
                        <div class="announcement-content-viewer">
                            <?php foreach ($announcements as $index => $item) : ?>
                                <div class="announcement-detail <?= $index === 0 ? 'active' : '' ?>" 
                                     id="content-item-<?= $index ?>">
                                    <h3><?= htmlspecialchars($item['title']) ?></h3>
                                    <span class="detail-date">Date Posted: <?= htmlspecialchars($item['created_at_date']) ?></span>
                                    <hr class="detail-divider">
                                    <div class="detail-content"><?= makeLinksClickable($item['content']) ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php else : ?>
                    <p class="no-data">No announcements available at this time.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Inline Switcher Script -->
        <script>
            function selectAnnouncement(index) {
                // Remove active status from all titles & contents
                document.querySelectorAll('.announcement-title-item').forEach(el => el.classList.remove('active'));
                document.querySelectorAll('.announcement-detail').forEach(el => el.classList.remove('active'));

                // Activate selected title & corresponding content
                const selectedTitle = document.getElementById('title-item-' + index);
                const selectedContent = document.getElementById('content-item-' + index);

                if (selectedTitle && selectedContent) {
                    selectedTitle.classList.add('active');
                    selectedContent.classList.add('active');
                }
            }
        </script>
    </body>
</html>