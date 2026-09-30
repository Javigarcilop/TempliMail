<?php

declare(strict_types=1);

namespace TempliMail\Services;

use TempliMail\Exceptions\ExcepcionApi;
use TempliMail\Models\ModeloAutenticacion;

class ServicioAutenticacion
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
            throw new ExcepcionApi('Datos incompletos');
        }

        if (mb_strlen($nombre_usuario) > 100 || !preg_match('/^[\p{L}\p{N}_.\-]+$/u', $nombre_usuario)) {
            throw new ExcepcionApi('El usuario solo puede contener letras, numeros, punto, guion y guion bajo');
        }

        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            throw new ExcepcionApi('El formato del correo no es valido');
        }

        if (strlen($contrasena) < self::MIN_PASSWORD_LENGTH) {
            throw new ExcepcionApi('La contraseña debe tener al menos ' . self::MIN_PASSWORD_LENGTH . ' caracteres');
        }

        if (ModeloAutenticacion::findByUsername($nombre_usuario) !== null) {
            throw ExcepcionApi::conflict('Ese nombre de usuario ya existe');
        }

        if (ModeloAutenticacion::findByEmail($correo) !== null) {
            throw ExcepcionApi::conflict('Ya existe una cuenta con ese correo');
        }

        ModeloAutenticacion::create($nombre_usuario, $correo, $contrasena);
    }

    public static function login(string $nombre_usuario, string $contrasena, ServicioJwt $jwtService): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'cli';

        if (
            ModeloAutenticacion::countRecentFailuresByUsername($nombre_usuario, self::LOCK_MINUTES) >= self::MAX_FAILURES_PER_USER ||
            ModeloAutenticacion::countRecentFailuresByIp($ip, self::LOCK_MINUTES) >= self::MAX_FAILURES_PER_IP
        ) {
            throw new ExcepcionApi(
                'Demasiados intentos fallidos. Vuelve a intentarlo en ' . self::LOCK_MINUTES . ' minutos.',
                429
            );
        }

        $user = ModeloAutenticacion::findByUsername($nombre_usuario);

        // Mismo mensaje para usuario inexistente, contrasena erronea o cuenta desactivada
        if (
            !$user ||
            $user['eliminado_en'] !== null ||
            !password_verify($contrasena, $user['password_hash'])
        ) {
            ModeloAutenticacion::recordFailedLogin($nombre_usuario, $ip);
            throw new ExcepcionApi('Credenciales incorrectas', 401);
        }

        ModeloAutenticacion::clearFailedLogins($nombre_usuario);

        return $jwtService->generate($user);
    }

    /** Invalida todos los tokens del usuario (cierre de sesion). */
    public static function logout(int $userId): void
    {
        ModeloAutenticacion::incrementTokenVersion($userId);
    }

    /** Cambia el correo de la cuenta. */
    public static function updateProfile(int $userId, string $correo): array
    {
        $correo = trim($correo);

        if (!filter_var($correo, FILTER_VALIDATE_EMAIL) || mb_strlen($correo) > 255) {
            throw new ExcepcionApi('El formato del correo no es valido');
        }

        $existing = ModeloAutenticacion::findByEmail($correo);

        if ($existing !== null && (int) $existing['id'] !== $userId) {
            throw ExcepcionApi::conflict('Ya existe una cuenta con ese correo');
        }

        ModeloAutenticacion::updateEmail($userId, $correo);

        return self::currentUser($userId);
    }

    /**
     * Cambia la contrasena y devuelve un token nuevo: el cambio invalida los
     * tokens anteriores (otras sesiones), pero esta sesion sigue abierta.
     */
    public static function changePassword(int $userId, string $current, string $new, ServicioJwt $jwtService): string
    {
        $user = ModeloAutenticacion::findWithPasswordById($userId);

        if (!$user || !password_verify($current, $user['password_hash'])) {
            throw new ExcepcionApi('La contraseña actual no es correcta', 403);
        }

        if (strlen($new) < self::MIN_PASSWORD_LENGTH) {
            throw new ExcepcionApi('La nueva contraseña debe tener al menos ' . self::MIN_PASSWORD_LENGTH . ' caracteres');
        }

        if ($new === $current) {
            throw new ExcepcionApi('La nueva contraseña debe ser distinta de la actual');
        }

        ModeloAutenticacion::updatePassword($userId, $new);

        return $jwtService->generate(ModeloAutenticacion::findById($userId));
    }

    public static function currentUser(int $userId): array
    {
        $user = ModeloAutenticacion::findById($userId);

        if (!$user) {
            throw ExcepcionApi::notFound('Usuario no encontrado');
        }

        return [
            'id'       => (int) $user['id'],
            'nombre_usuario' => $user['nombre_usuario'],
            'correo'    => $user['correo'],
        ];
    }
}
