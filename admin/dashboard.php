<?php 
session_start();
?>

<!DOCTYPE html>
<html>
    <head>
        <title>Admin Dashboard</title>
    </head>
    <body>
        <?php 
        if(!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {?>
            <p>You are not authorized to access this page. Please <a href="login.php">login</a>.</p>
        <?php } else { ?>
        <h2>Admin Dashboard</h2>
        <p>Welcome, Admin!</p>
        <?php } ?>
    </body>
</html>