<?php

namespace TempliMail\Controllers;

use TempliMail\Services\UserService;
use Exception;

class UserController
{
    public function login(): void
    {
        try {
            $raw = file_get_contents('php://input');
            $data = json_decode($raw, true);

            // Validación robusta
            if (
                !$data ||
                empty($data['usuario']) ||
                empty($data['password'])
            ) {
                http_response_code(400);
                echo json_encode(['error' => 'Datos incompletos']);
                return;
            }

            UserService::login(
                $data['usuario'],
                $data['password']
            );

            http_response_code(200);
            echo json_encode([
                'success' => true
            ]);

        } catch (Exception $e) {

            http_response_code(401);
            echo json_encode([
                'error' => 'Credenciales incorrectas'
            ]);
        }
    }
}
