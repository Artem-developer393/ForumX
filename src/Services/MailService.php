<?php

namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use Exception;

class MailService
{
    private string $host;
    private int $port;
    private string $username;
    private string $password;
    private string $encryption;
    private string $fromAddress;
    private string $fromName;

    public function __construct()
    {
        $this->host = $_ENV['MAIL_HOST'];
        $this->port = (int)$_ENV['MAIL_PORT'];
        $this->username = $_ENV['MAIL_USERNAME'];
        $this->password = $_ENV['MAIL_PASSWORD'];
        $this->encryption = $_ENV['MAIL_ENCRYPTION'];
        $this->fromAddress = $_ENV['MAIL_FROM_ADDRESS'];
        $this->fromName = $_ENV['MAIL_FROM_NAME'];
    }

    public function sendVerificationCode(string $email, string $code): void
    {
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();

            $mail->Host = $this->host;
            $mail->SMTPAuth = true;
            $mail->Username = $this->username;
            $mail->Password = $this->password;
            $mail->Port = $this->port;

            if ($this->encryption === 'tls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } elseif ($this->encryption === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            }

            $mail->CharSet = 'UTF-8';

            $mail->setFrom(
                $this->fromAddress,
                $this->fromName
            );

            $mail->addAddress($email);

            $mail->isHTML(true);

            $mail->Subject = 'ForumX verification code';

            $mail->Body = '
                <h2>Email verification</h2>
                <p>Your verification code:</p>
                <p style="font-size: 28px; font-weight: bold; letter-spacing: 5px;">
                    ' . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . '
                </p>
                <p>The code is valid for 15 minutes.</p>
                <p>If you did not register for ForumX, please ignore this email.</p>
            ';

            $mail->AltBody =
                "ForumX verification code: {$code}\n" .
                "The code is valid for 15 minutes.\n" .
                "If you did not register for ForumX, please ignore this email.";

            $mail->send();
        } catch (PHPMailerException $e) {
            throw new Exception(
                'Failed to send email: ' . $e->getMessage()
            );
        }
    }

    public function sendPasswordResetCode(string $email, string $code): void
    {
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();

            $mail->Host = $this->host;
            $mail->SMTPAuth = true;
            $mail->Username = $this->username;
            $mail->Password = $this->password;
            $mail->Port = $this->port;

            if ($this->encryption === 'tls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } elseif ($this->encryption === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            }

            $mail->CharSet = 'UTF-8';

            $mail->setFrom(
                $this->fromAddress,
                $this->fromName
            );

            $mail->addAddress($email);
            $mail->isHTML(true);

            $mail->Subject = 'ForumX password recovery';

            $mail->Body = "
            <h2>ForumX password recovery</h2>
            <p>Your recovery code:</p>
            <h1>{$code}</h1>
            <p>The code is valid for 15 minutes.</p>
            <p>
                If you did not request a password reset,
                please ignore this email.
            </p>
        ";

            $mail->AltBody =
                "ForumX password recovery code: {$code}\n" .
                "The code is valid for 15 minutes.";

            $mail->send();
        } catch (PHPMailerException $e) {
            throw new Exception(
                'Failed to send email: ' . $e->getMessage()
            );
        }
    }
}
