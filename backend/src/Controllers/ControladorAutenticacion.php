<?php

declare(strict_types=1);

namespace TempliMail\Controllers;

use TempliMail\Exceptions\ExcepcionApi;
use TempliMail\Services\ServicioAutenticacion;
use TempliMail\Services\ServicioJwt;
use TempliMail\Utils\Entorno;

class ControladorAutenticacion extends ControladorBase
{
    public function login(): void
    {
        $this->respond(function (): array {
            $data = $this->body();

            if (empty($data['nombre_usuario']) || empty($data['contrasena'])) {
                throw new ExcepcionApi('Usuario y contraseña son obligatorios');
            }

            $token = ServicioAutenticacion::login(
                (string) $data['nombre_usuario'],
                (string) $data['contrasena'],
                new ServicioJwt(Entorno::get('JWT_SECRET', ''))
            );

            return ['token' => $token];
        });
    }

    public function register(): void
    {
        $this->respond(function (): array {
            $data = $this->body();

            ServicioAutenticacion::register(
                (string) ($data['nombre_usuario'] ?? ''),
                (string) ($data['correo'] ?? ''),
                (string) ($data['contrasena'] ?? '')
            );

            return ['message' => 'Usuario creado correctamente'];
        }, 201);
    }

    public function me(): void
    {
        $this->respond(fn(): array => ['data' => ServicioAutenticacion::currentUser($this->userId())]);
    }

    /** PUT /me  {"correo": "..."} */
    public function updateProfile(): void
    {
        $this->respond(fn(): array => [
            'data' => ServicioAutenticacion::updateProfile($this->userId(), (string) ($this->body()['correo'] ?? '')),
        ]);
    }

    /** PUT /me/contrasena  {"contrasena_actual": "...", "contrasena_nueva": "..."} */
    public function changePassword(): void
    {
        $this->respond(function (): array {
            $data = $this->body();

            $token = ServicioAutenticacion::changePassword(
                $this->userId(),
                (string) ($data['contrasena_actual'] ?? ''),
                (string) ($data['contrasena_nueva'] ?? ''),
                new ServicioJwt(Entorno::get('JWT_SECRET', ''))
            );

            return ['token' => $token];
        });
    }

    public function logout(): void
    {
        $this->respond(function (): array {
            ServicioAutenticacion::logout($this->userId());

            return [];
        });
    }
}
