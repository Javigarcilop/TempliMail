<?php
declare(strict_types=1);

namespace TempliMail\Middleware;

use TempliMail\Services\JwtService;
use TempliMail\Models\AuthModel;
use Throwable;

class AuthMiddleware
{
    public function __construct(
        private JwtService $jwtService
    ) {}

    public function handle(): int
    {
        $authHeader = $this->getAuthorizationHeader();

        if ($authHeader === null) {
            $this->unauthorized();
        }

        if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            $this->unauthorized();
        }

        try {
            $decoded = $this->jwtService->validate($matches[1]);

            $user = AuthModel::findById((int) $decoded->sub);

            if (
                !$user ||
                $decoded->ver !== (int) $user['token_version'] ||
                $user['deleted_at'] !== null
            ) {
                $this->unauthorized();
            }

            $_SERVER['AUTH_USER_ID'] = (int) $user['id'];

            return (int) $user['id'];

        } catch (Throwable) {
            $this->unauthorized();
        }
    }

    private function getAuthorizationHeader(): ?string
    {
        $headers = function_exists('getallheaders') ? getallheaders() : [];

        if (is_array($headers)) {
            foreach ($headers as $key => $value) {
                if (strtolower((string) $key) === 'authorization' && is_string($value) && $value !== '') {
                    return $value;
                }
            }
        }

        if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
            return (string) $_SERVER['HTTP_AUTHORIZATION'];
        }

        if (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            return (string) $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }

        return null;
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