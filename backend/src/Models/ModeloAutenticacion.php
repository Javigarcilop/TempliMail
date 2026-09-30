<?php

namespace TempliMail\Models;

use TempliMail\Utils\BD;
use PDO;

class ModeloAutenticacion
{
    public static function findByUsername(string $nombre_usuario): ?array
    {
        $db = BD::get();

        $stmt = $db->prepare("
            SELECT id, nombre_usuario, correo, password_hash, version_token, eliminado_en
            FROM usuarios
            WHERE nombre_usuario = :nombre_usuario
            LIMIT 1
        ");

        $stmt->execute([
            'nombre_usuario' => $nombre_usuario
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function findById(int $id): ?array
    {
        $db = BD::get();

        $stmt = $db->prepare("
            SELECT id, nombre_usuario, correo, version_token, eliminado_en
            FROM usuarios
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
        $stmt = BD::get()->prepare("
            SELECT id, nombre_usuario, correo, password_hash, version_token, eliminado_en
            FROM usuarios WHERE id = :id LIMIT 1
        ");
        $stmt->execute(['id' => $id]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function updateEmail(int $userId, string $correo): void
    {
        BD::get()
            ->prepare("UPDATE usuarios SET correo = :correo WHERE id = :id AND eliminado_en IS NULL")
            ->execute(['correo' => $correo, 'id' => $userId]);
    }

    public static function findByEmail(string $correo): ?array
    {
        $stmt = BD::get()->prepare("SELECT id FROM usuarios WHERE correo = :correo LIMIT 1");
        $stmt->execute(['correo' => $correo]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    // -----------------------------------------------------------------
    // Proteccion contra fuerza bruta
    // -----------------------------------------------------------------

    public static function recordFailedLogin(string $nombre_usuario, string $ip): void
    {
        $db = BD::get();

        $db->prepare("INSERT INTO intentos_login (nombre_usuario, ip) VALUES (:nombre_usuario, :ip)")
           ->execute(['nombre_usuario' => mb_substr($nombre_usuario, 0, 100), 'ip' => $ip]);

        // Limpieza oportunista de registros antiguos
        $db->exec("DELETE FROM intentos_login WHERE intentado_en < UTC_TIMESTAMP() - INTERVAL 1 DAY");
    }

    public static function countRecentFailuresByUsername(string $nombre_usuario, int $minutes): int
    {
        $stmt = BD::get()->prepare("
            SELECT COUNT(*) FROM intentos_login
            WHERE nombre_usuario = :nombre_usuario
              AND intentado_en > UTC_TIMESTAMP() - INTERVAL $minutes MINUTE
        ");
        $stmt->execute(['nombre_usuario' => mb_substr($nombre_usuario, 0, 100)]);

        return (int) $stmt->fetchColumn();
    }

    public static function countRecentFailuresByIp(string $ip, int $minutes): int
    {
        $stmt = BD::get()->prepare("
            SELECT COUNT(*) FROM intentos_login
            WHERE ip = :ip
              AND intentado_en > UTC_TIMESTAMP() - INTERVAL $minutes MINUTE
        ");
        $stmt->execute(['ip' => $ip]);

        return (int) $stmt->fetchColumn();
    }

    public static function clearFailedLogins(string $nombre_usuario): void
    {
        BD::get()
            ->prepare("DELETE FROM intentos_login WHERE nombre_usuario = :nombre_usuario")
            ->execute(['nombre_usuario' => mb_substr($nombre_usuario, 0, 100)]);
    }

    public static function create(string $nombre_usuario, string $correo, string $contrasena): void
    {
        $db = BD::get();

        $stmt = $db->prepare("
            INSERT INTO usuarios (nombre_usuario, correo, password_hash, version_token)
            VALUES (:nombre_usuario, :correo, :password_hash, 1)
        ");

        $stmt->execute([
            'nombre_usuario'      => $nombre_usuario,
            'correo'         => $correo,
            'password_hash' => password_hash($contrasena, PASSWORD_ARGON2ID) 
        ]);
    }

    public static function updatePassword(int $userId, string $newPassword): void
    {
        $db = BD::get();

        $stmt = $db->prepare("
            UPDATE usuarios
            SET password_hash = :password_hash,
                version_token = version_token + 1,
                actualizado_en = NOW()
            WHERE id = :id
              AND eliminado_en IS NULL
        ");

        $stmt->execute([
            'id' => $userId,
            'password_hash' => password_hash($newPassword, PASSWORD_ARGON2ID)
        ]);
    }

    public static function incrementTokenVersion(int $userId): void
    {
        $db = BD::get();

        $stmt = $db->prepare("
            UPDATE usuarios
            SET version_token = version_token + 1
            WHERE id = :id
        ");

        $stmt->execute([
            'id' => $userId
        ]);
    }

    public static function softDelete(int $userId): void
    {
        $db = BD::get();

        $stmt = $db->prepare("
            UPDATE usuarios
            SET eliminado_en = NOW(),
                version_token = version_token + 1
            WHERE id = :id
        ");

        $stmt->execute([
            'id' => $userId
        ]);
    }
}