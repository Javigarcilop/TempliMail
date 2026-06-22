<?php

declare(strict_types=1);

namespace TempliMail\Models;

use TempliMail\Utils\DB;
use PDO;

class DashboardModel
{
    public static function getStats(int $userId): array
    {
        $db = DB::get();

        $stmt = $db->prepare("
            SELECT
                (SELECT COUNT(*) FROM email_campaigns
                  WHERE user_id = :uid1)                             AS total_campaigns,
                (SELECT COUNT(*) FROM contacts
                  WHERE user_id = :uid2 AND deleted_at IS NULL)      AS total_contacts,
                (SELECT COUNT(*) FROM templates
                  WHERE user_id = :uid3 AND deleted_at IS NULL)      AS total_templates
        ");

        $stmt->execute([
            'uid1' => $userId,
            'uid2' => $userId,
            'uid3' => $userId,
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [
            'total_campaigns' => 0,
            'total_contacts'  => 0,
            'total_templates' => 0,
        ];
    }

    public static function getTopTemplate(int $userId): ?array
    {
        $db = DB::get();

        $stmt = $db->prepare("
            SELECT t.name, COUNT(ec.id) AS total
            FROM email_campaigns ec
            JOIN templates t ON ec.template_id = t.id
            WHERE ec.user_id = :user_id
            GROUP BY t.id, t.name
            ORDER BY total DESC
            LIMIT 1
        ");

        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function getTopContact(int $userId): ?array
    {
        $db = DB::get();

        $stmt = $db->prepare("
            SELECT
                CONCAT(c.first_name, ' ', c.last_name) AS name,
                COUNT(ed.id) AS total
            FROM email_deliveries ed
            JOIN contacts c        ON ed.contact_id  = c.id
            JOIN email_campaigns ec ON ed.campaign_id = ec.id
            WHERE ec.user_id = :user_id
              AND ed.status   = 'sent'
            GROUP BY c.id, c.first_name, c.last_name
            ORDER BY total DESC
            LIMIT 1
        ");

        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}
