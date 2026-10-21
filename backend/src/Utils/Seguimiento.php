<?php

declare(strict_types=1);

namespace TempliMail\Utils;

/**
 * Enlaces de seguimiento firmados con HMAC (apertura y clics), en la misma
 * linea que Baja: no hace falta guardar tokens en la base de datos y no se
 * pueden adivinar ni falsificar.
 *
 * La firma del clic esta atada a la entrega Y a la URL exacta de destino, asi
 * que cambiar el parametro "url" invalida la firma: el enlace no sirve para
 * redirigir a un sitio distinto del original (sin esto, un clic-tracker es un
 * open redirect de libro).
 */
class Seguimiento
{
    public static function openSignature(int $deliveryId): string
    {
        return hash_hmac('sha256', 'track-open:' . $deliveryId, self::secret());
    }

    public static function isValidOpenSignature(int $deliveryId, string $signature): bool
    {
        return hash_equals(self::openSignature($deliveryId), $signature);
    }

    public static function clickSignature(int $deliveryId, string $url): string
    {
        return hash_hmac('sha256', 'track-click:' . $deliveryId . ':' . $url, self::secret());
    }

    public static function isValidClickSignature(int $deliveryId, string $url, string $signature): bool
    {
        return hash_equals(self::clickSignature($deliveryId, $url), $signature);
    }

    public static function pixelUrl(int $deliveryId): string
    {
        return sprintf('%s/t/%d/%s.gif', self::base(), $deliveryId, self::openSignature($deliveryId));
    }

    public static function clickUrl(int $deliveryId, string $url): string
    {
        return sprintf(
            '%s/t/%d/%s/click?url=%s',
            self::base(),
            $deliveryId,
            self::clickSignature($deliveryId, $url),
            rawurlencode($url)
        );
    }

    private static function base(): string
    {
        return rtrim(Entorno::get('PUBLIC_API_URL', 'http://localhost:8080/backend/api/index.php'), '/');
    }

    private static function secret(): string
    {
        return Entorno::get('JWT_SECRET', '');
    }
}
