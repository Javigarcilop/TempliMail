<?php

declare(strict_types=1);

namespace TempliMail\Controllers;

use TempliMail\Exceptions\ApiException;
use TempliMail\Services\AiService;

class AiController extends BaseController
{
    public function suggestSubjects(): void
    {
        $this->respond(function (): array {
            $topic = trim((string) ($this->body()['topic'] ?? ''));

            if ($topic === '') {
                throw new ApiException('Indica de qué trata el correo');
            }

            if (mb_strlen($topic) > 300) {
                throw new ApiException('El tema es demasiado largo (máximo 300 caracteres)');
            }

            return ['subjects' => AiService::suggestSubjects($topic)];
        });
    }
}
