<?php

namespace TempliMail\Utils;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;


class Mailer
{
    public static function send(string $to, string $subject, string $body)
    {
        $mail = new PHPMailer(true);

        try {
            $mail->SMTPDebug  = 2;
            $mail->Debugoutput = 'error_log';

            // Configuración SMTP
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USER;
            $mail->Password   = SMTP_PASS;
            $mail->SMTPSecure = SMTP_SECURE;
            $mail->Port       = SMTP_PORT;

            // Codificación
            $mail->CharSet  = 'UTF-8';
            $mail->Encoding = 'base64';

            // Remitente y destinatario
            $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
            $mail->addAddress($to);

            // Contenido
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $body;
            $mail->AltBody = strip_tags($body);

            $mail->send();
            return true;

        } catch (Exception $e) {
            return $mail->ErrorInfo; 
        }
    }
}
