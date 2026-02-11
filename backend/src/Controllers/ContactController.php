<?php

namespace TempliMail\Controllers;

use TempliMail\Services\ContactService;
use Exception;

class ContactController
{
    public function getAll(): void
    {
        echo json_encode(
            ContactService::getAll()
        );
    }

    public function create(): void
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            ContactService::create($data);

            echo json_encode(['success' => true]);

        } catch (Exception $e) {

            http_response_code(400);
            echo json_encode([
                'error' => $e->getMessage()
            ]);
        }
    }

    public function update(int $id): void
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            ContactService::update($id, $data);

            echo json_encode(['success' => true]);

        } catch (Exception $e) {

            http_response_code(400);
            echo json_encode([
                'error' => $e->getMessage()
            ]);
        }
    }

    public function delete(int $id): void
    {
        ContactService::delete($id);

        echo json_encode(['success' => true]);
    }
}
