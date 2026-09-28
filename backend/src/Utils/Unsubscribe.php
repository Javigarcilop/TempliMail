<?php

declare(strict_types=1);

namespace TempliMail\Utils;

/**
 * Enlaces de baja firmados con HMAC: no se pueden adivinar ni falsificar
 * y no requieren guardar tokens en base de datos.
 */
class Unsubscribe
{
    /** Pie que se anade a las campanas cuyo contenido no incluye {{unsubscribe_url}}. */
    public const FOOTER = '<hr style="margin-top:32px;border:none;border-top:1px solid #ddd">'
        . '<p style="font-size:12px;color:#888;text-align:center;font-family:Arial,sans-serif">'
        . 'Si no deseas recibir más correos, <a href="{{unsubscribe_url}}" style="color:#888">'
        . 'date de baja aquí</a>.</p>';

    public static function signature(int $contactId): string
    {
        return hash_hmac('sha256', 'unsubscribe:' . $contactId, self::secret());
    }

    public static function isValid(int $contactId, string $signature): bool
    {
        return hash_equals(self::signature($contactId), $signature);
    }

    public static function url(int $contactId): string
    {
        $base = rtrim(Env::get('PUBLIC_API_URL', 'http://localhost:8080/backend/api/index.php'), '/');

        return sprintf('%s/unsubscribe/%d/%s', $base, $contactId, self::signature($contactId));
    }

    private static function secret(): string
    {
        return Env::get('JWT_SECRET', '');
    }
}
