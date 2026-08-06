<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\OAuthTokenProvider;

require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';
require_once __DIR__ . '/PHPMailer/src/OAuthTokenProvider.php';
require_once __DIR__ . '/config.php';

/**
 * Custom Fetcher for Azure AD Access Tokens (Client Credentials Flow for M365)
 */
class AzureTokenFetcher {
    public static function getAccessToken($tenantId, $clientId, $clientSecret) {
        $url = "https://login.microsoftonline.com/$tenantId/oauth2/v2.0/token";
        $data = [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'grant_type' => 'client_credentials',
            'scope' => 'https://outlook.office365.com/.default'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        
        if (curl_errno($ch)) {
            $error_msg = curl_error($ch);
            curl_close($ch);
            throw new Exception("CURL Error: " . $error_msg);
        }
        curl_close($ch);

        $res = json_decode($response, true);
        if (isset($res['access_token'])) {
            return $res['access_token'];
        }

        throw new Exception("Failed to get Access Token: " . ($res['error_description'] ?? 'Unknown error'));
    }
}

/**
 * Implementation of PHPMailer's OAuthTokenProvider for Microsoft 365
 */
class AzureXOAUTH2Provider implements OAuthTokenProvider {
    private $userName;
    private $token;

    public function __construct($userName, $token) {
        $this->userName = $userName;
        $this->token = $token;
    }

    public function getOauth64() {
        return base64_encode("user=" . $this->userName . "\001auth=Bearer " . $this->token . "\001\001");
    }
}

/**
 * Send an email using PHPMailer (Supports XOAUTH2 for Microsoft 365)
 */
function send_email($to, $subject, $body, $attachments = []) {
    if (!defined('MAIL_ENABLED') || !MAIL_ENABLED) {
        error_log("MAIL_ENABLED is false. Email to $to suppressed.");
        return true;
    }

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Port       = SMTP_PORT;
        $mail->SMTPSecure = (SMTP_SECURE === 'tls') ? PHPMailer::ENCRYPTION_STARTTLS : PHPMailer::ENCRYPTION_SMTPS;
        $mail->Timeout    = 8;
        
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ];

        if (defined('MAIL_AUTH_MODE') && MAIL_AUTH_MODE === 'XOAUTH2') {
            $token = AzureTokenFetcher::getAccessToken(MAIL_TENANT_ID, MAIL_CLIENT_ID, MAIL_CLIENT_SECRET);
            $mail->AuthType = 'XOAUTH2';
            $mail->Username = SMTP_USER;
            $mail->setOAuth(new AzureXOAUTH2Provider(SMTP_USER, $token));
        } else {
            $mail->Username = SMTP_USER;
            $mail->Password = defined('SMTP_PASS') ? SMTP_PASS : '';
        }

        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($to);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = strip_tags($body);

        foreach ($attachments as $attachment) {
            if (file_exists($attachment)) {
                $mail->addAttachment($attachment);
            }
        }

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mailer Error ($to): " . $e->getMessage());
        return "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
    }
}

/**
 * Send Formatted CRM Notification Email
 */
function sendCRMEmailNotification($toEmail, $recipientName, $subject, $title, $messageHtml, $buttonText = '', $buttonUrl = '') {
    if (empty($toEmail)) return false;

    $baseUrl = defined('BASE_URL') ? BASE_URL : '/crm';
    if (!empty($buttonUrl) && strpos($buttonUrl, 'http') === false) {
        $buttonUrl = rtrim($baseUrl, '/') . '/' . ltrim($buttonUrl, '/');
    }

    $buttonHtml = '';
    if (!empty($buttonText) && !empty($buttonUrl)) {
        $buttonHtml = '
        <div style="text-align: center; margin-top: 30px; margin-bottom: 10px;">
            <a href="' . htmlspecialchars($buttonUrl) . '" style="display: inline-block; padding: 12px 28px; background-color: #12b1b0; color: #ffffff !important; text-decoration: none; border-radius: 8px; font-weight: 700; font-size: 14px; box-shadow: 0 4px 12px rgba(18, 177, 176, 0.3);">
                ' . htmlspecialchars($buttonText) . '
            </a>
        </div>';
    }

    $body = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <style>
            @import url("https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap");
            body { font-family: "Outfit", "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background-color: #f8fafc; margin: 0; padding: 0; }
            .container { max-width: 600px; margin: 30px auto; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.06); border: 1px solid #e2e8f0; }
            .header { background: linear-gradient(135deg, #12b1b0 0%, #0f172a 100%); color: #ffffff; padding: 30px; text-align: center; }
            .header .logo { font-size: 24px; font-weight: 800; letter-spacing: -0.5px; margin-bottom: 4px; }
            .header .subtitle { font-size: 14px; opacity: 0.95; }
            .content { padding: 35px 30px; color: #334155; line-height: 1.6; font-size: 15px; }
            .title { font-size: 19px; font-weight: 700; color: #0f172a; margin-top: 0; margin-bottom: 16px; }
            .footer { background-color: #f8fafc; border-top: 1px solid #e2e8f0; padding: 20px; text-align: center; font-size: 13px; color: #64748b; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <div class="logo">BBSL CRM</div>
                <div class="subtitle">Executive Sales Pipeline & CRM</div>
            </div>
            <div class="content">
                <h2 class="title">' . htmlspecialchars($title) . '</h2>
                <p>Hello ' . htmlspecialchars($recipientName) . ',</p>
                <div>' . $messageHtml . '</div>
                ' . $buttonHtml . '
            </div>
            <div class="footer">
                &copy; ' . date('Y') . ' BBSL Business Solutions Limited. All rights reserved.<br>
                This is an automated notification from BBSL CRM. Please do not reply directly.
            </div>
        </div>
    </body>
    </html>';

    return send_email($toEmail, $subject, $body);
}
