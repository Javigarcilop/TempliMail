<?php
declare(strict_types=1);

namespace TempliMail\Auth;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JwtService
{
    private string $secret;
    private int $ttl;

    public function __construct(string $secret, int $ttl = 3600)
    {
        $this->secret = $secret;
        $this->ttl = $ttl;
    }

    public function generate(array $user): string
    {
        $payload = [
            'iss' => 'templimail',
            'sub' => (int) $user['id'],
            'ver' => (int) $user['token_version'],
            'iat' => time(),
            'exp' => time() + $this->ttl
        ];

        return JWT::encode($payload, $this->secret, 'HS256');
    }

    public function validate(string $token): object
    {
        return JWT::decode($token, new Key($this->secret, 'HS256'));
    }
}