<?php

require_once __DIR__ . '/../services/UserService.php';

class UserController
{
    public function login(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['user'], $data['password'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Datos incompletos']);
            return;
        }

        try {
            UserService::login($data['user'], $data['password']);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            http_response_code(401);
            echo json_encode(['error' => 'Credenciales incorrectas']);
        }
    }
}
