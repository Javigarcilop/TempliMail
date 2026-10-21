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
    public static function firmaApertura(int $deliveryId): string
    {
        return hash_hmac('sha256', 'track-open:' . $deliveryId, self::secreto());
    }

    public static function esFirmaAperturaValida(int $deliveryId, string $signature): bool
    {
        return hash_equals(self::firmaApertura($deliveryId), $signature);
    }

    public static function firmaClic(int $deliveryId, string $url): string
    {
        return hash_hmac('sha256', 'track-click:' . $deliveryId . ':' . $url, self::secreto());
    }

    public static function esFirmaClicValida(int $deliveryId, string $url, string $signature): bool
    {
        return hash_equals(self::firmaClic($deliveryId, $url), $signature);
    }

    public static function generarUrlPixel(int $deliveryId): string
    {
        return sprintf('%s/t/%d/%s.gif', self::urlBase(), $deliveryId, self::firmaApertura($deliveryId));
    }

    public static function generarUrlClic(int $deliveryId, string $url): string
    {
        return sprintf(
            '%s/t/%d/%s/click?url=%s',
            self::urlBase(),
            $deliveryId,
            self::firmaClic($deliveryId, $url),
            rawurlencode($url)
        );
    }

    private static function urlBase(): string
    {
        return rtrim(Entorno::obtener('PUBLIC_API_URL', 'http://localhost:8080/backend/api/index.php'), '/');
    }

    private static function secreto(): string
    {
        return Entorno::obtener('JWT_SECRET', '');
    }
}
