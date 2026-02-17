<?php

namespace TempliMail\Controllers;

use TempliMail\Services\AuthService;
use Exception;

class AuthController
{
    /**
     * Login user
     */
    public function login(): void
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            if (
                !$data ||
                empty($data['username']) ||
                empty($data['password'])
            ) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error'   => 'Incomplete data'
                ]);
                return;
            }

            $user = AuthService::login(
                $data['username'],
                $data['password']
            );

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'user'    => $user
            ]);

        } catch (Exception $e) {

            http_response_code(401);
            echo json_encode([
                'success' => false,
                'error'   => $e->getMessage()
            ]);
        }
    }

    /**
     * Register new user
     */
    public function register(): void
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            if (
                !$data ||
                empty($data['username']) ||
                empty($data['email']) ||
                empty($data['password'])
            ) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error'   => 'Incomplete data'
                ]);
                return;
            }

            AuthService::register(
                $data['username'],
                $data['email'],
                $data['password']
            );

            http_response_code(201);
            echo json_encode([
                'success' => true,
                'message' => 'User created successfully'
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
