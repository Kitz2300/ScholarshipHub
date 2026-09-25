<?php
include __DIR__ . '/../includes/conn.php';
session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Autoload resolution for vendor packages across environments
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} elseif (file_exists(__DIR__ . '/../lib/vendor/autoload.php')) {
    require_once __DIR__ . '/../lib/vendor/autoload.php';
}

/**
 * Sends Admin Reset OTP via Webhook (Primary for Railway) or PHPMailer SMTP (Fallback for XAMPP)
 */
function sendOTPEmail($to_email, $otp) {
    if (empty($to_email) || !filter_var($to_email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $subject = 'Password Reset OTP - PIT ScholarshipHub Admin';
    $body  = "<div style='font-family:Arial,sans-serif; padding:20px; border:1px solid #e0e0e0; border-radius:8px;'>";
    $body .= "<h2 style='color:#1e4628;'>Admin Account Recovery</h2>";
    $body .= "<p>Your OTP for password reset is:</p>";
    $body .= "<h1 style='color:#2a6b37; letter-spacing:4px;'>{$otp}</h1>";
    $body .= "<p style='color:#666; font-size:12px;'>This code will expire in 15 minutes.</p>";
    $body .= "</div>";

    // =========================================================================
    // METHOD 1: Google Apps Script Webhook (Primary - for Railway / Cloud)
    // =========================================================================
    $webhook_url = getenv('GMAIL_WEBHOOK_URL') ?: ($_ENV['GMAIL_WEBHOOK_URL'] ?? '');

    if (!empty($webhook_url)) {
        $payload = json_encode([
            'secret'  => 'PIT_SCHOLARSHIP_HUB_SECRET_2026',
            'to'      => $to_email,
            'subject' => $subject,
            'body'    => $body
        ]);

        $ch = curl_init($webhook_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code === 200 && $response) {
            $result = json_decode($response, true);
            if (isset($result['status']) && $result['status'] === 'success') {
                return true;
            }
        }
        error_log("Webhook OTP email failed (HTTP $http_code). Attempting PHPMailer SMTP fallback...");
    }

    // =========================================================================
    // METHOD 2: Direct PHPMailer SMTP (Fallback - for Localhost / XAMPP)
    // =========================================================================
    $original_socket_timeout = ini_get('default_socket_timeout');
    ini_set('default_socket_timeout', 3);

    $mail = new PHPMailer(true);

    try {
        $smtp_host = getenv('SMTP_HOST') ?: (getenv('smtp_host') ?: ($_ENV['SMTP_HOST'] ?? ($_ENV['smtp_host'] ?? 'smtp.gmail.com')));
        $smtp_user = getenv('smtp_user') ?: (getenv('SMTP_USER') ?: ($_ENV['smtp_user'] ?? ($_ENV['SMTP_USER'] ?? 'keithgabrielandales@gmail.com')));
        $smtp_pass = getenv('smtp_pass') ?: (getenv('SMTP_PASS') ?: ($_ENV['smtp_pass'] ?? ($_ENV['SMTP_PASS'] ?? 'jsncwypnchefqvsy')));
        $smtp_port = (int) (getenv('SMTP_PORT') ?: (getenv('smtp_port') ?: ($_ENV['SMTP_PORT'] ?? ($_ENV['smtp_port'] ?? 587))));

        if (empty($smtp_user) || empty($smtp_pass)) {
            error_log("SMTP Error: Credentials missing in environment variables.");
            ini_set('default_socket_timeout', $original_socket_timeout);
            return false;
        }

        $mail->isSMTP();
        $mail->Host       = trim($smtp_host);
        $mail->SMTPAuth   = true;
        $mail->Username   = trim($smtp_user);
        $mail->Password   = trim($smtp_pass);
        $mail->Port       = $smtp_port;
        $mail->CharSet    = 'UTF-8';

        if ($smtp_port === 465) {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }

        $mail->Timeout       = 3;
        $mail->SMTPKeepAlive = false;

        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true
            )
        );

        $mail->setFrom($smtp_user, 'PIT ScholarshipHub');
        $mail->addAddress($to_email);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;

        $sent = $mail->send();
        ini_set('default_socket_timeout', $original_socket_timeout);
        return $sent;
    } catch (Exception $e) {
        error_log("PHPMailer OTP Error for $to_email: " . $mail->ErrorInfo);
        ini_set('default_socket_timeout', $original_socket_timeout);
        return false;
    }
}

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

            if (sendOTPEmail($email, $otp)) {
                $_SESSION['reset_email'] = $email;
                header("Location: verify_otp.php");
                exit();
            } else {
                $error = "Failed to send OTP email. Please verify mail configuration or try again.";
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
