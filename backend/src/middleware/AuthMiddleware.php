<?php
declare(strict_types=1);

namespace TempliMail\Middleware;

use TempliMail\Auth\JwtService;
use TempliMail\Models\AuthModel;
use Throwable;

class AuthMiddleware
{
    public function __construct(
        private JwtService $jwtService
    ) {}

    public function handle(): int
    {
        $headers = getallheaders();

        if (!isset($headers['Authorization'])) {
            $this->unauthorized();
        }

        if (!preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $matches)) {
            $this->unauthorized();
        }

        try {
            $decoded = $this->jwtService->validate($matches[1]);

            // 🔥 Ahora usamos AuthModel estático
            $user = AuthModel::findById((int) $decoded->sub);

            if (
                !$user ||
                $decoded->ver !== (int) $user['token_version'] ||
                $user['deleted_at'] !== null
            ) {
                $this->unauthorized();
            }

            // Inyectamos el usuario autenticado
            $_SERVER['AUTH_USER_ID'] = (int) $user['id'];

            return (int) $user['id'];

        } catch (Throwable) {
            $this->unauthorized();
        }
    }

    private function unauthorized(): never
    {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error'   => 'Unauthorized'
        ]);
        exit;
    }
}