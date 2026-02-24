<?php

namespace TempliMail\Services;

use TempliMail\Models\AuthModel;
use TempliMail\Auth\JwtService;
use Exception;
use DomainException;

class AuthService
{
    public static function register(string $username, string $email, string $password): void
    {
        if (trim($username) === '' || trim($email) === '' || trim($password) === '') {
            throw new Exception('Invalid input data');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email format');
        }

        if (strlen($password) < 6) {
            throw new Exception('Password must be at least 6 characters');
        }

        if (AuthModel::findByUsername($username) !== null) {
            throw new Exception('Username already exists');
        }

        AuthModel::create($username, $email, $password);
    }

    public static function login(string $username, string $password, JwtService $jwtService): string
    {
        $user = AuthModel::findByUsername($username);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            throw new DomainException('Invalid credentials');
        }

        if ($user['deleted_at'] !== null) {
            throw new DomainException('User inactive');
        }

        return $jwtService->generate($user);
    }

    public static function changePassword(int $userId, string $newPassword): void
    {
        if (strlen($newPassword) < 6) {
            throw new Exception('Password must be at least 6 characters');
        }

        AuthModel::updatePassword($userId, $newPassword);
    }

    public static function deleteAccount(int $userId): void
    {
        AuthModel::softDelete($userId);
    }
}