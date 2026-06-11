<?php

namespace TempliMail\Models;

use TempliMail\Utils\DB;
use PDO;
use Exception;

class ContactModel
{
    public static function getAllByUser(int $userId): array
    {
        $db = DB::get();

        $stmt = $db->prepare("
            SELECT id, first_name, last_name, email, phone, company, position, created_at, updated_at
            FROM contacts
            WHERE user_id = :user_id
              AND deleted_at IS NULL
            ORDER BY created_at DESC
        ");

        $stmt->execute([
            'user_id' => $userId
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function create(int $userId, array $data): void
    {
        $db = DB::get();

        $email = trim($data['email'] ?? '');

        if ($email === '') {
            throw new Exception('El email es obligatorio');
        }

        if (self::emailExists($userId, $email)) {
            throw new Exception('Ya existe un contacto con ese email');
        }

        $stmt = $db->prepare("
            INSERT INTO contacts 
            (user_id, first_name, last_name, email, phone, company, position)
            VALUES (:user_id, :first_name, :last_name, :email, :phone, :company, :position)
        ");

        $stmt->execute([
            'user_id'    => $userId,
            'first_name' => $data['first_name'] ?? null,
            'last_name'  => $data['last_name'] ?? null,
            'email'      => $email,
            'phone'      => $data['phone'] ?? null,
            'company'    => $data['company'] ?? null,
            'position'   => $data['position'] ?? null
        ]);
    }

    public static function update(int $userId, int $id, array $data): void
    {
        $db = DB::get();

        $email = trim($data['email'] ?? '');

        if ($email === '') {
            throw new Exception('El email es obligatorio');
        }

        if (self::emailExistsForAnotherContact($userId, $email, $id)) {
            throw new Exception('Ya existe otro contacto con ese email');
        }

        $stmt = $db->prepare("
            UPDATE contacts
            SET first_name = :first_name,
                last_name = :last_name,
                email = :email,
                phone = :phone,
                company = :company,
                position = :position,
                updated_at = NOW()
            WHERE id = :id
              AND user_id = :user_id
              AND deleted_at IS NULL
        ");

        $stmt->execute([
            'id'         => $id,
            'user_id'    => $userId,
            'first_name' => $data['first_name'] ?? null,
            'last_name'  => $data['last_name'] ?? null,
            'email'      => $email,
            'phone'      => $data['phone'] ?? null,
            'company'    => $data['company'] ?? null,
            'position'   => $data['position'] ?? null
        ]);

        if ($stmt->rowCount() === 0) {
            throw new Exception('Contacto no encontrado o sin permisos');
        }
    }

    public static function softDelete(int $userId, int $id): void
{
    $db = DB::get();

    $stmt = $db->prepare("
        DELETE FROM contacts
        WHERE id = :id
          AND user_id = :user_id
    ");

    $stmt->execute([
        'id'      => $id,
        'user_id' => $userId
    ]);

    if ($stmt->rowCount() === 0) {
        throw new Exception('Contacto no encontrado o sin permisos');
    }
}

    private static function emailExists(int $userId, string $email): bool
    {
        $db = DB::get();

        $stmt = $db->prepare("
            SELECT id
            FROM contacts
            WHERE user_id = :user_id
              AND email = :email
            LIMIT 1
        ");

        $stmt->execute([
            'user_id' => $userId,
            'email'   => $email
        ]);

        return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private static function emailExistsForAnotherContact(int $userId, string $email, int $contactId): bool
    {
        $db = DB::get();

        $stmt = $db->prepare("
            SELECT id
            FROM contacts
            WHERE user_id = :user_id
              AND email = :email
              AND id != :id
            LIMIT 1
        ");

        $stmt->execute([
            'user_id' => $userId,
            'email'   => $email,
            'id'      => $contactId
        ]);

        return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    }
}