<?php session_start(); ?>

<!DOCTYPE html>
<html>
    <head>
        <title>Staff Dashboard</title>
    </head>
    <body>
        <?php if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {?>
            <p>You are not authorized to access this page. Please <a href="login.php">login</a>.</p>
        <?php } else { ?>
        <h2>Staff Dashboard</h2>
        <p>Welcome, Staff!</p>

        <!-- logout button -->
        <form action="../functions/logout.php" method="POST">
            <button>Logout</button>
        </form>
        <?php } ?>
    </body>
</html>