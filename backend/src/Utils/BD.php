<?php

declare(strict_types=1);

namespace TempliMail\Utils;

use PDO;
use PDOException;
use RuntimeException;

class BD
{
    private static ?PDO $instance = null;

    public static function get(): PDO
    {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                Entorno::get('DB_HOST', 'localhost'),
                Entorno::int('DB_PORT', 3306),
                Entorno::get('DB_NAME', 'templimail_db')
            );

            try {
                self::$instance = new PDO(
                    $dsn,
                    Entorno::get('DB_USER', 'root'),
                    Entorno::get('DB_PASSWORD', ''),
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
