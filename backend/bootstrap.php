<?php

declare(strict_types=1);

/**
 * Arranque comun de la aplicacion (API HTTP y worker CLI):
 * autoload de Composer, variables de entorno y ajustes globales de PHP.
 */

require_once __DIR__ . '/../vendor/autoload.php';

Dotenv\Dotenv::createImmutable(__DIR__)->safeLoad();

date_default_timezone_set('UTC');

// Los errores nunca se muestran al cliente: se registran en el log del servidor.
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);
