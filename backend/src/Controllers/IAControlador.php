<?php

declare(strict_types=1);

namespace TempliMail\Controllers;

use TempliMail\Exceptions\ApiExcepcion;
use TempliMail\Services\IAServicio;

class IAControlador extends BaseControlador
{
    public function sugerirAsuntos(): void
    {
        $this->responder(function (): array {
            $topic = trim((string) ($this->cuerpoPeticion()['topic'] ?? ''));

            if ($topic === '') {
                throw new ApiExcepcion('Indica de qué trata el correo');
            }

            if (mb_strlen($topic) > 300) {
                throw new ApiExcepcion('El tema es demasiado largo (máximo 300 caracteres)');
            }

            return ['subjects' => IAServicio::sugerirAsuntos($topic)];
        });
    }
}
