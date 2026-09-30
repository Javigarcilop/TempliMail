<?php

declare(strict_types=1);

/**
 * Comprueba la configuracion SMTP enviando un correo de prueba.
 *
 *   php backend/bin/test_mail.php destinatario@ejemplo.com
 */

require_once __DIR__ . '/../bootstrap.php';

use TempliMail\Utils\EnviadorCorreo;

$to = $argv[1] ?? null;

if ($to === null || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Uso: php backend/bin/test_mail.php destinatario@ejemplo.com\n");
    exit(1);
}

try {
    EnviadorCorreo::sendOnce($to, 'Prueba SMTP TempliMail', '<h1>Si lees esto, el SMTP funciona</h1>');
    echo "Correo enviado a {$to}\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
    exit(1);
}
