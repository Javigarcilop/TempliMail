<?php

namespace TempliMail\Models;

use TempliMail\Utils\DB;
use PDO;
use Exception;

class EmailDeliveryModel
{
    public static function insertBatch(int $campaignId, array $contactIds): void
    {
        if (empty($contactIds)) {
            return;
        }

        $db = DB::get();

        $placeholders = [];
        $params = [];

        foreach ($contactIds as $index => $contactId) {
            $placeholders[] = "(:campaign_id, :contact_id_$index, 'pending')";
            $params["contact_id_$index"] = $contactId;
        }

        $params['campaign_id'] = $campaignId;

        $sql = "
            INSERT INTO email_deliveries (campaign_id, contact_id, status)
            VALUES " . implode(',', $placeholders);

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
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
              AND c.deleted_at IS NULL
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

        if ($stmt->rowCount() === 0) {
            throw new Exception('Delivery not found');
        }
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

        if ($stmt->rowCount() === 0) {
            throw new Exception('Delivery not found');
        }
    }
}