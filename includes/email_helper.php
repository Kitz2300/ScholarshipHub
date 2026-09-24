<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

function sendAccountCreatedEmail($to_email, $first_name, $student_id) {
    if (empty($to_email) || !filter_var($to_email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $mail = new PHPMailer(true);

    try {
        // Read environment variables (supports SMTP_USER, SMTP_PASS, SMTP_HOST, SMTP_PORT)
        $smtp_host = getenv('SMTP_HOST') ?: ($_ENV['SMTP_HOST'] ?? 'smtp.gmail.com');
        $smtp_user = getenv('SMTP_USER') ?: ($_ENV['SMTP_USER'] ?? 'keithgabrielandales@gmail.com');
        $smtp_pass = getenv('SMTP_PASS') ?: ($_ENV['SMTP_PASS'] ?? 'jsncwypnchefqvsy');
        $smtp_port = (int) (getenv('SMTP_PORT') ?: ($_ENV['SMTP_PORT'] ?? 587));
        $smtp_from = getenv('SMTP_FROM') ?: ($_ENV['SMTP_FROM'] ?? $smtp_user);
        $smtp_from_name = getenv('SMTP_FROM_NAME') ?: ($_ENV['SMTP_FROM_NAME'] ?? 'PIT ScholarshipHub');

        if (empty($smtp_user) || empty($smtp_pass)) {
            error_log("SMTP Error: Username or password missing in environment variables.");
            return false;
        }

        // ========== SMTP CONFIGURATION ==========
        $mail->isSMTP();
        $mail->Host       = $smtp_host;
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtp_user;
        $mail->Password   = $smtp_pass;
        $mail->Port       = $smtp_port;
        $mail->CharSet    = 'UTF-8';

        // Set Encryption based on Port (465 = SMTPS, 587 = STARTTLS)
        if ($smtp_port === 465) {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }

        // ========== TIMEOUT & CONNECTION SETTINGS ==========
        $mail->Timeout       = 10; // Socket connection timeout in seconds
        $mail->SMTPKeepAlive = false;

        // ========== XAMPP LOCALHOST / SERVER SSL FIX ==========
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true
            )
        );

        // Sender & Recipient
        $mail->setFrom($smtp_from, $smtp_from_name);
        $mail->addAddress($to_email, $first_name);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Account Created - PIT ScholarshipHub';
        
        $body  = "<h2>Welcome to PIT ScholarshipHub!</h2>";
        $body .= "<p>Dear <strong>" . htmlspecialchars($first_name) . "</strong>,</p>";
        $body .= "<p>An account has been successfully created for you. You can now log in to view your scholarship profile and academic performance.</p>";
        $body .= "<div style='background:#f4f4f4; padding:15px; border-radius:5px; margin:15px 0;'>";
        $body .= "<p style='margin:0 0 8px 0;'><strong>Login Credentials:</strong></p>";
        $body .= "<p style='margin:0 0 5px 0;'><strong>Username:</strong> " . htmlspecialchars($student_id) . "</p>";
        $body .= "<p style='margin:0;'><strong>Temporary Password:</strong> " . htmlspecialchars($student_id) . "</p>";
        $body .= "</div>";
        $body .= "<p><em>Note: Please log in and update your password as soon as possible.</em></p>";
        $body .= "<p>Best regards,<br>PIT Scholarship Office</p>";

        $mail->Body = $body;
        return $mail->send();
    } catch (Exception $e) {
        error_log("Mail Error for $to_email: " . $mail->ErrorInfo);
        return false;
    }
}
?>