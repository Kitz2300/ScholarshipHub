<?php 
session_start();

// if login fails alert user
if(isset($_SESSION['fail'])) {
    echo "<script>alert('" . $_SESSION['fail'] . "')</script>";
    unset($_SESSION['fail']);
}
?>

<html>
    <head>
        <title>Scholar Login</title>
    </head>
    <body>
        <h1>PIT ScholarshipHub</h1>
        <nav>
            <ul>
                <li><a href="../portal.php">Home</a></li>
            </ul>
        </nav>
        <h2>Scholar Login</h2>
        <form action="../functions/student/scholar_login_process.php" method="post">
            <label for="username">Username:</label><br>
            <input type="text" id="username" name="username" required><br><br>
            <label for="password">Password:</label><br>
            <input type="password" id="password" name="password" required><br><br>
            <input type="submit" value="Login">
        </form>
    </body>
</html>