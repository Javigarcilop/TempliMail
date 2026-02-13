<?php

namespace TempliMail\Services;

use TempliMail\Models\AuthModel;
use Exception;

class AuthService
{
    public static function login(string $usuario, string $password): void
    {
        $user = AuthModel::findByUsuario($usuario);

        if (!$user) {
            throw new Exception('Credenciales incorrectas');
        }

        if (!password_verify($password, $user['password_hash'])) {
            throw new Exception('Credenciales incorrectas');
        }
    }

    public static function createUser(string $usuario, string $password): void
    {
        AuthModel::create($usuario, $password);
    }

    public static function registerUser1(string $usuario, string $password): void
    {

        $existingUser = AuthModel::findByUsuario($usuario);

        if($existingUser && $password){
            throw new Exception('Usuario ya existe');
        }
        AuthModel::create($usuario, $password);

    }

    public static function registerUser(string $usuario, string $password): void
{
    if (trim($usuario) === '' || trim($password) === '') {
        throw new Exception('Datos inválidos');
    }

    if (strlen($password) < 4) {
        throw new Exception('Password demasiado corta');
    }
    $existingUser = AuthModel::findByUsuario($usuario);

    if ($existingUser !== null) {
        throw new Exception('Usuario ya existe');
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    AuthModel::create($usuario, $hash);
}


}
