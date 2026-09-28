<?php

declare(strict_types=1);

namespace TempliMail\Controllers;

use TempliMail\Exceptions\ApiException;
use TempliMail\Services\AuthService;
use TempliMail\Services\JwtService;
use TempliMail\Utils\Env;

class AuthController extends BaseController
{
    public function login(): void
    {
        $this->respond(function (): array {
            $data = $this->body();

            if (empty($data['username']) || empty($data['password'])) {
                throw new ApiException('Usuario y contraseña son obligatorios');
            }

            $token = AuthService::login(
                (string) $data['username'],
                (string) $data['password'],
                new JwtService(Env::get('JWT_SECRET', ''))
            );

            return ['token' => $token];
        });
    }

    public function register(): void
    {
        $this->respond(function (): array {
            $data = $this->body();

            AuthService::register(
                (string) ($data['username'] ?? ''),
                (string) ($data['email'] ?? ''),
                (string) ($data['password'] ?? '')
            );

            return ['message' => 'Usuario creado correctamente'];
        }, 201);
    }

    public function me(): void
    {
        $this->respond(fn(): array => ['data' => AuthService::currentUser($this->userId())]);
    }

    public function logout(): void
    {
        $this->respond(function (): array {
            AuthService::logout($this->userId());

            return [];
        });
    }
}
