<?php 
include __DIR__ . "/../includes/conn.php";
session_start();

if(isset($_SESSION['edit_success'])) {
    echo "<script>alert('" . $_SESSION['edit_success'] . "')</script>";
    unset($_SESSION['edit_success']);
} else if(isset($_SESSION['edit_fail'])) {
    echo "<script>alert('" . $_SESSION['edit_fail'] . "')</script>";
    unset($_SESSION['edit_fail']);
}

$announcement_sql = "SELECT
                        announcements.id AS announcement_id,
                        title, 
                        content, 
                        is_active,
                        created_at,
                        scholarship_users.given_name, 
                        scholarship_users.middle_name, 
                        scholarship_users.last_name,
                        scholarship_users.role
                    FROM 
                        announcements 
                    INNER JOIN 
                        scholarship_users ON announcements.user_id = scholarship_users.id
                    ORDER BY
                        is_active DESC,
                        created_at DESC
                    ";

$announcement_stmt = $conn->prepare($announcement_sql);
$announcement_stmt->execute();
$announcement_result = $announcement_stmt->get_result();
?>

<html>
    <head>
        <title>Announcements</title>
    </head>
    <body>
        <?php
        if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { ?>
            <!-- if not logged in, redirect user to login page -->
            <script>alert('You are not logged in')</script>
            <script>window.location.href='login.php'</script>
        <?php } else { ?>
        <!-- if logged in, load page -->
        <h1>Announcements</h1>
        <a href="dashboard.php">Back</a><br>
        <a href="add_announcement.php">Add Announcement</a>
        <?php 
        while($row=$announcement_result->fetch_assoc()) {
            $title = $row['title'];
            $content = $row['content'];
            $is_active = $row['is_active'] == 1 ? "Active" : "Not Active";
            $created_at = $row['created_at'];

            // get user info who created the announcement
            $created_by = isset($row['middle_name']) ? $row['given_name'] . " " . $row['middle_name'] . " " . $row['last_name'] : $row['given_name'] . " " . $row['last_name'] ;
            $role = $row['role'];?>

            <h3><?= htmlspecialchars($title) ?> - <?= htmlspecialchars($is_active) ?></h3>
            <p>Created At: <?= htmlspecialchars($created_at) ?></p>
            <p><?= htmlspecialchars($content) ?></p>
            <p>Created By: <?= htmlspecialchars($created_by) ?> - <?= htmlspecialchars($role) ?></p>
            <a href="edit_announcement.php?id=<?= $row['announcement_id'] ?>">Edit Announcement</a>

        <?php }
        
        } ?>
    </body>
</html>