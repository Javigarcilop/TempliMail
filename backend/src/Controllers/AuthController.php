<?php

namespace TempliMail\Controllers;

use TempliMail\Services\AuthService;
use Exception;

class AuthController
{
    public function login(): void
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            if (
                !$data ||
                empty($data['user']) ||
                empty($data['password'])
            ) {
                http_response_code(400);
                echo json_encode(['error' => 'Datos incompletos']);
                return;
            }

            AuthService::login(
                $data['user'],
                $data['password']
            );

            echo json_encode(['success' => true]);
        } catch (Exception $e) {

            http_response_code(401);
            echo json_encode([
                'error' => 'Credenciales incorrectas'
            ]);
        }
    }


    public function register(): void
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            if (
                !$data ||
                empty($data['user']) ||
                empty($data['password'])
            ) {
                http_response_code(400);
                echo json_encode(['error' => 'Datos incompletos']);
                return;
            }

            AuthService::registerUser(
                $data['user'],
                $data['password']
            );

            http_response_code(201);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {

            http_response_code(400);
            echo json_encode([
                'error' => $e->getMessage()
            ]);
        }
    }
}
