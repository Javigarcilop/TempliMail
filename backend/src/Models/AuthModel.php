<?php

namespace TempliMail\Models;
use TempliMail\Utils\DB;
use PDO;

class AuthModel
{
    public static function findByUsuario(string $usuario): ?array
    {
        $db = DB::get();

        $stmt = $db->prepare(
            "SELECT id, usuario, password_hash
             FROM usuarios
             WHERE usuario = :usuario
             LIMIT 1"
        );

        $stmt->execute([
            'usuario' => $usuario
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function create(string $usuario, string $password): void
    {
        $db = DB::get();

        $stmt = $db->prepare(
            "INSERT INTO usuarios (usuario, password_hash)
             VALUES (:usuario, :password_hash)"
        );

        $stmt->execute([
            'usuario' => $usuario,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT)
        ]);
    }


    public static function update(int $id, string $password): void
    {
        $db = DB::get();

        $stmt = $db->prepare(
            "UPDATE usuarios
             SET usuario = :usuario,
                 password_hash = :password_hash,
             WHERE id = :id"
        );

        $stmt->execute([
            'id' => $id,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT)
        ]);
    }

    public static function delete(int $id): void
    {
        $db = DB::get();

        $stmt = $db->prepare(
            "DELETE FROM usuarios WHERE id = :id"
        );
        $stmt->execute(['id' => $id]);
    }
}
