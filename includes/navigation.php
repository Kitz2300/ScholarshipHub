<?php
// includes/navigation.php
$current_page = basename($_SERVER['PHP_SELF']);

// Dynamic subtitle fallback if not explicitly defined in the parent page
$header_subtitle = isset($page_subtitle) ? $page_subtitle : 'Scholarship Management System';

// Fetch current logged-in user profile picture & details
$nav_user_id = $_SESSION['user_id'] ?? 0;
$nav_user_pic = '';
$nav_has_pic = false;
$nav_initials = 'A';

if ($nav_user_id > 0 && isset($conn)) {
    $nav_user_sql = "SELECT email, profile_picture FROM scholarship_users WHERE id = ?";
    $nav_user_stmt = $conn->prepare($nav_user_sql);
    if ($nav_user_stmt) {
        $nav_user_stmt->bind_param("i", $nav_user_id);
        $nav_user_stmt->execute();
        $nav_user_res = $nav_user_stmt->get_result();
        if ($nav_u_row = $nav_user_res->fetch_assoc()) {
            $raw_user_pic = trim($nav_u_row['profile_picture'] ?? '');

            if (!empty($raw_user_pic) && preg_match("~^https?://~i", $raw_user_pic)) {
                $nav_user_pic = $raw_user_pic;
                $nav_has_pic = true;
            } elseif (!empty($raw_user_pic) && file_exists(__DIR__ . '/../assets/images/profile_picture/' . $raw_user_pic)) {
                $nav_user_pic = '../assets/images/profile_picture/' . $raw_user_pic . '?v=' . time();
                $nav_has_pic = true;
            }

            $email_prefix = explode('@', $nav_u_row['email'])[0] ?? 'Admin';
            $nav_initials = strtoupper(substr($email_prefix, 0, 2));
        }
        $nav_user_stmt->close();
    }
}

// Helper function to match exact script or arrays of related sub-pages
function isActive($pages, $current_page) {
    if (is_array($pages)) {
        return in_array($current_page, $pages) ? 'active' : '';
    }
    return $current_page === $pages ? 'active' : '';
}

// Sub-page groups for active state highlight
$scholar_pages = [
    'scholar_list.php', 
    'add_scholar.php', 
    'edit_scholar.php', 
    'scholar_profile.php', 
    'academic_performance.php', 
    'add_academic_performance.php', 
    'edit_academic_performance.php'
];

$program_pages = [
    'scholarship_program.php', 
    'add_scholarship.php', 
    'edit_scholarship.php',
    'providers.php'
];

$announcement_pages = [
    'announcements.php', 
    'add_announcement.php', 
    'edit_announcement.php'
];

$management_pages = array_merge(
    $announcement_pages, 
    ['program_management.php', 'account_management.php', 'archive.php', 'audit_trail.php']
);
?>

<!-- Integrated Header -->
<div class="header">
    <div class="header-left">
        <img src="../assets/images/PIT%20LOGO.png" alt="PIT Logo">
        <div>
            <h1>PIT ScholarshipHub</h1>
            <p><?= htmlspecialchars($header_subtitle) ?></p>
        </div>
    </div>
    <div class="header-right">
        <!-- Logged-in Admin/Staff Profile Avatar -->
        <div style="display: flex; align-items: center; gap: 10px;">
            <?php if ($nav_has_pic): ?>
                <img src="<?= htmlspecialchars($nav_user_pic) ?>" alt="User Profile" 
                     style="width: 42px; height: 42px; border-radius: 50%; object-fit: cover; border: 2px solid #ffffff; box-shadow: 0 2px 5px rgba(0,0,0,0.2); cursor: pointer;" 
                     title="User Profile">
            <?php else: ?>
                <div style="width: 42px; height: 42px; border-radius: 50%; background: #2a6b37; color: #ffffff; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 16px; border: 2px solid #ffffff; box-shadow: 0 2px 5px rgba(0,0,0,0.2);">
                    <?= htmlspecialchars($nav_initials) ?>
                </div>
            <?php endif; ?>
            <span class="welcome-text">Welcome, <?= ($_SESSION['role'] ?? '') === 'admin' ? 'Admin!' : 'Staff!' ?></span>
        </div>

        <form action="../functions/logout.php" method="post" style="display: inline;">
            <button type="submit" class="logout-btn" onclick="return confirmAction(event, 'Are you sure you want to log out?', 'Logout', true)">Logout</button>
        </form>
    </div>
