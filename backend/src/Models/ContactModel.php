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

        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function create(int $userId, array $data): void
    {
        $db = DB::get();

        $stmt = $db->prepare("
            INSERT INTO contacts 
            (user_id, first_name, last_name, email, phone, company, position)
            VALUES (:user_id, :first_name, :last_name, :email, :phone, :company, :position)
        ");

        $stmt->execute([
            'user_id'    => $userId,
            'first_name' => $data['first_name'] ?? null,
            'last_name'  => $data['last_name'] ?? null,
            'email'      => $data['email'],
            'phone'      => $data['phone'] ?? null,
            'company'    => $data['company'] ?? null,
            'position'   => $data['position'] ?? null
        ]);
    }

    public static function update(int $userId, int $id, array $data): void
    {
        $db = DB::get();

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
            'email'      => $data['email'],
            'phone'      => $data['phone'] ?? null,
            'company'    => $data['company'] ?? null,
            'position'   => $data['position'] ?? null
        ]);

        if ($stmt->rowCount() === 0) {
            throw new Exception('Contact not found or not owned by user');
        }
    }

    public static function softDelete(int $userId, int $id): void
    {
        $db = DB::get();

        $stmt = $db->prepare("
            UPDATE contacts
            SET deleted_at = NOW()
            WHERE id = :id
              AND user_id = :user_id
              AND deleted_at IS NULL
        ");

        $stmt->execute([
            'id' => $id,
            'user_id' => $userId
        ]);

        if ($stmt->rowCount() === 0) {
            throw new Exception('Contact not found or already deleted');
        }
    }
}