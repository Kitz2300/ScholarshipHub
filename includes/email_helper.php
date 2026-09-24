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
        // Read smtp_user and smtp_pass from environment (supports lowercase 'smtp_user' / 'smtp_pass' or uppercase 'SMTP_USER' / 'SMTP_PASS')
        $smtp_user = getenv('smtp_user') ?: (getenv('SMTP_USER') ?: ($_ENV['smtp_user'] ?? ($_ENV['SMTP_USER'] ?? 'keithgabrielandales@gmail.com')));
        $smtp_pass = getenv('smtp_pass') ?: (getenv('SMTP_PASS') ?: ($_ENV['smtp_pass'] ?? ($_ENV['SMTP_PASS'] ?? 'jsncwypnchefqvsy')));

        if (empty($smtp_user) || empty($smtp_pass)) {
            error_log("SMTP Error: smtp_user or smtp_pass is missing in environment variables.");
            return false;
        }

        // ========== SMTP CONFIGURATION ==========
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = trim($smtp_user);
        $mail->Password   = trim($smtp_pass);
        $mail->Port       = 587;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->CharSet    = 'UTF-8';

        // ========== SHORT SOCKET TIMEOUT ==========
        $mail->Timeout       = 5; // Fail after 5 seconds if SMTP port is blocked by host
        $mail->SMTPKeepAlive = false;

        // ========== LOCALHOST / SERVER SSL FIX ==========
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true
            )
        );

        // Sender & Recipient
        $mail->setFrom($smtp_user, 'PIT ScholarshipHub');
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