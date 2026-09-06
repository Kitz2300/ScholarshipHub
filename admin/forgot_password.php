<?php
include '../includes/conn.php';
session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../lib/vendor/autoload.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_otp'])) {
    $email = strtolower(trim($_POST['email']));

    if (!empty($email)) {
        $sql = "SELECT id FROM scholarship_users WHERE LOWER(email) = ? AND role = 'admin'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($user = $res->fetch_assoc()) {
            $otp = sprintf("%06d", mt_rand(100000, 999999));

            // Sync expiration natively in MySQL to prevent timezone mismatch
            $update_sql = "UPDATE scholarship_users SET reset_otp = ?, otp_expiry = DATE_ADD(NOW(), INTERVAL 15 MINUTE) WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("si", $otp, $user['id']);
            $update_stmt->execute();
            $update_stmt->close();

            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'keithgabrielandales@gmail.com';
                $mail->Password   = 'jsncwypnchefqvsy';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;

                $mail->setFrom('keithgabrielandales@gmail.com', 'PIT ScholarshipHub');
                $mail->addAddress($email);

                $mail->isHTML(true);
                $mail->Subject = 'Password Reset OTP - PIT ScholarshipHub Admin';
                $mail->Body    = "<div style='font-family:Arial,sans-serif; padding:20px; border:1px solid #e0e0e0; border-radius:8px;'>
                                    <h2 style='color:#1e4628;'>Admin Account Recovery</h2>
                                    <p>Your OTP for password reset is:</p>
                                    <h1 style='color:#2a6b37; letter-spacing:4px;'>{$otp}</h1>
                                    <p style='color:#666; font-size:12px;'>This code will expire in 15 minutes.</p>
                                  </div>";

                $mail->send();

                $_SESSION['reset_email'] = $email;
                header("Location: verify_otp.php");
                exit();
            } catch (Exception $e) {
                $error = "Failed to send OTP email. Please try again.";
            }
        } else {
            $error = "No admin account found with that email address.";
        }
        $stmt->close();
    } else {
        $error = "Please enter your email address.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Admin Access</title>
    <link rel="stylesheet" href="../assets/css/recovery.css">
    <link rel="icon" href="../assets/images/PIT%20LOGO.png">
</head>
<body>
    <div class="recovery-card">
        <div class="card-header">
            <img src="../assets/images/PIT%20LOGO.png" alt="PIT Logo">
            <h2>Account Recovery</h2>
            <p>PIT ScholarshipHub - Admin Panel</p>
        </div>
        <div class="card-body">
            <?php if (isset($error)): ?>
                <div class="alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <form action="" method="POST">
                <div class="form-group">
                    <label for="email">Admin Email Address</label>
                    <input type="email" id="email" name="email" placeholder="admin@pit.edu.ph" required autofocus>
                </div>
                <button type="submit" name="request_otp" class="btn-submit">Send OTP Code</button>
            </form>
            <div class="card-footer-links">
                <a href="login.php">← Back to Login</a>
            </div>
        </div>
    </div>
</body>
</html>