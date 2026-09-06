<?php
include '../includes/conn.php';
session_start();

if (!isset($_SESSION['otp_verified_admin_id'])) {
    header("Location: forgot_password.php");
    exit();
}

$admin_id = $_SESSION['otp_verified_admin_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($new_password) || strlen($new_password) < 8) {
        $error = "Password must be at least 8 characters long.";
    } elseif ($new_password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        $sql = "UPDATE scholarship_users 
                SET password = ?, reset_otp = NULL, otp_expiry = NULL 
                WHERE id = ? AND role = 'admin'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $hashed_password, $admin_id);

        if ($stmt->execute()) {
            unset($_SESSION['reset_email']);
            unset($_SESSION['otp_verified_admin_id']);
            $_SESSION['success'] = "Password updated successfully. Please log in.";
            header("Location: login.php");
            exit();
        } else {
            $error = "Failed to update password.";
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Admin Access</title>
    <link rel="stylesheet" href="../assets/css/recovery.css">
    <link rel="icon" href="../assets/images/PIT%20LOGO.png">
</head>
<body>
    <div class="recovery-card">
        <div class="card-header">
            <img src="../assets/images/PIT%20LOGO.png" alt="PIT Logo">
            <h2>Set New Password</h2>
            <p>PIT ScholarshipHub - Admin Panel</p>
        </div>
        <div class="card-body">
            <?php if (isset($error)): ?>
                <div class="alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <form action="" method="POST">
                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password" minlength="8" required autofocus placeholder="Minimum 8 characters">
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" minlength="8" required placeholder="Re-enter new password">
                </div>
                <button type="submit" name="update_password" class="btn-submit">Update Password</button>
            </form>
        </div>
    </div>
</body>
</html>