<!DOCTYPE html>
<html>
    <head>
        <title>Staff Login</title>
    </head>
    <body>
        <h2>Staff Login</h2>
        <form method="post" action="../functions/staff_login_process.php">
            <label for="username">Username:</label><br>
            <input type="text" id="username" name="username" required><br><br>
            <label for="password">Password:</label><br>
            <input type="password" id="password" name="password" required><br><br>
            <button>Login</button>
        </form>
    </body>
</html>