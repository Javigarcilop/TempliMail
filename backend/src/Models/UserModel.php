<?php

namespace TempliMail\Models;
use TempliMail\Utils\DB;
use PDO;

class UserModel
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
}
