<?php session_start(); ?>

<!DOCTYPE html>
<html>
    <head>
        <title>Scholar Dashboard</title>
    </head>
    <body>
        <?php if(!isset($_SESSION['username'])) {?>
            <p>You are not authorized to access this page. Please <a href="login.php">login</a>.</p>
        <?php } else { ?>
        <h2>Scholar Dashboard</h2>
        <p>Welcome, Scholar!</p>

        <form action="../functions/logout.php" method="POST">
            <button>Logout</button>
        </form>
        <?php } ?>
    </body>
</html>