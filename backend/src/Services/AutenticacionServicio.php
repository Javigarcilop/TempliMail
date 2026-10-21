<?php

declare(strict_types=1);

namespace TempliMail\Services;

use TempliMail\Exceptions\ApiExcepcion;
use TempliMail\Models\AutenticacionModelo;

class AutenticacionServicio
{
    private const MAX_FAILURES_PER_USER = 5;
    private const MAX_FAILURES_PER_IP   = 30;
    private const LOCK_MINUTES          = 15;
    private const MIN_PASSWORD_LENGTH   = 8;

    public static function register(string $nombre_usuario, string $correo, string $contrasena): void
    {
        $nombre_usuario = trim($nombre_usuario);
        $correo    = trim($correo);

        if ($nombre_usuario === '' || $correo === '' || $contrasena === '') {
            throw new ApiExcepcion('Datos incompletos');
        }

        if (mb_strlen($nombre_usuario) > 100 || !preg_match('/^[\p{L}\p{N}_.\-]+$/u', $nombre_usuario)) {
            throw new ApiExcepcion('El usuario solo puede contener letras, numeros, punto, guion y guion bajo');
        }

        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            throw new ApiExcepcion('El formato del correo no es valido');
        }

        if (strlen($contrasena) < self::MIN_PASSWORD_LENGTH) {
            throw new ApiExcepcion('La contraseña debe tener al menos ' . self::MIN_PASSWORD_LENGTH . ' caracteres');
        }

        if (AutenticacionModelo::findByUsername($nombre_usuario) !== null) {
            throw ApiExcepcion::conflict('Ese nombre de usuario ya existe');
        }

        if (AutenticacionModelo::findByEmail($correo) !== null) {
            throw ApiExcepcion::conflict('Ya existe una cuenta con ese correo');
        }

        AutenticacionModelo::create($nombre_usuario, $correo, $contrasena);
    }

    public static function login(string $nombre_usuario, string $contrasena, JwtServicio $jwtService): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'cli';

        if (
            AutenticacionModelo::countRecentFailuresByUsername($nombre_usuario, self::LOCK_MINUTES) >= self::MAX_FAILURES_PER_USER ||
            AutenticacionModelo::countRecentFailuresByIp($ip, self::LOCK_MINUTES) >= self::MAX_FAILURES_PER_IP
        ) {
            throw new ApiExcepcion(
                'Demasiados intentos fallidos. Vuelve a intentarlo en ' . self::LOCK_MINUTES . ' minutos.',
                429
            );
        }

        $user = AutenticacionModelo::findByUsername($nombre_usuario);

        // Mismo mensaje para usuario inexistente, contrasena erronea o cuenta desactivada
        if (
            !$user ||
            $user['eliminado_en'] !== null ||
            !password_verify($contrasena, $user['hash_contrasena'])
        ) {
            AutenticacionModelo::recordFailedLogin($nombre_usuario, $ip);
            throw new ApiExcepcion('Credenciales incorrectas', 401);
        }

        AutenticacionModelo::clearFailedLogins($nombre_usuario);

        return $jwtService->generate($user);
    }

    /** Invalida todos los tokens del usuario (cierre de sesion). */
    public static function logout(int $userId): void
    {
        AutenticacionModelo::incrementTokenVersion($userId);
    }

    /** Cambia el correo de la cuenta. */
    public static function updateProfile(int $userId, string $correo): array
    {
        $correo = trim($correo);

        if (!filter_var($correo, FILTER_VALIDATE_EMAIL) || mb_strlen($correo) > 255) {
            throw new ApiExcepcion('El formato del correo no es valido');
        }

        $existing = AutenticacionModelo::findByEmail($correo);

        if ($existing !== null && (int) $existing['id'] !== $userId) {
            throw ApiExcepcion::conflict('Ya existe una cuenta con ese correo');
        }

        AutenticacionModelo::updateEmail($userId, $correo);

        return self::currentUser($userId);
    }

    /**
     * Cambia la contrasena y devuelve un token nuevo: el cambio invalida los
     * tokens anteriores (otras sesiones), pero esta sesion sigue abierta.
     */
    public static function changePassword(int $userId, string $current, string $new, JwtServicio $jwtService): string
    {
        $user = AutenticacionModelo::findWithPasswordById($userId);

        if (!$user || !password_verify($current, $user['hash_contrasena'])) {
            throw new ApiExcepcion('La contraseña actual no es correcta', 403);
        }

        if (strlen($new) < self::MIN_PASSWORD_LENGTH) {
            throw new ApiExcepcion('La nueva contraseña debe tener al menos ' . self::MIN_PASSWORD_LENGTH . ' caracteres');
        }

        if ($new === $current) {
            throw new ApiExcepcion('La nueva contraseña debe ser distinta de la actual');
        }

        AutenticacionModelo::updatePassword($userId, $new);

        return $jwtService->generate(AutenticacionModelo::findById($userId));
    }

    public static function currentUser(int $userId): array
    {
        $user = AutenticacionModelo::findById($userId);

        if (!$user) {
            throw ApiExcepcion::notFound('Usuario no encontrado');
        }

        return [
            'id'       => (int) $user['id'],
            'nombre_usuario' => $user['nombre_usuario'],
            'correo'    => $user['correo'],
        ];
    }
}
