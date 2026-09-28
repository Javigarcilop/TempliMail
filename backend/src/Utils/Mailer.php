<?php

declare(strict_types=1);

namespace TempliMail\Utils;

use PHPMailer\PHPMailer\PHPMailer;
use Exception;

/**
 * Envoltorio de PHPMailer. Una instancia mantiene abierta la conexion SMTP
 * entre mensajes (keep-alive), lo que acelera mucho las campanas masivas.
 */
class Mailer
{
    private ?PHPMailer $mail = null;

    public function __construct(private bool $keepAlive = false) {}

    /**
     * @param array<string,string> $headers cabeceras extra (List-Unsubscribe...)
     * @throws Exception si el servidor SMTP rechaza el mensaje
     */
    public function send(string $to, string $subject, string $htmlBody, array $headers = []): void
    {
        $mail = $this->connection();

        try {
            $mail->clearAllRecipients();
            $mail->clearCustomHeaders();

            $mail->addAddress($to);
            $mail->Subject = $subject;
            $mail->Body    = $htmlBody;
            $mail->AltBody = trim(html_entity_decode(strip_tags($htmlBody)));

            foreach ($headers as $name => $value) {
                $mail->addCustomHeader($name, $value);
            }

            $mail->send();
        } catch (\Throwable $e) {
            // Fuerza una conexion nueva en el siguiente envio
            $this->close();
            throw new Exception('Error enviando correo: ' . $e->getMessage());
        }
    }

    /** Cierra la conexion SMTP (si hay una abierta). */
    public function close(): void
    {
        if ($this->mail !== null) {
            try {
                $this->mail->smtpClose();
            } catch (\Throwable) {
                // nada que hacer
            }
            $this->mail = null;
        }
    }

    /** Envio unico (abre y cierra la conexion). */
    public static function sendOnce(string $to, string $subject, string $htmlBody, array $headers = []): void
    {
        $mailer = new self(false);

        try {
            $mailer->send($to, $subject, $htmlBody, $headers);
        } finally {
            $mailer->close();
        }
    }

    private function connection(): PHPMailer
    {
        if ($this->mail !== null) {
            return $this->mail;
        }

        $host = Env::get('SMTP_HOST');

        if ($host === null) {
            throw new Exception('El servidor SMTP no esta configurado');
        }

        $mail = new PHPMailer(true);

        $secure = Env::get('SMTP_SECURE', PHPMailer::ENCRYPTION_STARTTLS);
        $user   = Env::get('SMTP_USER', '');

        $mail->isSMTP();
        $mail->Host          = $host;
        $mail->SMTPAuth      = $user !== '';
        $mail->Username      = $user;
        $mail->Password      = Env::get('SMTP_PASSWORD', '');
        // SMTP_SECURE=none -> sin cifrado (buzones de prueba como Mailpit)
        $mail->SMTPSecure    = $secure === 'none' ? '' : $secure;
        $mail->SMTPAutoTLS   = $secure !== 'none';
        $mail->Port          = Env::int('SMTP_PORT', 587);
        $mail->SMTPKeepAlive = $this->keepAlive;
        $mail->Timeout       = 20;
        $mail->SMTPDebug     = 0;

        $mail->CharSet  = 'UTF-8';
        $mail->Encoding = 'base64';
        $mail->isHTML(true);

        $mail->setFrom(
            Env::get('SMTP_FROM', Env::get('SMTP_USER', 'no-reply@localhost')),
            Env::get('SMTP_FROM_NAME', 'TempliMail')
        );

        return $this->mail = $mail;
    }
}
