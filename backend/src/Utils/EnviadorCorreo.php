<?php

declare(strict_types=1);

namespace TempliMail\Utils;

use PHPMailer\PHPMailer\PHPMailer;
use Exception;

/**
 * Envoltorio de PHPMailer. Una instancia mantiene abierta la conexion SMTP
 * entre mensajes (keep-alive), lo que acelera mucho las campanas masivas.
 */
class EnviadorCorreo
{
    private ?PHPMailer $mail = null;

    public function __construct(private bool $keepAlive = false) {}

    /**
     * @param array<string,string> $headers cabeceras extra (List-Unsubscribe...)
     * @throws Exception si el servidor SMTP rechaza el mensaje
     */
    public function enviar(string $to, string $asunto, string $htmlBody, array $headers = []): void
    {
        $mail = $this->conexion();

        try {
            $mail->clearAllRecipients();
            $mail->clearCustomHeaders();

            $mail->addAddress($to);
            $mail->Subject = $asunto;
            $mail->Body    = $htmlBody;
            $mail->AltBody = trim(html_entity_decode(strip_tags($htmlBody)));

            foreach ($headers as $nombre => $value) {
                $mail->addCustomHeader($nombre, $value);
            }

            $mail->send();
        } catch (\Throwable $e) {
            // Fuerza una conexion nueva en el siguiente envio
            $this->cerrar();
            throw new Exception('Error enviando correo: ' . $e->getMessage());
        }
    }

    /** Cierra la conexion SMTP (si hay una abierta). */
    public function cerrar(): void
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
    public static function enviarUnaVez(string $to, string $asunto, string $htmlBody, array $headers = []): void
    {
        $mailer = new self(false);

        try {
            $mailer->enviar($to, $asunto, $htmlBody, $headers);
        } finally {
            $mailer->cerrar();
        }
    }

    private function conexion(): PHPMailer
    {
        if ($this->mail !== null) {
            return $this->mail;
        }

        $host = Entorno::obtener('SMTP_HOST');

        if ($host === null) {
            throw new Exception('El servidor SMTP no esta configurado');
        }

        $mail = new PHPMailer(true);

        $secure = Entorno::obtener('SMTP_SECURE', PHPMailer::ENCRYPTION_STARTTLS);
        $user   = Entorno::obtener('SMTP_USER', '');

        $mail->isSMTP();
        $mail->Host          = $host;
        $mail->SMTPAuth      = $user !== '';
        $mail->Username      = $user;
        $mail->Password      = Entorno::obtener('SMTP_PASSWORD', '');
        // SMTP_SECURE=none -> sin cifrado (buzones de prueba como Mailpit)
        $mail->SMTPSecure    = $secure === 'none' ? '' : $secure;
        $mail->SMTPAutoTLS   = $secure !== 'none';
        $mail->Port          = Entorno::entero('SMTP_PORT', 587);
        $mail->SMTPKeepAlive = $this->keepAlive;
        $mail->Timeout       = 20;
        $mail->SMTPDebug     = 0;

        $mail->CharSet  = 'UTF-8';
        $mail->Encoding = 'base64';
        $mail->isHTML(true);

        $mail->setFrom(
            Entorno::obtener('SMTP_FROM', Entorno::obtener('SMTP_USER', 'no-reply@localhost')),
            Entorno::obtener('SMTP_FROM_NAME', 'TempliMail')
        );

        return $this->mail = $mail;
    }
}
