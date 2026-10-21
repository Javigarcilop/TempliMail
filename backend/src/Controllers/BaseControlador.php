<?php

declare(strict_types=1);

namespace TempliMail\Controllers;

use TempliMail\Exceptions\ApiExcepcion;
use Throwable;

abstract class BaseControlador
{
    protected function idUsuario(): int
    {
        return (int) $_SERVER['AUTH_USER_ID'];
    }

    /** Cuerpo JSON de la peticion (array vacio si no hay o es invalido). */
    protected function cuerpoPeticion(): array
    {
        $data = json_decode((string) file_get_contents('php://input'), true);

        return is_array($data) ? $data : [];
    }

    /**
     * Ejecuta una accion y serializa el resultado como JSON.
     * - ApiExcepcion  -> su codigo HTTP y su mensaje.
     * - Otro Throwable -> se registra y se responde 500 generico (sin filtrar detalles).
     */
    protected function responder(callable $action, int $estado = 200): void
    {
        try {
            $result = $action();

            http_response_code($estado);
            echo json_encode(['success' => true] + (is_array($result) ? $result : []));
        } catch (ApiExcepcion $e) {
            http_response_code($e->obtenerEstado());
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        } catch (Throwable $e) {
            error_log(sprintf(
                '[%s] %s in %s:%d',
                static::class,
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            ));
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Error interno del servidor']);
        }
    }
}
