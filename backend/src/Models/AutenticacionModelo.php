<?php

namespace TempliMail\Models;

use TempliMail\Utils\BD;
use PDO;

class AutenticacionModelo
{
    public static function buscarPorNombreUsuario(string $nombre_usuario): ?array
    {
        $db = BD::obtener();

        $stmt = $db->prepare("
            SELECT id, nombre_usuario, correo, hash_contrasena, version_token, eliminado_en
            FROM usuarios
            WHERE nombre_usuario = :nombre_usuario
            LIMIT 1
        ");

        $stmt->execute([
            'nombre_usuario' => $nombre_usuario
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function buscarPorId(int $id): ?array
    {
        $db = BD::obtener();

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

    public static function buscarConContrasenaPorId(int $id): ?array
    {
        $stmt = BD::obtener()->prepare("
            SELECT id, nombre_usuario, correo, hash_contrasena, version_token, eliminado_en
            FROM usuarios WHERE id = :id LIMIT 1
        ");
        $stmt->execute(['id' => $id]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function actualizarCorreo(int $userId, string $correo): void
    {
        BD::obtener()
            ->prepare("UPDATE usuarios SET correo = :correo WHERE id = :id AND eliminado_en IS NULL")
            ->execute(['correo' => $correo, 'id' => $userId]);
    }

    public static function buscarPorCorreo(string $correo): ?array
    {
        $stmt = BD::obtener()->prepare("SELECT id FROM usuarios WHERE correo = :correo LIMIT 1");
        $stmt->execute(['correo' => $correo]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    // -----------------------------------------------------------------
    // Proteccion contra fuerza bruta
    // -----------------------------------------------------------------

    public static function registrarIntentoFallido(string $nombre_usuario, string $ip): void
    {
        $db = BD::obtener();

        $db->prepare("INSERT INTO intentos_login (nombre_usuario, ip) VALUES (:nombre_usuario, :ip)")
           ->execute(['nombre_usuario' => mb_substr($nombre_usuario, 0, 100), 'ip' => $ip]);

        // Limpieza oportunista de registros antiguos
        $db->exec("DELETE FROM intentos_login WHERE intentado_en < UTC_TIMESTAMP() - INTERVAL 1 DAY");
    }

    public static function contarFallosRecientesPorUsuario(string $nombre_usuario, int $minutes): int
    {
        $stmt = BD::obtener()->prepare("
            SELECT COUNT(*) FROM intentos_login
            WHERE nombre_usuario = :nombre_usuario
              AND intentado_en > UTC_TIMESTAMP() - INTERVAL $minutes MINUTE
        ");
        $stmt->execute(['nombre_usuario' => mb_substr($nombre_usuario, 0, 100)]);

        return (int) $stmt->fetchColumn();
    }

    public static function contarFallosRecientesPorIp(string $ip, int $minutes): int
    {
        $stmt = BD::obtener()->prepare("
            SELECT COUNT(*) FROM intentos_login
            WHERE ip = :ip
              AND intentado_en > UTC_TIMESTAMP() - INTERVAL $minutes MINUTE
        ");
        $stmt->execute(['ip' => $ip]);

        return (int) $stmt->fetchColumn();
    }

    public static function borrarIntentosFallidos(string $nombre_usuario): void
    {
        BD::obtener()
            ->prepare("DELETE FROM intentos_login WHERE nombre_usuario = :nombre_usuario")
            ->execute(['nombre_usuario' => mb_substr($nombre_usuario, 0, 100)]);
    }

    public static function crear(string $nombre_usuario, string $correo, string $contrasena): void
    {
        $db = BD::obtener();

        $stmt = $db->prepare("
            INSERT INTO usuarios (nombre_usuario, correo, hash_contrasena, version_token)
            VALUES (:nombre_usuario, :correo, :hash_contrasena, 1)
        ");

        $stmt->execute([
            'nombre_usuario'      => $nombre_usuario,
            'correo'         => $correo,
            'hash_contrasena' => password_hash($contrasena, PASSWORD_ARGON2ID)
        ]);
    }

    public static function actualizarContrasena(int $userId, string $newPassword): void
    {
        $db = BD::obtener();

        $stmt = $db->prepare("
            UPDATE usuarios
            SET hash_contrasena = :hash_contrasena,
                version_token = version_token + 1,
                actualizado_en = NOW()
            WHERE id = :id
              AND eliminado_en IS NULL
        ");

        $stmt->execute([
            'id' => $userId,
            'hash_contrasena' => password_hash($newPassword, PASSWORD_ARGON2ID)
        ]);
    }

    public static function incrementarVersionToken(int $userId): void
    {
        $db = BD::obtener();

        $stmt = $db->prepare("
            UPDATE usuarios
            SET version_token = version_token + 1
            WHERE id = :id
        ");

        $stmt->execute([
            'id' => $userId
        ]);
    }

    public static function eliminarLogico(int $userId): void
    {
        $db = BD::obtener();

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