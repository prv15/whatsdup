<?php

declare(strict_types=1);

namespace WhatstheUp\Services;

use PHPMailer\PHPMailer\PHPMailer;
use RuntimeException;
use WhatstheUp\Support\Env;

final class MailerService
{
    public function sendPasswordReset(string $recipient, string $name, string $resetUrl): void
    {
        $mail = new PHPMailer(true);
        $host = Env::get('MAIL_HOST', '') ?? '';
        if ($host !== '') {
            $mail->isSMTP();
            $mail->Host = $host;
            $mail->Port = Env::int('MAIL_PORT', 587);
            $mail->SMTPAuth = (Env::get('MAIL_USERNAME', '') ?? '') !== '';
            $mail->Username = Env::get('MAIL_USERNAME', '') ?? '';
            $mail->Password = Env::get('MAIL_PASSWORD', '') ?? '';
            $encryption = Env::get('MAIL_ENCRYPTION', 'tls') ?? 'tls';
            if ($encryption !== '') {
                $mail->SMTPSecure = $encryption;
            }
        } else {
            $mail->isMail();
        }

        $from = Env::get('MAIL_FROM_ADDRESS', '') ?? '';
        if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('MAIL_FROM_ADDRESS is not configured.');
        }
        $mail->setFrom($from, Env::get('MAIL_FROM_NAME', 'WhatstheUp') ?? 'WhatstheUp');
        $mail->addAddress($recipient, $name);
        $mail->isHTML(true);
        $mail->Subject = 'Reset your WhatstheUp password';
        $safeName = htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeUrl = htmlspecialchars($resetUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $ttlMinutes = max(1, (int) ceil(Env::int('PASSWORD_RESET_TTL', 3600) / 60));
        $mail->Body = "<p>Hello {$safeName},</p><p>Use the button below to reset your WhatstheUp password. This link expires in {$ttlMinutes} minutes and can only be used once.</p><p><a href=\"{$safeUrl}\" style=\"display:inline-block;padding:12px 18px;background:#006b5e;color:#fff;text-decoration:none;border-radius:8px\">Reset password</a></p><p>If you did not request this, you can ignore this email.</p>";
        $mail->AltBody = "Reset your WhatstheUp password: {$resetUrl}\n\nThis link expires in {$ttlMinutes} minutes and can only be used once.";
        $mail->send();
    }
}
