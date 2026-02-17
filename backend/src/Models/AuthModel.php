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
            SELECT id, username, email, password_hash
            FROM users
            WHERE username = :username
              AND deleted_at IS NULL
            LIMIT 1
        ");

        $stmt->execute([
            'username' => $username
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function create(string $username, string $email, string $password): void
    {
        $db = DB::get();

        $stmt = $db->prepare("
            INSERT INTO users (username, email, password_hash)
            VALUES (:username, :email, :password_hash)
        ");

        $stmt->execute([
            'username' => $username,
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT)
        ]);
    }

    public static function updatePassword(int $id, string $password): void
    {
        $db = DB::get();

        $stmt = $db->prepare("
            UPDATE users
            SET password_hash = :password_hash,
                updated_at = NOW()
            WHERE id = :id
        ");

        $stmt->execute([
            'id' => $id,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT)
        ]);
    }

    public static function softDelete(int $id): void
    {
        $db = DB::get();

        $stmt = $db->prepare("
            UPDATE users
            SET deleted_at = NOW()
            WHERE id = :id
        ");

        $stmt->execute(['id' => $id]);
    }
}
