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

    public static function findWithPasswordById(int $id): ?array
    {
        $stmt = DB::get()->prepare("
            SELECT id, username, email, password_hash, token_version, deleted_at
            FROM users WHERE id = :id LIMIT 1
        ");
        $stmt->execute(['id' => $id]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function updateEmail(int $userId, string $email): void
    {
        DB::get()
            ->prepare("UPDATE users SET email = :email WHERE id = :id AND deleted_at IS NULL")
            ->execute(['email' => $email, 'id' => $userId]);
    }

    public static function findByEmail(string $email): ?array
    {
        $stmt = DB::get()->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => $email]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    // -----------------------------------------------------------------
    // Proteccion contra fuerza bruta
    // -----------------------------------------------------------------

    public static function recordFailedLogin(string $username, string $ip): void
    {
        $db = DB::get();

        $db->prepare("INSERT INTO login_attempts (username, ip) VALUES (:username, :ip)")
           ->execute(['username' => mb_substr($username, 0, 100), 'ip' => $ip]);

        // Limpieza oportunista de registros antiguos
        $db->exec("DELETE FROM login_attempts WHERE attempted_at < UTC_TIMESTAMP() - INTERVAL 1 DAY");
    }

    public static function countRecentFailuresByUsername(string $username, int $minutes): int
    {
        $stmt = DB::get()->prepare("
            SELECT COUNT(*) FROM login_attempts
            WHERE username = :username
              AND attempted_at > UTC_TIMESTAMP() - INTERVAL $minutes MINUTE
        ");
        $stmt->execute(['username' => mb_substr($username, 0, 100)]);

        return (int) $stmt->fetchColumn();
    }

    public static function countRecentFailuresByIp(string $ip, int $minutes): int
    {
        $stmt = DB::get()->prepare("
            SELECT COUNT(*) FROM login_attempts
            WHERE ip = :ip
              AND attempted_at > UTC_TIMESTAMP() - INTERVAL $minutes MINUTE
        ");
        $stmt->execute(['ip' => $ip]);

        return (int) $stmt->fetchColumn();
    }

    public static function clearFailedLogins(string $username): void
    {
        DB::get()
            ->prepare("DELETE FROM login_attempts WHERE username = :username")
            ->execute(['username' => mb_substr($username, 0, 100)]);
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
            'password_hash' => password_hash($password, PASSWORD_ARGON2ID) 
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
            'password_hash' => password_hash($newPassword, PASSWORD_ARGON2ID)
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