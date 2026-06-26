<?php 
include __DIR__ . "/../includes/conn.php";
session_start();

$announcement_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$announcement_sql = "SELECT * FROM announcements WHERE id=?";
$announcement_stmt = $conn->prepare($announcement_sql);
$announcement_stmt->bind_param("i", $announcement_id);
$announcement_stmt->execute();
$result = $announcement_stmt->get_result();

// announcement data variables
$title = "";
$content = "";
$status = 0;

if($row=$result->fetch_assoc()) {
    $title = $row['title'];
    $content = $row['content'];
    $status = (int)$row['is_active'];
}


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
        <h1>Add Announcement</h1>
        <a href="announcements.php">Back</a>

        <!-- Edit Announcement Form -->
        <form action="../functions/edit_announcement_process.php" method="post">
            <label for="title">Title:</label>
            <input type="text" name="title" id="title" value="<?= htmlspecialchars($title) ?>" required/><br><br>
            
            <label for="content">Content:</label><br>
            <textarea name="content" id="content" required><?= htmlspecialchars($content) ?></textarea><br><br>

            <label for="status">Status:</label>
            <select name="status">
                <option value="1" <?= $status == 1 ? "selected" : "" ?>>Active</option>
                <option value="0" <?= $status == 0 ? "selected" : "" ?>>Not Active</option>
            </select><br><br>

            <input type="hidden" name="announcement_id" value="<?= htmlspecialchars($announcement_id) ?>"/>

            <button>Update Announcement</button>
            <button type="button" onclick="window.location.href='announcements.php'">Cancel</button>
        </form>
        <?php } ?>
    </body>
</html>