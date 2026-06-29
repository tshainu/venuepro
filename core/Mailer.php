<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Mailer {

    private static function smtp(): PHPMailer {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = getenv('MAIL_USER') ?: 'venueprox@gmail.com';
        $mail->Password   = getenv('MAIL_PASS') ?: '';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';
        $mail->setFrom(
            getenv('MAIL_USER') ?: 'venueprox@gmail.com',
            'VenuePro'
        );
        return $mail;
    }

    public static function sendPasswordReset(string $toEmail, string $toName, string $resetLink): bool {
        try {
            $mail = self::smtp();
            $mail->addAddress($toEmail, $toName);
            $mail->isHTML(true);
            $mail->Subject = 'Reset Your VenuePro Password';
            $mail->Body    = self::resetTemplate($toName, $resetLink);
            $mail->AltBody = "Hi $toName,\n\nClick the link below to reset your password (valid for 1 hour):\n$resetLink\n\nIf you didn't request this, ignore this email.\n\n— VenuePro Team";
            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log('Mailer error: ' . $e->getMessage());
            return false;
        }
    }

    private static function resetTemplate(string $name, string $link): string {
        $year = date('Y');
        return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:'Inter',Arial,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="padding:40px 16px;">
    <tr><td align="center">
      <table width="100%" style="max-width:520px;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.08);">
        <tr>
          <td style="background:linear-gradient(135deg,#0a1628 0%,#162d5a 100%);padding:36px 40px;text-align:center;">
            <div>
              <div style="width:40px;height:40px;background:linear-gradient(135deg,#c9a84c,#e8c96a);border-radius:10px;display:inline-block;font-size:20px;line-height:40px;text-align:center;vertical-align:middle;">&#127963;</div>
              <span style="color:#fff;font-size:22px;font-weight:800;letter-spacing:-.5px;vertical-align:middle;margin-left:10px;">VenuePro</span>
            </div>
          </td>
        </tr>
        <tr>
          <td style="padding:40px 40px 32px;">
            <h2 style="margin:0 0 8px;font-size:22px;font-weight:800;color:#0c1a35;">Reset your password</h2>
            <p style="margin:0 0 24px;color:#6b7280;font-size:15px;line-height:1.6;">Hi <strong style="color:#1f2937;">$name</strong>, we received a request to reset your VenuePro password. Click the button below &mdash; this link is valid for <strong>1 hour</strong>.</p>
            <div style="text-align:center;margin:32px 0;">
              <a href="$link" style="display:inline-block;background:linear-gradient(135deg,#c9a84c,#e8c96a);color:#0c1a35;font-size:15px;font-weight:800;text-decoration:none;padding:14px 36px;border-radius:10px;">Reset Password</a>
            </div>
            <p style="margin:0 0 8px;color:#9ca3af;font-size:13px;">Or copy this link into your browser:</p>
            <div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:8px;padding:12px 16px;word-break:break-all;">
              <a href="$link" style="color:#c9a84c;font-size:12px;text-decoration:none;">$link</a>
            </div>
          </td>
        </tr>
        <tr>
          <td style="padding:20px 40px 32px;border-top:1px solid #f1f5f9;">
            <p style="margin:0;color:#9ca3af;font-size:12px;line-height:1.6;">If you didn't request a password reset, you can safely ignore this email. Your password won&rsquo;t change.</p>
            <p style="margin:12px 0 0;color:#cbd5e1;font-size:11px;">&copy; $year VenuePro &middot; AxisXNOR (PVT) Ltd</p>
          </td>
        </tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;
    }
}
