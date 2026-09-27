<?php
include '../includes/conn.php';
session_start();

if (!isset($_SESSION['reset_email'])) {
    header("Location: forgot_password.php");
    exit();
}

$email = $_SESSION['reset_email'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_otp'])) {
    $entered_otp = trim($_POST['otp']);

    $sql = "SELECT id FROM scholarship_users 
            WHERE LOWER(email) = ? AND role = 'admin' AND reset_otp = ? AND otp_expiry > NOW()";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $email, $entered_otp);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($user = $res->fetch_assoc()) {
        $_SESSION['otp_verified_admin_id'] = $user['id'];
        header("Location: reset_password.php");
        exit();
    } else {
        $error = "Invalid or expired OTP code.";
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify OTP - Admin Access</title>
    <link rel="stylesheet" href="../assets/css/recovery.css">
    <link rel="icon" href="../assets/images/PIT%20LOGO.png">
    <script src="../assets/js/disable_button.js"></script>
</head>
<body>
    <div class="recovery-card">
        <div class="card-header">
            <img src="../assets/images/PIT%20LOGO.png" alt="PIT Logo">
            <h2>Enter OTP Code</h2>
            <p>Sent to: <?= htmlspecialchars($email) ?></p>
        </div>
        <div class="card-body">
            <?php if (isset($error)): ?>
                <div class="alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <form action="" method="POST">
                <div class="form-group">
                    <label for="otp">6-Digit Code</label>
                    <input type="text" id="otp" name="otp" class="otp-input" maxlength="6" pattern="\d{6}" placeholder="123456" required autofocus autocomplete="off">
                </div>
                <button type="submit" name="verify_otp" class="btn-submit">Verify & Continue</button>
            </form>
            <div class="card-footer-links">
                <a href="forgot_password.php">Resend Code</a>
                <a href="login.php">Back to Login</a>
            </div>
        </div>
    </div>
</body>
</html>