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
                  WHERE user_id = :uid1 AND type = 'mass')           AS total_campaigns,
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

    /** Correos enviados / fallidos (campañas masivas e individuales). */
    public static function getDeliveryTotals(int $userId): array
    {
        $stmt = DB::get()->prepare("
            SELECT
                COALESCE(SUM(ed.status = 'sent'),   0) AS total_sent,
                COALESCE(SUM(ed.status = 'failed'), 0) AS total_failed
            FROM email_deliveries ed
            JOIN email_campaigns ec ON ec.id = ed.campaign_id
            WHERE ec.user_id = :user_id
        ");
        $stmt->execute(['user_id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return ['total_sent' => (int) $row['total_sent'], 'total_failed' => (int) $row['total_failed']];
    }

    /**
     * Correos enviados por dia (UTC) en los ultimos $days dias, rellenando con 0 los dias vacios.
     *
     * @return array<int,array{date:string,sent:int}>
     */
    public static function getActivity(int $userId, int $days = 14): array
    {
        $stmt = DB::get()->prepare("
            SELECT DATE(ed.sent_at) AS day, COUNT(*) AS sent
            FROM email_deliveries ed
            JOIN email_campaigns ec ON ec.id = ed.campaign_id
            WHERE ec.user_id = :user_id
              AND ed.status = 'sent'
              AND ed.sent_at >= UTC_DATE() - INTERVAL " . ($days - 1) . " DAY
            GROUP BY DATE(ed.sent_at)
        ");
        $stmt->execute(['user_id' => $userId]);

        $perDay = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $perDay[$row['day']] = (int) $row['sent'];
        }

        $result = [];
        $today  = new \DateTimeImmutable('today', new \DateTimeZone('UTC'));

        for ($i = $days - 1; $i >= 0; $i--) {
            $day      = $today->modify("-{$i} day")->format('Y-m-d');
            $result[] = ['date' => $day, 'sent' => $perDay[$day] ?? 0];
        }

        return $result;
    }

    public static function getTopTemplate(int $userId): ?array
    {
        $db = DB::get();

        $stmt = $db->prepare("
            SELECT t.name, COUNT(ec.id) AS total
            FROM email_campaigns ec
            JOIN templates t ON ec.template_id = t.id
            WHERE ec.user_id = :user_id
              AND ec.type = 'mass'
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
                COALESCE(NULLIF(TRIM(CONCAT_WS(' ', c.first_name, c.last_name)), ''), c.email) AS name,
                COUNT(ed.id) AS total
            FROM email_deliveries ed
            JOIN contacts c         ON ed.contact_id  = c.id
            JOIN email_campaigns ec ON ed.campaign_id = ec.id
            WHERE ec.user_id = :user_id
              AND ec.type = 'mass'
              AND ed.status   = 'sent'
            GROUP BY c.id, c.first_name, c.last_name, c.email
            ORDER BY total DESC
            LIMIT 1
        ");

        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}
