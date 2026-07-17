<?php 
include __DIR__ . "/../includes/conn.php";
session_start();

// succes/fail session variables used by the process to send success/fail messages
if(isset($_SESSION['success'])) {
    echo "<script>alert('" . $_SESSION['success'] . "')</script>";
    unset($_SESSION['success']);
} else if(isset($_SESSION['fail'])) {
    echo "<script>alert('" . $_SESSION['fail'] . "')</script>";
    unset($_SESSION['fail']);
}

// query all the announcements, active and not active alike
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

// delete announcement function
if(isset($_POST['delete'])) {
    if(isset($_POST['announcement_id'])) {
        $announcement_id = (int) $_POST['announcement_id'];
        $delete_sql = "DELETE FROM announcements WHERE id = ?";
        $delete_stmt = $conn->prepare($delete_sql);
        $delete_stmt->bind_param("i", $announcement_id);
        
        // insert success/fail message to session variables to display success/fail message after reloading page
        if($delete_stmt->execute()) {
            $_SESSION['success'] = "Announcement has been deleted";
        } else {
            $_SESSION['fail'] = "Failed to delete announcement";
        }

        // reload page to display success/fail message and refresh announcements displayed
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
        
    }
}

?>

<html>
    <head>
        <title>Announcements</title>
    </head>
    <body>
        <?php
        if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') : ?>
            <!-- if not logged in, redirect user to login page -->
            <script>alert('You are not logged in')</script>
            <script>window.location.href='login.php'</script>
        <?php else : ?>
        <!-- if logged in, load page -->
        <h1>Announcements</h1>

        <!-- redirects to pages specified -->
        <a href="dashboard.php">Back</a><br>
        <a href="add_announcement.php">Add Announcement</a>
        <?php 
        // loop through rows and display values
        while($row=$announcement_result->fetch_assoc()) :
            $title = $row['title'];
            $content = $row['content'];
            $is_active = $row['is_active'] == 1 ? "Active" : "Not Active";
            $created_at = $row['created_at'];

            // get user info who created the announcement
            $created_by = isset($row['middle_name']) ? $row['given_name'] . " " . $row['middle_name'] . " " . $row['last_name'] : $row['given_name'] . " " . $row['last_name'] ;
            $role = $row['role'];?>

            <hr>

            <!-- display announcement with status, date posted/created, created by user name-->
            <h3><?= htmlspecialchars($title) ?> - <?= htmlspecialchars($is_active) ?></h3>
            <p>Created At: <?= htmlspecialchars($created_at) ?></p>
            <p style="white-space: pre-wrap; word-wrap: break-word;"><?= htmlspecialchars($content, ENT_QUOTES, 'UTF-8') ?></p>
            <p>Created By: <?= htmlspecialchars($created_by) ?> - <?= htmlspecialchars($role) ?></p>
            <!-- edit button to edit selected announcement -->
            <a href="edit_announcement.php?id=<?= $row['announcement_id'] ?>">Edit Announcement</a>

            <!-- delete button -->
            <form action="<?= $_SERVER['PHP_SELF'] ?>" method="post">
                <input type="hidden" name="announcement_id" value="<?= $row['announcement_id'] ?>"/>
                <button name="delete" onclick="return confirm('Delete this announcement?')">Delete Announcement</button>
            </form>
        <?php endwhile;
        
        endif; ?>
    </body>
</html>