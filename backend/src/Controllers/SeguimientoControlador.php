<?php

declare(strict_types=1);

namespace TempliMail\Controllers;

use TempliMail\Models\EntregaCorreoModelo;
use TempliMail\Utils\Seguimiento;
use Throwable;

/**
 * Endpoints publicos de seguimiento: el pixel de apertura y la redireccion de
 * clics incrustados en cada correo enviado. Los visita el cliente de correo o
 * el navegador del destinatario, nunca el frontend, asi que no llevan JWT.
 */
class SeguimientoControlador
{
    /** GIF transparente de 1x1 (43 bytes): el estandar de facto para pixeles de seguimiento. */
    private const PIXEL_GIF = "\x47\x49\x46\x38\x39\x61\x01\x00\x01\x00\x80\x00\x00\x00\x00\x00"
        . "\xff\xff\xff\x21\xf9\x04\x01\x00\x00\x00\x00\x2c\x00\x00\x00\x00"
        . "\x01\x00\x01\x00\x00\x02\x01\x44\x00\x3b";

    public function pixel(int $deliveryId, string $signature): void
    {
        try {
            if (Seguimiento::esFirmaAperturaValida($deliveryId, $signature)) {
                EntregaCorreoModelo::registrarApertura($deliveryId);
            }
        } catch (Throwable $e) {
            error_log('Seguimiento (apertura) fallo: ' . $e->getMessage());
        }

        // Se responde SIEMPRE con el pixel, firma valida o no: un error visible
        // delataria al destinatario que se estaba midiendo la apertura de su correo.
        http_response_code(200);
        header('Content-Type: image/gif');
        header('Content-Length: ' . strlen(self::PIXEL_GIF));
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('X-Robots-Tag: noindex');
        echo self::PIXEL_GIF;
    }

    public function clic(int $deliveryId, string $signature): void
    {
        header('X-Robots-Tag: noindex');

        $url = (string) ($_GET['url'] ?? '');

        if (
            $url === '' ||
            !Seguimiento::esFirmaClicValida($deliveryId, $url, $signature) ||
            !self::esUrlSegura($url)
        ) {
            http_response_code(404);
            header('Content-Type: text/html; charset=utf-8');
            echo '<!DOCTYPE html><meta charset="utf-8"><title>Enlace no válido</title>'
                . '<p style="font-family:Arial,sans-serif">Este enlace no es válido o ha caducado.</p>';
            return;
        }

        try {
            EntregaCorreoModelo::registrarClic($deliveryId);
        } catch (Throwable $e) {
            error_log('Seguimiento (clic) fallo: ' . $e->getMessage());
        }

        http_response_code(302);
        header('Location: ' . $url);
    }

    /** Solo se redirige a enlaces http(s) absolutos (nunca javascript:, data:, etc.). */
    private static function esUrlSegura(string $url): bool
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        return in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true);
    }
}
