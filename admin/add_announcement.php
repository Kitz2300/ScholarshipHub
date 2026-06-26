<?php 
session_start();
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
        <a href="announcements.php">Back</a><br><br>

        <!-- Announcement Form -->
        <form action="../functions/add_announcement_process.php" method="post">
            <label for="title">Title:</label>
            <input type="text" name="title" id="title" required/><br><br>
            
            <label for="content">Content:</label><br>
            <textarea name="content" id="content" required></textarea><br><br>

            <label for="status">Status:</label>
            <select name="status">
                <option value="1">Active</option>
                <option value="0">Not Active</option>
            </select><br><br>

            <button>Create Announcement</button>
            <button onclick="window.location.href='announcements.php'">Cancel</button>
        </form>
        <?php } ?>
    </body>
</html>