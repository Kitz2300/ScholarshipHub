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
        // query active announcements from the db, cast created_at(timestamp) as DATE
        $announcement_sql = "SELECT title, content, CAST(created_at AS DATE) AS created_at_date FROM announcements WHERE is_active = 1 ORDER BY created_at DESC";
        $announcement_stmt = $conn->prepare($announcement_sql);
        $announcement_stmt->execute();
        $announcement_result = $announcement_stmt->get_result();

        while($row=mysqli_fetch_assoc($announcement_result)) :
            $title = $row['title'];
            $content = $row['content']; 
            $created_at = $row['created_at_date']; ?>

            <!-- display announcement with date posted/created -->
            <h3><?= htmlspecialchars($title) ?> - <?= htmlspecialchars($created_at) ?></h3>
            <p style="white-space: pre-wrap; word-wrap: break-word;"><?= htmlspecialchars($content, ENT_QUOTES, 'UTF-8') ?></p>

        <?php endwhile;
        ?>
    </body>
</html>