<?php

namespace TempliMail\Services;

use TempliMail\Models\AuthModel;
use Exception;

class AuthService
{
    /**
     * Register new user
     */
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

        $existingUser = AuthModel::findByUsername($username);

        if ($existingUser !== null) {
            throw new Exception('Username already exists');
        }

        AuthModel::create($username, $email, $password);
    }

    /**
     * Login user
     */
    public static function login(string $username, string $password): array
    {
        if (trim($username) === '' || trim($password) === '') {
            throw new Exception('Invalid credentials');
        }

        $user = AuthModel::findByUsername($username);

        if (!$user) {
            throw new Exception('Invalid credentials');
        }

        if (!password_verify($password, $user['password_hash'])) {
            throw new Exception('Invalid credentials');
        }

        return [
            'id'       => $user['id'],
            'username' => $user['username'],
            'email'    => $user['email']
        ];
    }

    /**
     * Change password
     */
    public static function changePassword(int $userId, string $newPassword): void
    {
        if (strlen($newPassword) < 6) {
            throw new Exception('Password must be at least 6 characters');
        }

        AuthModel::updatePassword($userId, $newPassword);
    }

    /**
     * Delete account (soft delete)
     */
    public static function deleteAccount(int $userId): void
    {
        AuthModel::softDelete($userId);
    }
}
