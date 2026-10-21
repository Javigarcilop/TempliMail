<?php

declare(strict_types=1);

namespace TempliMail\Utils;

use PDO;
use PDOException;
use RuntimeException;

class BD
{
    private static ?PDO $instance = null;

    public static function obtener(): PDO
    {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                Entorno::obtener('DB_HOST', 'localhost'),
                Entorno::entero('DB_PORT', 3306),
                Entorno::obtener('DB_NAME', 'templimail_db')
            );

            try {
                self::$instance = new PDO(
                    $dsn,
                    Entorno::obtener('DB_USER', 'root'),
                    Entorno::obtener('DB_PASSWORD', ''),
                    [
                        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        // Todas las fechas se guardan y comparan en UTC
                        PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+00:00'",
                    ]
                );
            } catch (PDOException $e) {
                error_log('BD connection failed: ' . $e->getMessage());
                throw new RuntimeException('Database connection failed');
            }
        }

        return self::$instance;
    }
}
