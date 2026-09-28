<?php 
include __DIR__ . "/../includes/conn.php";
include __DIR__ . "/../includes/check_permission.php";
session_start();

// Ensure user is logged in
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'staff')) {
    $_SESSION['fail'] = "You are not logged in.";
    header("Location: login.php");
    exit();
}

if ($_SESSION['role'] !== 'admin') {
    checkPageAccess($conn, $_SESSION['user_id'], 'announcements');
}

// Helper function to turn plain URLs into clickable links safely
if (!function_exists('makeLinksClickable')) {
    function makeLinksClickable($text) {
        $safe_text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        $pattern = '/(https?:\/\/[^\s<]+)/i';
        $replacement = '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>';
        return preg_replace($pattern, $replacement, $safe_text);
    }
}

// Query all announcements with addressed scholarship program
$announcement_sql = "SELECT
                        announcements.id AS announcement_id,
                        title, 
                        content, 
                        announcements.is_active,
                        created_at,
                        scholarship_users.given_name, 
                        scholarship_users.middle_name, 
                        scholarship_users.last_name,
                        scholarship_users.role,
                        sp.name AS scholarship_program_name
                    FROM 
                        announcements 
                    INNER JOIN 
                        scholarship_users ON announcements.user_id = scholarship_users.id
                    LEFT JOIN
                        scholarship_program sp ON announcements.scholarship_program_id = sp.id
                    ORDER BY
                        is_active DESC,
                        created_at DESC
                    ";

$announcement_stmt = $conn->prepare($announcement_sql);
$announcement_stmt->execute();
$announcement_result = $announcement_stmt->get_result();

// Toggle status function
if (isset($_POST['change_status']) && isset($_POST['announcement_id'])) {
    $announcement_id = (int) $_POST['announcement_id'];
    $change_sql = "UPDATE announcements SET is_active = NOT is_active WHERE id = ?";
    $change_stmt = $conn->prepare($change_sql);
    $change_stmt->bind_param("i", $announcement_id);
    
    if ($change_stmt->execute()) {
        $_SESSION['success'] = "Announcement status updated successfully!";
    } else {
        $_SESSION['fail'] = "Failed to change announcement status.";
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Delete announcement function
if (isset($_POST['delete']) && isset($_POST['announcement_id'])) {
    $announcement_id = (int) $_POST['announcement_id'];
    $delete_sql = "DELETE FROM announcements WHERE id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param('i', $announcement_id);
    
    if ($delete_stmt->execute()) { 
        $_SESSION['success'] = "Announcement deleted successfully!";
    } else {
        $_SESSION['fail'] = "Failed to delete announcement.";
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Announcements - PIT ScholarshipHub</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/announcements.css?v=<?= filemtime('../assets/css/announcements.css') ?>">
    <link rel="icon" href="../assets/images/PIT%20LOGO.png">
    <link rel="stylesheet" href="../assets/css/modals.css">
</head>
<body>
    <!-- alert messages -->
    <?php include __DIR__ . '/../includes/modal_alerts.php'; ?>

    <div class="container">

        <?php include __DIR__ . "/../includes/navigation.php"; ?>

        <!-- Page Content -->
        <div class="page-content">
            <!-- Page Header -->
            <div class="page-header">
                <h2>Announcements</h2>
                <div class="page-actions">
                    <?php if (hasPermission($conn, $_SESSION['user_id'], "announcements", "add")) : ?>
                        <a href="add_announcement.php" class="btn btn-success">Create Announcement</a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Announcements List -->
            <div class="announcements-list">
                <?php if ($announcement_result->num_rows > 0) : ?>
                    <?php while ($row = $announcement_result->fetch_assoc()) : 
                        $title = $row['title'];
                        $content = $row['content'];
                        $is_active = $row['is_active'] == 1 ? "Active" : "Not Active";
                        $is_active_title = $row['is_active'] == 0 ? "Active" : "Not Active";
                        $is_active_class = $row['is_active'] == 1 ? "status-active" : "status-inactive";
                        $created_at = $row['created_at'];
                        $created_by = isset($row['middle_name']) ? $row['given_name'] . " " . $row['middle_name'] . " " . $row['last_name'] : $row['given_name'] . " " . $row['last_name'];
                        $role = $row['role'];
                        $addressed_to = !empty($row['scholarship_program_name']) ? $row['scholarship_program_name'] : "All Scholarship Programs";
                    ?>
                        <div class="announcement-card <?= $is_active_class ?>">
                            <div class="announcement-header">
                                <h3><?= htmlspecialchars($title) ?></h3>
                                <span class="status-badge <?= $is_active_class ?>"><?= htmlspecialchars($is_active) ?></span>
                            </div>
                            <div class="announcement-meta">
                                <span class="meta-item">Addressed To: <strong><?= htmlspecialchars($addressed_to) ?></strong></span>
                                <span class="meta-item">Date Posted: <?= htmlspecialchars($created_at) ?></span>
                                <span class="meta-item">Posted By: <?= htmlspecialchars($created_by) ?> (<?= htmlspecialchars($role) ?>)</span>
                            </div>
                            <div class="announcement-content">
                                <p><?= makeLinksClickable($content) ?></p>
                            </div>
                            <div class="announcement-actions">
                                <?php if (hasPermission($conn, $_SESSION['user_id'], "announcements", "edit")) : ?>
                                    <a href="edit_announcement.php?id=<?= $row['announcement_id'] ?>" class="btn-sm btn-edit">Edit</a>
                                <?php endif; ?>
                                <?php if (hasPermission($conn, $_SESSION['user_id'], "announcements", "status")) : ?>
                                    <form action="<?= $_SERVER['PHP_SELF'] ?>" method="POST" style="display: inline;">
                                        <input type="hidden" name="change_status" value="1"/>
                                        <input type="hidden" name="announcement_id" value="<?= $row['announcement_id'] ?>"/>
                                        <button type="submit" class="btn-sm btn-<?= $is_active_class ?>" onclick="return confirmAction(event, 'Are you sure you want to change the status of this announcement?', 'Change Status', 'primary')">Change Status to <?= $is_active_title ?></button>
                                    </form>
                                <?php endif; ?>
                                <?php if (hasPermission($conn, $_SESSION['user_id'], "announcements", "delete")) : ?>
                                    <form action="<?= $_SERVER['PHP_SELF'] ?>" method="POST" style="display: inline;">
                                        <input type="hidden" name="delete" value="1"/>
                                        <input type="hidden" name="announcement_id" value="<?= $row['announcement_id'] ?>"/>
                                        <button type="submit" class="btn-sm btn-delete" onclick="return confirmAction(event, 'Are you sure you want to delete this announcement?', 'Delete Announcement', 'danger')">Delete</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else : ?>
                    <div class="no-data">
                        <p>No announcements found.</p>
                        <a href="add_announcement.php" class="btn btn-success" style="margin-top: 10px;">Create your first announcement</a>
                    </div>
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