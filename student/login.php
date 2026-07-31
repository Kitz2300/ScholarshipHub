<?php 
session_start();

// if login fails alert user
if(isset($_SESSION['fail'])) {
    echo "<script>alert('" . $_SESSION['fail'] . "')</script>";
    unset($_SESSION['fail']);
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Scholar Login - PIT ScholarshipHub</title>
    <link rel="stylesheet" href="../assets/css/login.css">
    <link rel="icon" href="../assets/images/PIT%20LOGO.png">
</head>
<body>
    <div class="container">
        <!-- header -->
        <div class="header">
            <img src="../assets/images/PIT%20LOGO.png" alt="PIT Logo">
            <p>Palompon Institute of Technology</p>
            <h1>PIT ScholarshipHub</h1>
        </div>

        <!-- login form -->
        <div class="login-box">
            <h2>🎓 Scholar / Student Login</h2>
            <p class="subtitle">Enter your credentials to access your scholarship information.</p>

            <?php if(isset($_SESSION['fail'])): ?>
                <div class="error-msg"><?= htmlspecialchars($_SESSION['fail']) ?></div>
                <?php unset($_SESSION['fail']); ?>
            <?php endif; ?>

            <form method="post" action="../functions/student/scholar_login_process.php">
                <div class="form-group">
                    <label for="username">Username (Student ID)</label>
                    <input type="text" id="username" name="username" placeholder="Enter your student ID" required>
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