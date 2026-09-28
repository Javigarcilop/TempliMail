<?php

namespace TempliMail\Models;

use TempliMail\Exceptions\ApiException;
use TempliMail\Utils\DB;
use PDO;

class TemplateModel
{
    public static function getAllByUser(int $userId): array
    {
        $db = DB::get();

        $stmt = $db->prepare("
            SELECT id, name, subject, content_html,
                   DATE_FORMAT(created_at, '%Y-%m-%dT%H:%i:%sZ') AS created_at,
                   DATE_FORMAT(updated_at, '%Y-%m-%dT%H:%i:%sZ') AS updated_at
            FROM templates
            WHERE user_id = :user_id
              AND deleted_at IS NULL
            ORDER BY created_at DESC
        ");

        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getById(int $userId, int $id): ?array
    {
        $db = DB::get();

        $stmt = $db->prepare("
            SELECT id, name, subject, content_html,
                   DATE_FORMAT(created_at, '%Y-%m-%dT%H:%i:%sZ') AS created_at,
                   DATE_FORMAT(updated_at, '%Y-%m-%dT%H:%i:%sZ') AS updated_at
            FROM templates
            WHERE id = :id
              AND user_id = :user_id
              AND deleted_at IS NULL
            LIMIT 1
        ");

        $stmt->execute([
            'id' => $id,
            'user_id' => $userId
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function create(int $userId, array $data): void
    {
        $db = DB::get();

        $stmt = $db->prepare("
            INSERT INTO templates (user_id, name, subject, content_html)
            VALUES (:user_id, :name, :subject, :content_html)
        ");

        $stmt->execute([
            'user_id' => $userId,
            'name' => $data['name'],
            'subject' => $data['subject'],
            'content_html' => $data['content_html']
        ]);
    }

    public static function update(int $userId, int $id, array $data): void
    {
        $db = DB::get();

        $stmt = $db->prepare("
            UPDATE templates
            SET name = :name,
                subject = :subject,
                content_html = :content_html
            WHERE id = :id
              AND user_id = :user_id
              AND deleted_at IS NULL
        ");

        $stmt->execute([
            'id' => $id,
            'user_id' => $userId,
            'name' => $data['name'],
            'subject' => $data['subject'],
            'content_html' => $data['content_html']
        ]);

        // rowCount() vale 0 si no cambio nada: se comprueba la existencia aparte
        if ($stmt->rowCount() === 0 && self::getById($userId, $id) === null) {
            throw ApiException::notFound('Plantilla no encontrada');
        }
    }

    public static function softDelete(int $userId, int $id): void
    {
        $db = DB::get();

        $stmt = $db->prepare("
            UPDATE templates
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
            throw ApiException::notFound('Plantilla no encontrada');
        }
    }
}