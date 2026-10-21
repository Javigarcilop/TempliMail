<?php

declare(strict_types=1);

namespace TempliMail\Utils;

class Entorno
{
    public static function obtener(string $key, ?string $default = null): ?string
    {
        $value = $_ENV[$key] ?? getenv($key);

        if ($value === false || $value === null || $value === '') {
            return $default;
        }

        return (string) $value;
    }

    public static function entero(string $key, int $default): int
    {
        $value = self::obtener($key);

        return $value === null ? $default : (int) $value;
    }
}
