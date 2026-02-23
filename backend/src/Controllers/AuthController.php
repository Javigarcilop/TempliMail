<?php

namespace TempliMail\Controllers;

use TempliMail\Auth\JwtService;
use TempliMail\Models\AuthModel;
use Exception;

class AuthController
{
    private JwtService $jwtService;

    public function __construct()
    {
        $secret = $_ENV['JWT_SECRET'] ?? 'dev_secret_change_this';
        $this->jwtService = new JwtService($secret);
    }

    public function login(): void
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            if (
                !$data ||
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

            $user = AuthModel::findByEmail($data['email']);

            if (!$user || !password_verify($data['password'], $user['password_hash'])) {
                throw new Exception('Invalid credentials');
            }

            if ($user['deleted_at'] !== null) {
                throw new Exception('User inactive');
            }

            $token = $this->jwtService->generate($user);

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'token'   => $token
            ]);

        } catch (Exception $e) {

            http_response_code(401);
            echo json_encode([
                'success' => false,
                'error'   => $e->getMessage()
            ]);
        }
    }

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

            AuthModel::create(
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