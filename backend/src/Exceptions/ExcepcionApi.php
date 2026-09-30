<?php

declare(strict_types=1);

namespace TempliMail\Exceptions;

use RuntimeException;

/**
 * Error "esperado" cuyo mensaje SI puede mostrarse al cliente
 * (validacion, recurso inexistente, credenciales invalidas...).
 * Cualquier otra excepcion se registra en el log y se responde con un 500 generico.
 */
class ExcepcionApi extends RuntimeException
{
    public function __construct(
        string $message,
        private int $estado = 400
    ) {
        parent::__construct($message);
    }

    public function getStatus(): int
    {
        return $this->estado;
    }

    public static function notFound(string $message = 'Recurso no encontrado'): self
    {
        return new self($message, 404);
    }

    public static function conflict(string $message): self
    {
        return new self($message, 409);
    }
}