</div>

<!-- Navigation Bar -->
<nav class="nav-bar">
    <ul>
        <!-- Dashboard -->
        <li>
            <a href="dashboard.php" class="<?= isActive('dashboard.php', $current_page) ?>">Dashboard</a>
        </li>

        <!-- Scholar List -->
        <?php if(canAccessPage($conn, $_SESSION['user_id'], 'scholars')) : ?>
            <li>
                <a href="scholar_list.php" class="<?= isActive($scholar_pages, $current_page) ?>">Scholar List</a>
            </li>
        <?php endif; ?>

        <!-- Scholarship Programs -->
        <?php if(canAccessPage($conn, $_SESSION['user_id'], 'scholarship_programs')) : ?>
            <li>
                <a href="scholarship_program.php" class="<?= isActive($program_pages, $current_page) ?>">Scholarship Programs</a>
            </li>
        <?php endif; ?>

        <!-- Import Scholar Records -->
        <?php if(hasPermission($conn, $_SESSION['user_id'], 'import_export', 'import')) : ?>
            <li>
                <a href="upload_excel.php" class="<?= isActive('upload_excel.php', $current_page) ?>">Import Scholar Records</a>
            </li>
        <?php endif; ?>

        <!-- Export Scholar Records -->
        <?php if(hasPermission($conn, $_SESSION['user_id'], 'import_export', 'export')) : ?>
            <li>
                <a href="export_scholars.php" class="<?= isActive('export_scholars.php', $current_page) ?>">Export Scholar Records</a>
            </li>
        <?php endif; ?>

        <!-- Management Dropdown -->
        <?php 
        $can_see_management = canAccessPage($conn, $_SESSION['user_id'], 'announcements') || 
                              canAccessPage($conn, $_SESSION['user_id'], 'program_management') || 
                              ($_SESSION['role'] ?? '') === 'admin';
        if($can_see_management) : 
        ?>
            <li class="dropdown">
                <a href="#" class="dropdown-toggle <?= isActive($management_pages, $current_page) ?>">
                    Management <div class="arrow-down">▼</div>
                </a>
                <ul class="dropdown-menu">
                    <!-- Announcements -->
                    <?php if(canAccessPage($conn, $_SESSION['user_id'], 'announcements')) : ?>
                        <li>
                            <a href="announcements.php" class="<?= isActive($announcement_pages, $current_page) ?>">Announcements</a>
                        </li>
                    <?php endif; ?>

                    <!-- Program & College Management -->
                    <?php if(canAccessPage($conn, $_SESSION['user_id'], 'program_management')) : ?>
                        <li>
                            <a href="program_management.php" class="<?= isActive('program_management.php', $current_page) ?>">Programs & Colleges</a>
                        </li>
                    <?php endif; ?>

                    <!-- Account Management -->
                    <?php if(($_SESSION['role'] ?? '') === 'admin') : ?>
                        <li>
                            <a href="account_management.php" class="<?= isActive('account_management.php', $current_page) ?>">Accounts</a>
                        </li>
                    <?php endif; ?>

                    <!-- Archives -->
                    <?php if(($_SESSION['role'] ?? '') === 'admin') : ?>
                        <li>
                            <a href="archive.php" class="<?= isActive('archive.php', $current_page) ?>">Archives</a>
                        </li>
                    <?php endif; ?>

                    <!-- Audit Trail -->
                    <?php if(($_SESSION['role'] ?? '') === 'admin') : ?>
                        <li>
                            <a href="audit_trail.php" class="<?= isActive('audit_trail.php', $current_page) ?>">Audit Trail</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </li>
        <?php endif; ?>
    </ul>
</nav>