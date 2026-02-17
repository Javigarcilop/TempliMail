<?php

namespace TempliMail\Models;

use TempliMail\Utils\DB;
use PDO;

class EmailDeliveryModel
{
    public static function insertBatch(int $campaignId, array $contactIds): void
    {
        $db = DB::get();

        $stmt = $db->prepare("
            INSERT INTO email_deliveries
            (campaign_id, contact_id, status)
            VALUES (:campaign_id, :contact_id, 'pending')
        ");

        foreach ($contactIds as $contactId) {
            $stmt->execute([
                'campaign_id' => $campaignId,
                'contact_id' => $contactId
            ]);
        }
    }

    public static function getPendingByCampaign(int $campaignId): array
    {
        $db = DB::get();

        $stmt = $db->prepare("
            SELECT ed.id, ed.contact_id, c.email
            FROM email_deliveries ed
            JOIN contacts c ON ed.contact_id = c.id
            WHERE ed.campaign_id = :campaign_id
              AND ed.status = 'pending'
        ");

        $stmt->execute(['campaign_id' => $campaignId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function markSent(int $deliveryId): void
    {
        $db = DB::get();

        $stmt = $db->prepare("
            UPDATE email_deliveries
            SET status = 'sent',
                sent_at = NOW()
            WHERE id = :id
        ");

        $stmt->execute(['id' => $deliveryId]);
    }

    public static function markFailed(int $deliveryId, string $error): void
    {
        $db = DB::get();

        $stmt = $db->prepare("
            UPDATE email_deliveries
            SET status = 'failed',
                error_message = :error,
                retry_count = retry_count + 1
            WHERE id = :id
        ");

        $stmt->execute([
            'id' => $deliveryId,
            'error' => $error
        ]);
    }
}
