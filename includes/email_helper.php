<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

function sendAccountCreatedEmail($to_email, $first_name, $student_id) {
    if (empty($to_email) || !filter_var($to_email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $subject = 'Account Created - PIT ScholarshipHub';
    
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
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code === 200 && $response) {
            $result = json_decode($response, true);
            if (isset($result['status']) && $result['status'] === 'success') {
                return true;
            }
        }
        error_log("Webhook Email failed or returned invalid response (HTTP $http_code). Attempting PHPMailer SMTP fallback...");
    }

    // =========================================================================
    // METHOD 2: Direct PHPMailer SMTP (Fallback - for Localhost / XAMPP)
    // =========================================================================
    $mail = new PHPMailer(true);

    try {
        $smtp_host = getenv('SMTP_HOST') ?: (getenv('smtp_host') ?: ($_ENV['SMTP_HOST'] ?? ($_ENV['smtp_host'] ?? 'smtp.gmail.com')));
        $smtp_user = getenv('smtp_user') ?: (getenv('SMTP_USER') ?: ($_ENV['smtp_user'] ?? ($_ENV['SMTP_USER'] ?? 'keithgabrielandales@gmail.com')));
        $smtp_pass = getenv('smtp_pass') ?: (getenv('SMTP_PASS') ?: ($_ENV['smtp_pass'] ?? ($_ENV['SMTP_PASS'] ?? 'jsncwypnchefqvsy')));
        $smtp_port = (int) (getenv('SMTP_PORT') ?: (getenv('smtp_port') ?: ($_ENV['SMTP_PORT'] ?? ($_ENV['smtp_port'] ?? 587))));

        if (empty($smtp_user) || empty($smtp_pass)) {
            error_log("SMTP Error: Credentials missing in environment variables.");
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

        $mail->Timeout       = 5;
        $mail->SMTPKeepAlive = false;

        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true
            )
        );

        $mail->setFrom($smtp_user, 'PIT ScholarshipHub');
        $mail->addAddress($to_email, $first_name);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;

        return $mail->send();
    } catch (Exception $e) {
        error_log("PHPMailer Mail Error for $to_email: " . $mail->ErrorInfo);
        return false;
    }
}
?>