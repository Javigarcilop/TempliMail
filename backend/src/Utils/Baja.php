<?php

declare(strict_types=1);

namespace TempliMail\Utils;

/**
 * Enlaces de baja firmados con HMAC: no se pueden adivinar ni falsificar
 * y no requieren guardar tokens en base de datos.
 */
class Baja
{
    /** Pie que se anade a las campanas cuyo contenido no incluye {{enlace_baja}}. */
    public const FOOTER = '<hr style="margin-top:32px;border:none;border-top:1px solid #ddd">'
        . '<p style="font-size:12px;color:#888;text-align:center;font-family:Arial,sans-serif">'
        . 'Si no deseas recibir más correos, <a href="{{enlace_baja}}" style="color:#888">'
        . 'date de baja aquí</a>.</p>';

    public static function firma(int $contactId): string
    {
        return hash_hmac('sha256', 'unsubscribe:' . $contactId, self::secreto());
    }

    public static function esValida(int $contactId, string $signature): bool
    {
        return hash_equals(self::firma($contactId), $signature);
    }

    public static function generarUrl(int $contactId): string
    {
        $base = rtrim(Entorno::obtener('PUBLIC_API_URL', 'http://localhost:8080/backend/api/index.php'), '/');

        return sprintf('%s/unsubscribe/%d/%s', $base, $contactId, self::firma($contactId));
    }

    private static function secreto(): string
    {
        return Entorno::obtener('JWT_SECRET', '');
    }
}
