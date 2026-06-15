<?php 
include __DIR__ . "/includes/conn.php";
?>

<html>
    <head>
        <title>Scholarship Portal</title>
    </head>
    <body>
        <h1>Welcome to PIT ScholarshipHub</h1>
        <h2>Login as:</h2>
        <nav>
            <ul>
                <li><a href="admin/login.php">Admin</a></li>
                <li><a href="staff/login.php">Staff</a></li>
                <li><a href="student/login.php">Scholar</a></li>
            </ul>
        </nav>

        <h2>Announcements:</h2>
        <!-- Fetch and display announcements from the database -->
        <?php 
        $announcement_sql = "SELECT title, content FROM announcements WHERE is_active = 1 ORDER BY created_at DESC";
        $announcement_stmt = $conn->prepare($announcement_sql);
        $announcement_stmt->execute();
        $announcement_result = $announcement_stmt->get_result();

        while($row=mysqli_fetch_assoc($announcement_result)) {
            $title = $row['title'];
            $content = $row['content'];

            echo "<h3>$title</h3>";
            echo "<p>$content</p>";

        }
        ?>
    </body>
</html>