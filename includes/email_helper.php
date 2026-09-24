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
        // ========== SMTP CONFIGURATION ==========
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'keithgabrielandales@gmail.com';
        $mail->Password   = 'jsncwypnchefqvsy';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';

        // ========== TIMEOUT & CONNECTION SETTINGS ==========
        $mail->Timeout       = 5; // Socket connection timeout in seconds
        $mail->SMTPKeepAlive = false;

        // ========== XAMPP LOCALHOST SSL FIX ==========
        // Prevents OpenSSL certificate verification failures on Windows XAMPP
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true
            )
        );

        // Sender & Recipient
        $mail->setFrom('keithgabrielandales@gmail.com', 'PIT ScholarshipHub');
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
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mail Error for $to_email: " . $mail->ErrorInfo);
        return false;
    }
}
?>