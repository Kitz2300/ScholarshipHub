<?php 
session_start();

if(isset($_SESSION['user_id']) && ($_SESSION['role'] == "admin" || $_SESSION['role'] == "staff")) {
    header("Location: dashboard.php");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Login - PIT ScholarshipHub</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/login.css">
    <link rel="icon" href="../assets/images/PIT LOGO.png">
    <link rel="stylesheet" href="../assets/css/modals.css">
</head>
<body>
    <?php include __DIR__ . "/../includes/modal_alerts.php"; ?>
    <div class="container">
        <!-- header -->
        <div class="header">
            <img src="../assets/images/PIT LOGO.png" alt="PIT Logo">
            <p>Palompon Institute of Technology</p>
            <h1>PIT ScholarshipHub</h1>
        </div>

        <!-- login form -->
        <div class="login-box">
            <h2> Scholarship Coordinator Login</h2>
            <p class="subtitle">Enter your credentials to access the admin dashboard.</p>

            <form method="post" action="../functions/admin/admin_login_process.php">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" placeholder="Enter your username" required>
                </div>

                <div class="form-group">
                    <div class="label-row">
                        <label for="password">Password</label>
                        <a href="forgot_password.php" class="forgot-btn">Forgot Password?</a>
                    </div>
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