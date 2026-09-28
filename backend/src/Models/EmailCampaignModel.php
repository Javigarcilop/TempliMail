<?php

declare(strict_types=1);

namespace TempliMail\Models;

use TempliMail\Exceptions\ApiException;
use TempliMail\Utils\DB;
use PDO;

/**
 * Ciclo de vida de una campana:
 *   scheduled --(worker la reclama)--> processing --> completed
 *   scheduled --(usuario)--> cancelled
 *   completed --(reintentar fallidos)--> scheduled
 *
 * "scheduled" con scheduled_at = NULL significa "en cola, enviar ya".
 * Todas las fechas se guardan en UTC y se devuelven en ISO 8601 (sufijo Z).
 */
class EmailCampaignModel
{
    /** Minutos sin latido tras los cuales una campana "processing" se da por abandonada. */
    private const STALE_MINUTES = 5;

    private const ISO = "'%Y-%m-%dT%H:%i:%sZ'";

    public static function create(
        int $userId,
        ?int $templateId,
        ?string $name,
        string $type,
        string $subject,
        string $content,
        string $status,
        ?string $scheduledAtUtc
    ): int {
        $db = DB::get();

        $stmt = $db->prepare("
            INSERT INTO email_campaigns
            (user_id, template_id, name, type, subject, content_html, status, scheduled_at)
            VALUES (:user_id, :template_id, :name, :type, :subject, :content_html, :status, :scheduled_at)
        ");

        $stmt->execute([
            'user_id'      => $userId,
            'template_id'  => $templateId,
            'name'         => $name,
            'type'         => $type,
            'subject'      => $subject,
            'content_html' => $content,
            'status'       => $status,
            'scheduled_at' => $scheduledAtUtc,
        ]);

        return (int) $db->lastInsertId();
    }

    /** Uso interno (worker): sin filtrar por usuario. */
    public static function findById(int $campaignId): ?array
    {
        $stmt = DB::get()->prepare("
            SELECT id, user_id, subject, content_html, status, type
            FROM email_campaigns
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->execute(['id' => $campaignId]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Reclama la campana de forma atomica. Devuelve false si otro proceso
     * ya la esta enviando, no esta lista o no existe: evita envios duplicados.
     */
    public static function claim(int $campaignId): bool
    {
        $stmt = DB::get()->prepare("
            UPDATE email_campaigns
            SET status = 'processing',
                processing_started_at = UTC_TIMESTAMP()
            WHERE id = :id
              AND (
                    (status = 'scheduled'
                       AND (scheduled_at IS NULL OR scheduled_at <= UTC_TIMESTAMP()))
                 OR (status = 'processing'
                       AND processing_started_at < UTC_TIMESTAMP() - INTERVAL " . self::STALE_MINUTES . " MINUTE)
              )
        ");

        $stmt->execute(['id' => $campaignId]);

        return $stmt->rowCount() === 1;
    }

    /** Latido: indica que la campana sigue procesandose. */
    public static function heartbeat(int $campaignId): void
    {
        DB::get()
            ->prepare("UPDATE email_campaigns SET processing_started_at = UTC_TIMESTAMP() WHERE id = :id AND status = 'processing'")
            ->execute(['id' => $campaignId]);
    }

    public static function markCompleted(int $campaignId): void
    {
        DB::get()
            ->prepare("UPDATE email_campaigns SET status = 'completed', processing_started_at = NULL WHERE id = :id")
            ->execute(['id' => $campaignId]);
    }

    /** Campanas listas para enviarse (de todos los usuarios) o abandonadas a medias. */
    public static function getDue(int $limit = 10): array
    {
        $stmt = DB::get()->prepare("
            SELECT id, user_id
            FROM email_campaigns
            WHERE (status = 'scheduled'
                     AND (scheduled_at IS NULL OR scheduled_at <= UTC_TIMESTAMP()))
               OR (status = 'processing'
                     AND processing_started_at < UTC_TIMESTAMP() - INTERVAL " . self::STALE_MINUTES . " MINUTE)
            ORDER BY COALESCE(scheduled_at, created_at) ASC
            LIMIT :limit
        ");

        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Cancela una campana programada que aun no ha empezado a enviarse. */
    public static function cancel(int $userId, int $campaignId): void
    {
        $db = DB::get();
        $db->beginTransaction();

        try {
            $stmt = $db->prepare("
                UPDATE email_campaigns
                SET status = 'cancelled'
                WHERE id = :id AND user_id = :user_id AND status = 'scheduled'
            ");
            $stmt->execute(['id' => $campaignId, 'user_id' => $userId]);

            if ($stmt->rowCount() === 0) {
                throw new ApiException('Solo se pueden cancelar campañas programadas que no hayan empezado', 409);
            }

            $db->prepare("
                UPDATE email_deliveries
                SET status = 'skipped', error_message = 'Campaña cancelada'
                WHERE campaign_id = :id AND status = 'pending'
            ")->execute(['id' => $campaignId]);

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /** Vuelve a encolar las entregas fallidas de una campana ya terminada. */
    public static function retryFailed(int $userId, int $campaignId): int
    {
        $db = DB::get();
        $db->beginTransaction();

        try {
            $stmt = $db->prepare("
                SELECT status FROM email_campaigns
                WHERE id = :id AND user_id = :user_id
                FOR UPDATE
            ");
            $stmt->execute(['id' => $campaignId, 'user_id' => $userId]);
            $status = $stmt->fetchColumn();

            if ($status === false) {
                throw ApiException::notFound('Campaña no encontrada');
            }

            if ($status !== 'completed') {
                throw new ApiException('Solo se pueden reintentar campañas ya finalizadas', 409);
            }

            $stmt = $db->prepare("
                UPDATE email_deliveries
                SET status = 'pending', error_message = NULL, retry_count = 0
                WHERE campaign_id = :id AND status = 'failed'
            ");
            $stmt->execute(['id' => $campaignId]);
            $requeued = $stmt->rowCount();

            if ($requeued === 0) {
                throw new ApiException('La campaña no tiene entregas fallidas', 409);
            }

            $db->prepare("
                UPDATE email_campaigns
                SET status = 'scheduled', scheduled_at = NULL
                WHERE id = :id
            ")->execute(['id' => $campaignId]);

            $db->commit();

            return $requeued;
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public static function getAllByUser(int $userId): array
    {
        $iso = self::ISO;

        $stmt = DB::get()->prepare("
            SELECT
                ec.id,
                ec.name,
                ec.type,
                ec.subject,
                ec.status,
                DATE_FORMAT(ec.scheduled_at, $iso) AS scheduled_at,
                DATE_FORMAT(ec.created_at,   $iso) AS created_at,
                DATE_FORMAT(ec.updated_at,   $iso) AS updated_at,
                t.name                                        AS template_name,
                COUNT(ed.id)                                  AS total_recipients,
                COALESCE(SUM(ed.status = 'sent'),    0)      AS sent,
                COALESCE(SUM(ed.status = 'failed'),  0)      AS failed,
                COALESCE(SUM(ed.status = 'pending'), 0)      AS pending,
                COALESCE(SUM(ed.status = 'skipped'), 0)      AS skipped
            FROM email_campaigns ec
            LEFT JOIN templates t         ON ec.template_id = t.id
            LEFT JOIN email_deliveries ed ON ed.campaign_id = ec.id
            WHERE ec.user_id = :user_id
            GROUP BY ec.id, ec.name, ec.type, ec.subject, ec.status,
                     ec.scheduled_at, ec.created_at, ec.updated_at, t.name
            ORDER BY ec.created_at DESC, ec.id DESC
        ");

        $stmt->execute(['user_id' => $userId]);

        return array_map(static function (array $row): array {
            foreach (['total_recipients', 'sent', 'failed', 'pending', 'skipped'] as $field) {
                $row[$field] = (int) $row[$field];
            }

            return $row;
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }
}
