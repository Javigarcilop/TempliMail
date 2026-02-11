<?php

namespace TempliMail\Controllers;

use TempliMail\Services\TemplateService;
use Exception;

class TemplateController
{
    public function getAll(): void
    {
        echo json_encode(
            TemplateService::getAll()
        );
    }

    public function create(): void
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            TemplateService::create($data);

            echo json_encode(['success' => true]);

        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function update(int $id): void
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            TemplateService::update($id, $data);

            echo json_encode(['success' => true]);

        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function delete(int $id): void
    {
        TemplateService::delete($id);
        echo json_encode(['success' => true]);
    }
}
