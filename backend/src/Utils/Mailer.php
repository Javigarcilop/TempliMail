<?php

namespace TempliMail\Utils;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require_once __DIR__ . '/smtp_config.php';



class Mailer
{
    public static function send(string $to, string $subject, string $body): void
    {
        $mail = new PHPMailer(true);

        try {
            $mail->SMTPDebug  = 0; 
            $mail->Debugoutput = 'error_log';

            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USER;
            $mail->Password   = SMTP_PASS;
            $mail->SMTPSecure = SMTP_SECURE;
            $mail->Port       = SMTP_PORT;

            $mail->CharSet  = 'UTF-8';
            $mail->Encoding = 'base64';

            $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
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
