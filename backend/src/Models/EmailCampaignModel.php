<?php

namespace TempliMail\Models;

use TempliMail\Utils\DB;
use PDO;
use Exception;

class EmailCampaignModel
{
    public static function create(
        int $userId,
        ?int $templateId,
        string $subject,
        string $content,
        string $status,
        ?string $scheduledAt
    ): int {
        $db = DB::get();

        $stmt = $db->prepare("
            INSERT INTO email_campaigns
            (user_id, template_id, subject, content_html, status, scheduled_at)
            VALUES (:user_id, :template_id, :subject, :content_html, :status, :scheduled_at)
        ");

        $stmt->execute([
            'user_id'      => $userId,
            'template_id'  => $templateId,
            'subject'      => $subject,
            'content_html' => $content,
            'status'       => $status,
            'scheduled_at' => $scheduledAt
        ]);

        return (int) $db->lastInsertId();
    }

    public static function getById(int $userId, int $campaignId): ?array
    {
        $db = DB::get();

        $stmt = $db->prepare("
            SELECT id, user_id, subject, content_html, status, scheduled_at
            FROM email_campaigns
            WHERE id = :id
              AND user_id = :user_id
            LIMIT 1
        ");

        $stmt->execute([
            'id' => $campaignId,
            'user_id' => $userId
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function setProcessing(int $userId, int $campaignId): void
    {
        $db = DB::get();

        $stmt = $db->prepare("
            UPDATE email_campaigns
            SET status = 'processing',
                updated_at = NOW()
            WHERE id = :id
              AND user_id = :user_id
        ");

        $stmt->execute([
            'id' => $campaignId,
            'user_id' => $userId
        ]);

        if ($stmt->rowCount() === 0) {
            throw new Exception('Campaign not found or not owned by user');
        }
    }

    public static function markCompleted(int $userId, int $campaignId): void
    {
        $db = DB::get();

        $stmt = $db->prepare("
            UPDATE email_campaigns
            SET status = 'completed',
                updated_at = NOW()
            WHERE id = :id
              AND user_id = :user_id
        ");

        $stmt->execute([
            'id' => $campaignId,
            'user_id' => $userId
        ]);

        if ($stmt->rowCount() === 0) {
            throw new Exception('Campaign not found or not owned by user');
        }
    }

    public static function getScheduledByUser(int $userId): array
    {
        $db = DB::get();

        $stmt = $db->prepare("
            SELECT id, subject, content_html, scheduled_at, status
            FROM email_campaigns
            WHERE user_id = :user_id
              AND status = 'scheduled'
              AND scheduled_at <= NOW()
            ORDER BY scheduled_at ASC
        ");

        $stmt->execute([
            'user_id' => $userId
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}