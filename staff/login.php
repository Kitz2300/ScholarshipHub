<?php session_start(); ?>
<!DOCTYPE html>
<html>
<head>
    <title>Staff Login - PIT ScholarshipHub</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/login.css">
    <link rel="icon" href="../assets/images/PIT%20LOGO.png">
    <link rel="stylesheet" href="../assets/css/modals.css">
</head>
<body>
    <?php include __DIR__ . "/../includes/modal_alerts.php"; ?>
    <div class="container">
        <!-- header -->
        <div class="header">
            <img src="../assets/images/PIT%20LOGO.png" alt="PIT Logo">
            <p>Palompon Institute of Technology</p>
            <h1>PIT ScholarshipHub</h1>
        </div>

        <!-- login form -->
        <div class="login-box">
            <h2>Staff Login</h2>
            <p class="subtitle">Enter your credentials to access the staff dashboard.</p>

            <form method="post" action="../functions/staff/staff_login_process.php">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" placeholder="Enter your username" required>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="Enter your password" required>
                </div>

                <button type="submit" class="login-btn">Login</button>
            </form>

            <div class="login-links">
                <a href="../portal.php">← Back to Portal</a>
            </div>
        </div>

        <!-- footer -->
        <div class="footer">
            © 2026 PIT ScholarshipHub. All rights reserved.
        </div>
    </div>
</body>
</html>