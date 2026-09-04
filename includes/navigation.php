<?php
// includes/navbar.php
$current_page = basename($_SERVER['PHP_SELF']);

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
    ['program_management.php', 'account_management.php']
);
?>

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
                              $_SESSION['role'] === 'admin';
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
                    <?php if($_SESSION['role'] === 'admin') : ?>
                        <li>
                            <a href="account_management.php" class="<?= isActive('account_management.php', $current_page) ?>">Accounts</a>
                        </li>
                    <?php endif; ?>

                    <!-- Archives -->
                    <?php if($_SESSION['role'] === 'admin') : ?>
                        <li>
                            <a href="archive.php" class="<?= isActive('archive.php', $current_page) ?>">Archives</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </li>
        <?php endif; ?>
    </ul>
</nav>