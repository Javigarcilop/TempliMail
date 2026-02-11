<?php

namespace TempliMail\Controllers;

use TempliMail\Services\UploadTemplateService;
use Exception;

class UploadTemplateController
{
    public function handleUpload(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!isset($_FILES['file'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'No se recibió ningún archivo.'
            ]);
            return;
        }

        try {
            $html = UploadTemplateService::process($_FILES['file']);

            echo json_encode([
                'success' => true,
                'html' => $html
            ]);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
}
