<?php

declare(strict_types=1);

namespace TempliMail\Controllers;

use TempliMail\Exceptions\ApiExcepcion;
use TempliMail\Services\AutenticacionServicio;
use TempliMail\Services\JwtServicio;
use TempliMail\Utils\Entorno;

class AutenticacionControlador extends BaseControlador
{
    public function login(): void
    {
        $this->respond(function (): array {
            $data = $this->body();

            if (empty($data['nombre_usuario']) || empty($data['contrasena'])) {
                throw new ApiExcepcion('Usuario y contraseña son obligatorios');
            }

            $token = AutenticacionServicio::login(
                (string) $data['nombre_usuario'],
                (string) $data['contrasena'],
                new JwtServicio(Entorno::get('JWT_SECRET', ''))
            );

            return ['token' => $token];
        });
    }

    public function register(): void
    {
        $this->respond(function (): array {
            $data = $this->body();

            AutenticacionServicio::register(
                (string) ($data['nombre_usuario'] ?? ''),
                (string) ($data['correo'] ?? ''),
                (string) ($data['contrasena'] ?? '')
            );

            return ['message' => 'Usuario creado correctamente'];
        }, 201);
    }

    public function me(): void
    {
        $this->respond(fn(): array => ['data' => AutenticacionServicio::currentUser($this->userId())]);
    }

    /** PUT /me  {"correo": "..."} */
    public function updateProfile(): void
    {
        $this->respond(fn(): array => [
            'data' => AutenticacionServicio::updateProfile($this->userId(), (string) ($this->body()['correo'] ?? '')),
        ]);
    }

    /** PUT /me/contrasena  {"contrasena_actual": "...", "contrasena_nueva": "..."} */
    public function changePassword(): void
    {
        $this->respond(function (): array {
            $data = $this->body();

            $token = AutenticacionServicio::changePassword(
                $this->userId(),
                (string) ($data['contrasena_actual'] ?? ''),
                (string) ($data['contrasena_nueva'] ?? ''),
                new JwtServicio(Entorno::get('JWT_SECRET', ''))
            );

            return ['token' => $token];
        });
    }

    public function logout(): void
    {
        $this->respond(function (): array {
            AutenticacionServicio::logout($this->userId());

            return [];
        });
    }
}
