<?php

declare(strict_types=1);

namespace TempliMail\Controllers;

use TempliMail\Exceptions\ExcepcionApi;
use TempliMail\Services\ServicioIA;

class ControladorIA extends ControladorBase
{
    public function suggestSubjects(): void
    {
        $this->respond(function (): array {
            $topic = trim((string) ($this->body()['topic'] ?? ''));

            if ($topic === '') {
                throw new ExcepcionApi('Indica de qué trata el correo');
            }

            if (mb_strlen($topic) > 300) {
                throw new ExcepcionApi('El tema es demasiado largo (máximo 300 caracteres)');
            }

            return ['subjects' => ServicioIA::suggestSubjects($topic)];
        });
    }
}
