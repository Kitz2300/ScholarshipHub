<html>
    <head>
        <title>Scholar Login</title>
    </head>
    <body>
        <h2>Scholar Login</h2>
        <form action="../functions/scholar_login_process.php" method="post">
            <label for="username">Username:</label>
            <input type="text" id="username" name="username" required><br><br>
            <label for="password">Password:</label>
            <input type="password" id="password" name="password" required><br><br>
            <input type="submit" value="Login">
        </form>
    </body>
</html>