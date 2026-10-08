<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scholar Login - PIT ScholarshipHub</title>
    <link rel="stylesheet" href="../assets/css/login.css?v=<?= filemtime('../assets/css/login.css') ?>">
    <link rel="icon" href="../assets/images/PIT%20LOGO.png">
    <link rel="stylesheet" href="../assets/css/modals.css">
</head>
<body>
    <?php include __DIR__ . "/../includes/modal_alerts.php"; ?>
    <div class="container">
        <!-- header -->
        <header class="header">
            <img src="../assets/images/PIT%20LOGO.png" alt="PIT Logo">
            <p>Palompon Institute of Technology</p>
            <h1>PIT ScholarshipHub</h1>
        </header>

        <!-- login form -->
        <main class="login-box">
            <h2>Scholar / Student Login</h2>
            <p class="subtitle">Enter your credentials to access your scholarship information.</p>

            <form method="post" action="../functions/student/scholar_login_process.php">
                <div class="form-group">
                    <label for="username">Username (Student ID)</label>
                    <input type="text" 
                           id="username" 
                           name="username" 
                           placeholder="00-0000-000" 
                           maxlength="11"
                           pattern="\d{2}-\d{4}-\d{3}"
                           title="Format must be 12-1234-123"
                           autocomplete="username" 
                           required>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="Enter your password" autocomplete="current-password" required>
                </div>

                <button type="submit" class="login-btn">Login</button>
            </form>

            <div class="login-links">
                <a href="../portal.php">← Back to Portal</a>
            </div>
        </main>

        <!-- footer -->
        <footer class="footer">
            © 2026 PIT ScholarshipHub. All rights reserved.
        </footer>
    </div>

    <!-- Student ID Auto-Dash & Formatting Script -->
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        const usernameInput = document.getElementById("username");

        usernameInput.addEventListener("input", function(e) {
            // Strip out non-digit characters
            let digits = e.target.value.replace(/\D/g, "");

            // Limit to max 9 digits
            if (digits.length > 9) {
                digits = digits.substring(0, 9);
            }

            // Format as XX-XXXX-XXX
            let formatted = "";
            if (digits.length > 6) {
                formatted = digits.substring(0, 2) + "-" + digits.substring(2, 6) + "-" + digits.substring(6);
            } else if (digits.length > 2) {
                formatted = digits.substring(0, 2) + "-" + digits.substring(2);
            } else {
                formatted = digits;
            }

            e.target.value = formatted;
        });
    });
    </script>
</body>
</html>