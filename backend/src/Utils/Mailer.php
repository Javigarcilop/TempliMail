<?php

namespace TempliMail\Utils;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Dotenv\Dotenv;

class Mailer
{
    public static function send(string $to, string $subject, string $body): void
    {
        $dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
        $dotenv->load();

        $mail = new PHPMailer(true);

        try {
            $mail->SMTPDebug  = 0;
            $mail->Debugoutput = 'error_log';

            $mail->isSMTP();
            $mail->Host       = $_ENV['SMTP_HOST'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $_ENV['SMTP_USER'];
            $mail->Password   = $_ENV['SMTP_PASSWORD'];
            $mail->SMTPSecure = $_ENV['SMTP_SECURE'];
            $mail->Port       = (int) $_ENV['SMTP_PORT'];

            $mail->CharSet  = 'UTF-8';
            $mail->Encoding = 'base64';

            $mail->setFrom($_ENV['SMTP_FROM'], $_ENV['SMTP_FROM_NAME']);
            $mail->addAddress($to);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $body;
            $mail->AltBody = strip_tags($body);

            if (!$mail->send()) {
                throw new Exception($mail->ErrorInfo);
            }
        } catch (\Throwable $e) {
            throw new Exception("Error enviando correo: " . $e->getMessage());
        }
    }
}
