<?php

declare(strict_types=1);

namespace TempliMail\Controllers;

use TempliMail\Exceptions\ApiExcepcion;
use TempliMail\Services\AutenticacionServicio;
use TempliMail\Services\JwtServicio;
use TempliMail\Utils\Entorno;

class AutenticacionControlador extends BaseControlador
{
    public function iniciarSesion(): void
    {
        $this->responder(function (): array {
            $data = $this->cuerpoPeticion();

            if (empty($data['nombre_usuario']) || empty($data['contrasena'])) {
                throw new ApiExcepcion('Usuario y contraseña son obligatorios');
            }

            $token = AutenticacionServicio::iniciarSesion(
                (string) $data['nombre_usuario'],
                (string) $data['contrasena'],
                new JwtServicio(Entorno::obtener('JWT_SECRET', ''))
            );

            return ['token' => $token];
        });
    }

    public function registrar(): void
    {
        $this->responder(function (): array {
            $data = $this->cuerpoPeticion();

            AutenticacionServicio::registrar(
                (string) ($data['nombre_usuario'] ?? ''),
                (string) ($data['correo'] ?? ''),
                (string) ($data['contrasena'] ?? '')
            );

            return ['message' => 'Usuario creado correctamente'];
        }, 201);
    }

    public function obtenerPerfil(): void
    {
        $this->responder(fn(): array => ['data' => AutenticacionServicio::usuarioActual($this->idUsuario())]);
    }

    /** PUT /me  {"correo": "..."} */
    public function actualizarPerfil(): void
    {
        $this->responder(fn(): array => [
            'data' => AutenticacionServicio::actualizarPerfil($this->idUsuario(), (string) ($this->cuerpoPeticion()['correo'] ?? '')),
        ]);
    }

    /** PUT /me/contrasena  {"contrasena_actual": "...", "contrasena_nueva": "..."} */
    public function cambiarContrasena(): void
    {
        $this->responder(function (): array {
            $data = $this->cuerpoPeticion();

            $token = AutenticacionServicio::cambiarContrasena(
                $this->idUsuario(),
                (string) ($data['contrasena_actual'] ?? ''),
                (string) ($data['contrasena_nueva'] ?? ''),
                new JwtServicio(Entorno::obtener('JWT_SECRET', ''))
            );

            return ['token' => $token];
        });
    }

    public function cerrarSesion(): void
    {
        $this->responder(function (): array {
            AutenticacionServicio::cerrarSesion($this->idUsuario());

            return [];
        });
    }
}
