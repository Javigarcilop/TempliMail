<?php

declare(strict_types=1);

namespace TempliMail\Middleware;

use DomainException;
use InvalidArgumentException;
use TempliMail\Models\AutenticacionModelo;
use TempliMail\Services\JwtServicio;
use UnexpectedValueException;

class AutenticacionMiddleware
{
    public function __construct(
        private JwtServicio $jwtService
    ) {}

    public function autenticar(): int
    {
        $authHeader = $this->obtenerCabeceraAutorizacion();

        if ($authHeader === null || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            $this->noAutorizado();
        }

        try {
            $decoded = $this->jwtService->validar($matches[1]);
        } catch (UnexpectedValueException | DomainException | InvalidArgumentException) {
            // Token malformado, con firma invalida o caducado.
            // (Otros errores, p. ej. la base de datos caida, NO son un 401: se propagan.)
            $this->noAutorizado();
        }

        $user = AutenticacionModelo::buscarPorId((int) $decoded->sub);

        if (
            !$user ||
            (int) $decoded->ver !== (int) $user['version_token'] ||
            $user['eliminado_en'] !== null
        ) {
            $this->noAutorizado();
        }

        $_SERVER['AUTH_USER_ID'] = (int) $user['id'];

        return (int) $user['id'];
    }

    private function obtenerCabeceraAutorizacion(): ?string
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

    private function noAutorizado(): never
    {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error'   => 'No autorizado',
        ]);
        exit;
    }
}
