<?php

namespace TempliMail\Controllers;

use TempliMail\Services\ContactService;
use Exception;

class ContactController
{
    public function getAll(): void
    {
        try {
            $userId = (int) $_SERVER['AUTH_USER_ID'];

            $contacts = ContactService::getAll($userId);

            echo json_encode([
                'success' => true,
                'data'    => $contacts
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

            ContactService::create($userId, $data);

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

            ContactService::update($userId, $id, $data);

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

            ContactService::delete($userId, $id);

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