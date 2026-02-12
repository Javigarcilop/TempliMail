<?php

namespace TempliMail\Controllers;

use TempliMail\Services\UserService;
use Exception;

class UserController
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

        UserService::login(
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

}
