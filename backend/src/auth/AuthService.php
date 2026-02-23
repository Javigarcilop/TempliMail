<?php
declare(strict_types=1);

namespace TempliMail\Auth;

use TempliMail\Models\AuthModel;
use DomainException;

class AuthService
{
    public function __construct(
        private AuthModel $authModel,
        private JwtService $jwtService
    ) {}

    public function login(string $email, string $password): string
    {
        $user = $this->authModel->findByEmail($email);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            throw new DomainException('Invalid credentials');
        }

        if ($user['deleted_at'] !== null) {
            throw new DomainException('User inactive');
        }

        return $this->jwtService->generate($user);
    }

    public function logout(int $userId): void
    {
        $this->authModel->incrementTokenVersion($userId);
    }
}