<?php 
include __DIR__ . "/includes/conn.php";
session_start();

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
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Scholarship Portal - PIT ScholarshipHub</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="stylesheet" href="assets/css/portal.css?v=<?= filemtime(__DIR__ . '/assets/css/portal.css') ?>">
        <link rel="stylesheet" href="assets/css/modals.css">
        <link rel="icon" href="assets/images/PIT LOGO.png">
    </head>
    <?php include __DIR__ . "/includes/modal_alerts.php"; ?>
    <body>
        <div class="container">
            <!-- hero & header section -->
            <div class="portal-hero">
                <div class="header">
                    <div class="header-logo-wrapper">
                        <img src="assets/images/PIT LOGO.png" alt="PIT Logo">
                    </div>
                    <p>Palompon Institute of Technology</p>
                    <h1>PIT ScholarshipHub</h1>
                </div>

                <div class="welcome">
                    <h2>Welcome to PIT ScholarshipHub</h2>
                    <p>A centralized platform for managing student scholarship records.</p>
                </div>
            </div>

            <!-- role cards -->
            <div class="role-cards">
                <a href="admin/login.php" class="role-card admin">
                    <div class="icon-badge">
                        <img src="assets/images/admin-icon.png" alt="Admin Icon">
                    </div>
                    <div class="role-name">Scholarship Coordinator</div>
                    <div class="role-desc">Manage scholars, imports, and system exports</div>
                    <div class="role-action-btn">Log In as Coordinator &rarr;</div>
                </a>

                <a href="staff/login.php" class="role-card staff">
                    <div class="icon-badge">
                        <img src="assets/images/staff-icon.png" alt="Staff Icon">
                    </div>
                    <div class="role-name">Staff</div>
                    <div class="role-desc">View and update scholar records and details</div>
                    <div class="role-action-btn">Log In as Staff &rarr;</div>
                </a>

                <a href="student/login.php" class="role-card student">
                    <div class="icon-badge">
                        <img src="assets/images/student-icon.png" alt="Student Icon">
                    </div>
                    <div class="role-name">Scholar / Student</div>
                    <div class="role-desc">View your active scholarship status and profile</div>
                    <div class="role-action-btn">Log In as Scholar &rarr;</div>
                </a>
            </div>

            <!-- announcements section -->
            <div class="announcements">
                <div class="announcements-header">
                    <h2>Announcements</h2>
                </div>
                
                <?php 
                $announcement_sql = "SELECT 
                                        announcements.title, 
                                        announcements.content, 
                                        CAST(announcements.created_at AS DATE) AS created_at_date,
                                        sp.name AS scholarship_program_name
                                     FROM announcements 
                                     LEFT JOIN scholarship_program sp ON announcements.scholarship_program_id = sp.id
                                     WHERE announcements.is_active = 1 
                                     ORDER BY announcements.created_at DESC 
                                     LIMIT 5";
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
                            <?php foreach ($announcements as $index => $item) : 
                                $addressed_to = !empty($item['scholarship_program_name']) ? $item['scholarship_program_name'] : "All Scholarship Programs";
                            ?>
                                <div class="announcement-title-item <?= $index === 0 ? 'active' : '' ?>" 
                                     onclick="selectAnnouncement(<?= $index ?>)" 
                                     id="title-item-<?= $index ?>">
                                    <div class="title-text"><?= htmlspecialchars($item['title']) ?></div>
                                    <div class="title-date">Posted: <?= date('M d, Y', strtotime($item['created_at_date'])) ?> | Addressed To: <?= htmlspecialchars($addressed_to) ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Right Column: Content Viewer -->
                        <div class="announcement-content-viewer">
                            <?php foreach ($announcements as $index => $item) : 
                                $addressed_to = !empty($item['scholarship_program_name']) ? $item['scholarship_program_name'] : "All Scholarship Programs";
                            ?>
                                <div class="announcement-detail <?= $index === 0 ? 'active' : '' ?>" 
                                     id="content-item-<?= $index ?>">
                                    <h3><?= htmlspecialchars($item['title']) ?></h3>
                                    <span class="detail-date-badge">Date Posted: <?= date('F d, Y', strtotime($item['created_at_date'])) ?> | Addressed To: <?= htmlspecialchars($addressed_to) ?></span>
                                    <hr class="detail-divider">
                                    <div class="detail-content"><?= makeLinksClickable($item['content']) ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php else : ?>
                    <p class="no-data">No active announcements available at this time.</p>
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