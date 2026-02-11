<?php

namespace TempliMail\Services;

use TempliMail\Models\UserModel;
use Exception;

class UserService
{
    public static function login(string $usuario, string $password): void
    {
        $user = UserModel::findByUsuario($usuario);

        if (!$user) {
            throw new Exception('Credenciales incorrectas');
        }

        if (!password_verify($password, $user['password_hash'])) {
            throw new Exception('Credenciales incorrectas');
        }
    }

    public static function createUser(string $usuario, string $password): void
    {
        UserModel::create($usuario, $password);
    }
}
