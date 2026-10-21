<?php
declare(strict_types=1);

namespace TempliMail\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JwtServicio
{
    private string $secret;
    private int $ttl;

    public function __construct(string $secret, int $ttl = 28800)
    {
        $this->secret = $secret;
        $this->ttl = $ttl;
    }

    public function generar(array $user): string
    {
        $payload = [
            'iss' => 'templimail',
            'sub' => (int) $user['id'],
            'ver' => (int) $user['version_token'],
            'iat' => time(),
            'exp' => time() + $this->ttl
        ];

        return JWT::encode($payload, $this->secret, 'HS256');
    }

    public function validar(string $token): object
    {
        return JWT::decode($token, new Key($this->secret, 'HS256'));
    }
}