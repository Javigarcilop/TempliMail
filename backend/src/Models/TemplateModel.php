<?php

namespace TempliMail\Models;

use TempliMail\Utils\DB;
use PDO;

class TemplateModel
{
    public static function getAllByUser(int $userId): array
    {
        $db = DB::get();

        $stmt = $db->prepare("
            SELECT *
            FROM templates
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
                content_html = :content_html,
                updated_at = NOW()
            WHERE id = :id
              AND user_id = :user_id
        ");

        $stmt->execute([
            'id' => $id,
            'user_id' => $userId,
            'name' => $data['name'],
            'subject' => $data['subject'],
            'content_html' => $data['content_html']
        ]);
    }

    public static function softDelete(int $userId, int $id): void
    {
        $db = DB::get();

        $stmt = $db->prepare("
            UPDATE templates
            SET deleted_at = NOW()
            WHERE id = :id
              AND user_id = :user_id
        ");

        $stmt->execute([
            'id' => $id,
            'user_id' => $userId
        ]);
    }
}
