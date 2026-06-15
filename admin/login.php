<!DOCTYPE html>
<html>
    <head>
        <title>Admin Login</title>
    </head>
    <body>
        <h1>PIT ScholarshipHub</h1>
        <nav>
            <ul>
                <li><a href="../portal.php">Home</a></li>
            </ul>
        </nav>
        <h2>Admin Login</h2>
        <form method="post" action="../functions/admin_login_process.php">
            <label for="username">Username:</label><br>
            <input type="text" id="username" name="username" required><br><br>
            <label for="password">Password:</label><br>
            <input type="password" id="password" name="password" required><br><br>
            <button>Login</button>
        </form>
    </body>
</html>