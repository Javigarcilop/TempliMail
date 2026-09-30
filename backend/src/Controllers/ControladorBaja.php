<?php

declare(strict_types=1);

namespace TempliMail\Controllers;

use TempliMail\Models\ModeloContacto;
use TempliMail\Utils\Baja;
use Throwable;

/**
 * Pagina publica de baja. Un GET solo muestra la confirmacion (asi los
 * antivirus/previsualizadores de enlaces no dan de baja a nadie sin querer);
 * el POST ejecuta la baja. El POST tambien atiende la baja "one-click"
 * (cabecera List-Unsubscribe-Post) de Gmail, Outlook, etc.
 */
class ControladorBaja
{
    public function handle(int $contactId, string $signature): void
    {
        header('Content-Type: text/html; charset=utf-8');
        header('X-Robots-Tag: noindex');

        if (!Baja::isValid($contactId, $signature)) {
            http_response_code(404);
            echo $this->page('Enlace no válido', '<p>Este enlace de baja no es válido o ha caducado.</p>');
            return;
        }

        try {
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $correo = ModeloContacto::unsubscribe($contactId);

                if ($correo === null) {
                    http_response_code(404);
                    echo $this->page('Enlace no válido', '<p>No hemos encontrado tu suscripción.</p>');
                    return;
                }

                echo $this->page(
                    'Baja confirmada',
                    '<p>Hemos dado de baja <strong>' . $this->e($correo) . '</strong>.</p>'
                    . '<p>No volverás a recibir correos nuestros.</p>'
                );
                return;
            }

            echo $this->page(
                'Darse de baja',
                '<p>¿Quieres dejar de recibir estos correos?</p>'
                . '<form method="post"><button type="submit">Confirmar baja</button></form>'
            );
        } catch (Throwable $e) {
            error_log('Baja failed: ' . $e->getMessage());
            http_response_code(500);
            echo $this->page('Error', '<p>No hemos podido procesar la baja. Inténtalo de nuevo más tarde.</p>');
        }
    }

    private function page(string $title, string $content): string
    {
        $title = $this->e($title);

        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{$title}</title>
  <style>
    body { font-family: Arial, sans-serif; background: #f4f6f8; margin: 0; padding: 48px 16px; color: #333; }
    main { max-width: 440px; margin: 0 auto; background: #fff; padding: 32px; border-radius: 8px;
           box-shadow: 0 2px 8px rgba(0,0,0,.08); text-align: center; }
    h1 { font-size: 22px; margin-top: 0; }
    button { background: #2563eb; color: #fff; border: 0; padding: 12px 24px; border-radius: 6px;
             font-size: 16px; cursor: pointer; }
  </style>
</head>
<body>
  <main>
    <h1>{$title}</h1>
    {$content}
  </main>
</body>
</html>
HTML;
    }

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
