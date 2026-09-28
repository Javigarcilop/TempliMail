<?php

declare(strict_types=1);

namespace TempliMail\Controllers;

use TempliMail\Exceptions\ApiException;
use TempliMail\Services\UploadTemplateService;

class UploadTemplateController extends BaseController
{
    public function handleUpload(): void
    {
        $this->respond(function (): array {
            if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
                throw new ApiException('No se recibió ningún archivo.');
            }

            return ['html' => UploadTemplateService::process($_FILES['file'])];
        });
    }
}
