<?php

declare(strict_types=1);

/**
 * Worker de envio: procesa las campanas en cola y las programadas cuya hora ha llegado.
 *
 *   php backend/bin/worker.php          # bucle infinito (lo lanza docker compose)
 *   php backend/bin/worker.php --once   # una sola pasada y termina
 */

require_once __DIR__ . '/../bootstrap.php';

use TempliMail\Services\ServicioCorreo;
use TempliMail\Utils\Entorno;

$once     = in_array('--once', $argv, true);
$interval = max(1, Entorno::int('WORKER_INTERVAL_SECONDS', 5));

$log = static fn(string $message) => fwrite(STDOUT, sprintf("[%s] %s\n", date('Y-m-d H:i:s'), $message));

$log('Worker iniciado' . ($once ? ' (una pasada)' : ''));

do {
    try {
        $processed = ServicioCorreo::processDue();

        if ($processed > 0) {
            $log("Campañas enviadas: {$processed}");
        }
    } catch (Throwable $e) {
        // Un fallo (p. ej. la base de datos aun arrancando) no debe matar el worker
        $log('Error: ' . $e->getMessage());
        error_log(sprintf('Worker: %s in %s:%d', $e->getMessage(), $e->getFile(), $e->getLine()));
    }

    if (!$once) {
        sleep($interval);
    }
} while (!$once);
