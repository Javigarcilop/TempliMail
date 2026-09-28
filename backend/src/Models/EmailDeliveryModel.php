<?php

declare(strict_types=1);

namespace TempliMail\Models;

use TempliMail\Utils\DB;
use PDO;

class EmailDeliveryModel
{
    /**
     * @param array<int,array{id:int|string|null,email:string}> $recipients
     *        contact_id puede ser null (envio individual)
     */
    public static function insertBatch(int $campaignId, array $recipients): void
    {
        if ($recipients === []) {
            return;
        }

        $db = DB::get();

        foreach (array_chunk($recipients, 500) as $chunk) {
            $rows   = [];
            $params = [];

            foreach ($chunk as $recipient) {
                $rows[]   = '(?, ?, ?, \'pending\')';
                $params[] = $campaignId;
                $params[] = $recipient['id'] ?? null;
                $params[] = $recipient['email'];
            }

            $db->prepare(
                'INSERT INTO email_deliveries (campaign_id, contact_id, recipient_email, status) VALUES '
                . implode(',', $rows)
            )->execute($params);
        }
    }

    /**
     * Entregas pendientes con los datos del contacto (para personalizar el correo).
     * Los contactos borrados aparecen con contact_id NULL.
     */
    public static function getPendingByCampaign(int $campaignId, int $limit = 50): array
    {
        $stmt = DB::get()->prepare("
            SELECT ed.id, ed.contact_id, ed.recipient_email,
                   c.first_name, c.last_name, c.company, c.position,
                   c.unsubscribed_at
            FROM email_deliveries ed
            LEFT JOIN contacts c ON ed.contact_id = c.id AND c.deleted_at IS NULL
            WHERE ed.campaign_id = :campaign_id
              AND ed.status = 'pending'
            ORDER BY ed.id ASC
            LIMIT :limit
        ");

        $stmt->bindValue('campaign_id', $campaignId, PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function markSent(int $deliveryId, int $attempts = 1): void
    {
        DB::get()->prepare("
            UPDATE email_deliveries
            SET status = 'sent', sent_at = UTC_TIMESTAMP(), error_message = NULL, retry_count = :attempts
            WHERE id = :id
        ")->execute(['id' => $deliveryId, 'attempts' => max(0, $attempts - 1)]);
    }

    public static function markFailed(int $deliveryId, string $error, int $attempts = 1): void
    {
        DB::get()->prepare("
            UPDATE email_deliveries
            SET status = 'failed', error_message = :error, retry_count = :attempts
            WHERE id = :id
        ")->execute([
            'id'       => $deliveryId,
            'error'    => mb_substr($error, 0, 1000),
            'attempts' => $attempts,
        ]);
    }

    public static function markSkipped(int $deliveryId, string $reason): void
    {
        DB::get()->prepare("
            UPDATE email_deliveries
            SET status = 'skipped', error_message = :reason
            WHERE id = :id
        ")->execute(['id' => $deliveryId, 'reason' => $reason]);
    }

    public static function getByCampaign(int $campaignId, int $userId): array
    {
        $stmt = DB::get()->prepare("
            SELECT
                ed.id,
                ed.status,
                ed.retry_count,
                DATE_FORMAT(ed.sent_at, '%Y-%m-%dT%H:%i:%sZ') AS sent_at,
                ed.error_message,
                c.first_name,
                c.last_name,
                ed.recipient_email AS email
            FROM email_deliveries ed
            JOIN email_campaigns ec ON ed.campaign_id = ec.id
            LEFT JOIN contacts c    ON ed.contact_id  = c.id
            WHERE ed.campaign_id = :campaign_id
              AND ec.user_id     = :user_id
            ORDER BY ed.id ASC
        ");

        $stmt->execute(['campaign_id' => $campaignId, 'user_id' => $userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
