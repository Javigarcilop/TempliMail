<?php

namespace TempliMail\Models;

use TempliMail\Utils\DB;
use PDO;

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
            'user_id' => $userId,
            'template_id' => $templateId,
            'subject' => $subject,
            'content_html' => $content,
            'status' => $status,
            'scheduled_at' => $scheduledAt
        ]);

        return (int) $db->lastInsertId();
    }

    public static function markCompleted(int $campaignId): void
    {
        $db = DB::get();

        $stmt = $db->prepare("
            UPDATE email_campaigns
            SET status = 'completed',
                updated_at = NOW()
            WHERE id = :id
        ");

        $stmt->execute(['id' => $campaignId]);
    }

    public static function getScheduled(): array
    {
        $db = DB::get();

        $stmt = $db->prepare("
            SELECT *
            FROM email_campaigns
            WHERE status = 'scheduled'
              AND scheduled_at <= NOW()
        ");

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function setProcessing(int $campaignId): void
    {
        $db = DB::get();

        $stmt = $db->prepare("
            UPDATE email_campaigns
            SET status = 'processing'
            WHERE id = :id
        ");

        $stmt->execute(['id' => $campaignId]);
    }
}
