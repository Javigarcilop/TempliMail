<?php

declare(strict_types=1);

namespace TempliMail\Services;

use TempliMail\Exceptions\ApiException;
use TempliMail\Models\AuthModel;

class AuthService
{
    private const MAX_FAILURES_PER_USER = 5;
    private const MAX_FAILURES_PER_IP   = 30;
    private const LOCK_MINUTES          = 15;
    private const MIN_PASSWORD_LENGTH   = 8;

    public static function register(string $username, string $email, string $password): void
    {
        $username = trim($username);
        $email    = trim($email);

        if ($username === '' || $email === '' || $password === '') {
            throw new ApiException('Datos incompletos');
        }

        if (mb_strlen($username) > 100 || !preg_match('/^[\p{L}\p{N}_.\-]+$/u', $username)) {
            throw new ApiException('El usuario solo puede contener letras, numeros, punto, guion y guion bajo');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new ApiException('El formato del email no es valido');
        }

        if (strlen($password) < self::MIN_PASSWORD_LENGTH) {
            throw new ApiException('La contraseña debe tener al menos ' . self::MIN_PASSWORD_LENGTH . ' caracteres');
        }

        if (AuthModel::findByUsername($username) !== null) {
            throw ApiException::conflict('Ese nombre de usuario ya existe');
        }

        if (AuthModel::findByEmail($email) !== null) {
            throw ApiException::conflict('Ya existe una cuenta con ese email');
        }

        AuthModel::create($username, $email, $password);
    }

    public static function login(string $username, string $password, JwtService $jwtService): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'cli';

        if (
            AuthModel::countRecentFailuresByUsername($username, self::LOCK_MINUTES) >= self::MAX_FAILURES_PER_USER ||
            AuthModel::countRecentFailuresByIp($ip, self::LOCK_MINUTES) >= self::MAX_FAILURES_PER_IP
        ) {
            throw new ApiException(
                'Demasiados intentos fallidos. Vuelve a intentarlo en ' . self::LOCK_MINUTES . ' minutos.',
                429
            );
        }

        $user = AuthModel::findByUsername($username);

        // Mismo mensaje para usuario inexistente, contrasena erronea o cuenta desactivada
        if (
            !$user ||
            $user['deleted_at'] !== null ||
            !password_verify($password, $user['password_hash'])
        ) {
            AuthModel::recordFailedLogin($username, $ip);
            throw new ApiException('Credenciales incorrectas', 401);
        }

        AuthModel::clearFailedLogins($username);

        return $jwtService->generate($user);
    }

    /** Invalida todos los tokens del usuario (cierre de sesion). */
    public static function logout(int $userId): void
    {
        AuthModel::incrementTokenVersion($userId);
    }

    /** Cambia el email de la cuenta. */
    public static function updateProfile(int $userId, string $email): array
    {
        $email = trim($email);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 255) {
            throw new ApiException('El formato del email no es valido');
        }

        $existing = AuthModel::findByEmail($email);

        if ($existing !== null && (int) $existing['id'] !== $userId) {
            throw ApiException::conflict('Ya existe una cuenta con ese email');
        }

        AuthModel::updateEmail($userId, $email);

        return self::currentUser($userId);
    }

    /**
     * Cambia la contrasena y devuelve un token nuevo: el cambio invalida los
     * tokens anteriores (otras sesiones), pero esta sesion sigue abierta.
     */
    public static function changePassword(int $userId, string $current, string $new, JwtService $jwtService): string
    {
        $user = AuthModel::findWithPasswordById($userId);

        if (!$user || !password_verify($current, $user['password_hash'])) {
            throw new ApiException('La contraseña actual no es correcta', 403);
        }

        if (strlen($new) < self::MIN_PASSWORD_LENGTH) {
            throw new ApiException('La nueva contraseña debe tener al menos ' . self::MIN_PASSWORD_LENGTH . ' caracteres');
        }

        if ($new === $current) {
            throw new ApiException('La nueva contraseña debe ser distinta de la actual');
        }

        AuthModel::updatePassword($userId, $new);

        return $jwtService->generate(AuthModel::findById($userId));
    }

    public static function currentUser(int $userId): array
    {
        $user = AuthModel::findById($userId);

        if (!$user) {
            throw ApiException::notFound('Usuario no encontrado');
        }

        return [
            'id'       => (int) $user['id'],
            'username' => $user['username'],
            'email'    => $user['email'],
        ];
    }
}
