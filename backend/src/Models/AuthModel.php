<?php

namespace TempliMail\Models;

use TempliMail\Utils\DB;
use PDO;

class AuthModel
{
    public static function findByUsername(string $username): ?array
    {
        $db = DB::get();

        $stmt = $db->prepare("
            SELECT id, username, email, password_hash, token_version, deleted_at
            FROM users
            WHERE username = :username
            LIMIT 1
        ");

        $stmt->execute([
            'username' => $username
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function findById(int $id): ?array
    {
        $db = DB::get();

        $stmt = $db->prepare("
            SELECT id, username, email, token_version, deleted_at
            FROM users
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->execute([
            'id' => $id
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function create(string $username, string $email, string $password): void
    {
        $db = DB::get();

        $stmt = $db->prepare("
            INSERT INTO users (username, email, password_hash, token_version)
            VALUES (:username, :email, :password_hash, 1)
        ");

        $stmt->execute([
            'username'      => $username,
            'email'         => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT)
        ]);
    }

    public static function updatePassword(int $userId, string $newPassword): void
    {
        $db = DB::get();

        $stmt = $db->prepare("
            UPDATE users
            SET password_hash = :password_hash,
                token_version = token_version + 1,
                updated_at = NOW()
            WHERE id = :id
              AND deleted_at IS NULL
        ");

        $stmt->execute([
            'id' => $userId,
            'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT)
        ]);
    }

    public static function incrementTokenVersion(int $userId): void
    {
        $db = DB::get();

        $stmt = $db->prepare("
            UPDATE users
            SET token_version = token_version + 1
            WHERE id = :id
        ");

        $stmt->execute([
            'id' => $userId
        ]);
    }

    public static function softDelete(int $userId): void
    {
        $db = DB::get();

        $stmt = $db->prepare("
            UPDATE users
            SET deleted_at = NOW(),
                token_version = token_version + 1
            WHERE id = :id
        ");

        $stmt->execute([
            'id' => $userId
        ]);
    }
}