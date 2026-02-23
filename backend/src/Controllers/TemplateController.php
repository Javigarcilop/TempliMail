<?php

namespace TempliMail\Controllers;

use TempliMail\Services\TemplateService;
use Exception;

class TemplateController
{
    public function getAll(): void
    {
        try {
            $userId = (int) $_SERVER['AUTH_USER_ID'];

            $templates = TemplateService::getAll($userId);

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'data'    => $templates
            ]);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error'   => $e->getMessage()
            ]);
        }
    }

    public function create(): void
    {
        try {
            $userId = (int) $_SERVER['AUTH_USER_ID'];
            $data = json_decode(file_get_contents('php://input'), true);

            TemplateService::create($userId, $data);

            http_response_code(201);
            echo json_encode([
                'success' => true
            ]);

        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error'   => $e->getMessage()
            ]);
        }
    }

    public function update(int $id): void
    {
        try {
            $userId = (int) $_SERVER['AUTH_USER_ID'];
            $data = json_decode(file_get_contents('php://input'), true);

            TemplateService::update($userId, $id, $data);

            http_response_code(200);
            echo json_encode([
                'success' => true
            ]);

        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error'   => $e->getMessage()
            ]);
        }
    }

    public function delete(int $id): void
    {
        try {
            $userId = (int) $_SERVER['AUTH_USER_ID'];

            TemplateService::delete($userId, $id);

            http_response_code(200);
            echo json_encode([
                'success' => true
            ]);

        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error'   => $e->getMessage()
            ]);
        }
    }
}