<?php

declare(strict_types=1);

namespace TempliMail\Models;

use TempliMail\Exceptions\ExcepcionApi;
use TempliMail\Utils\BD;
use PDO;

/**
 * Ciclo de vida de una campana:
 *   scheduled --(worker la reclama)--> processing --> completed
 *   scheduled --(usuario)--> cancelled
 *   completed --(reintentar fallidos)--> scheduled
 *
 * "scheduled" con programado_en = NULL significa "en cola, enviar ya".
 * Todas las fechas se guardan en UTC y se devuelven en ISO 8601 (sufijo Z).
 */
class ModeloCampanaCorreo
{
    /** Minutos sin latido tras los cuales una campana "processing" se da por abandonada. */
    private const STALE_MINUTES = 5;

    private const ISO = "'%Y-%m-%dT%H:%i:%sZ'";

    public static function create(
        int $userId,
        ?int $templateId,
        ?string $nombre,
        string $tipo,
        string $asunto,
        string $content,
        string $estado,
        ?string $scheduledAtUtc
    ): int {
        $db = BD::get();

        $stmt = $db->prepare("
            INSERT INTO campanas_correo
            (usuario_id, plantilla_id, nombre, tipo, asunto, contenido_html, estado, programado_en)
            VALUES (:usuario_id, :plantilla_id, :nombre, :tipo, :asunto, :contenido_html, :estado, :programado_en)
        ");

        $stmt->execute([
            'usuario_id'      => $userId,
            'plantilla_id'  => $templateId,
            'nombre'         => $nombre,
            'tipo'         => $tipo,
            'asunto'      => $asunto,
            'contenido_html' => $content,
            'estado'       => $estado,
            'programado_en' => $scheduledAtUtc,
        ]);

        return (int) $db->lastInsertId();
    }

    /** Uso interno (worker): sin filtrar por usuario. */
    public static function findById(int $campaignId): ?array
    {
        $stmt = BD::get()->prepare("
            SELECT id, usuario_id, asunto, contenido_html, estado, tipo
            FROM campanas_correo
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
        $stmt = BD::get()->prepare("
            UPDATE campanas_correo
            SET estado = 'processing',
                procesamiento_iniciado_en = UTC_TIMESTAMP()
            WHERE id = :id
              AND (
                    (estado = 'scheduled'
                       AND (programado_en IS NULL OR programado_en <= UTC_TIMESTAMP()))
                 OR (estado = 'processing'
                       AND procesamiento_iniciado_en < UTC_TIMESTAMP() - INTERVAL " . self::STALE_MINUTES . " MINUTE)
              )
        ");

        $stmt->execute(['id' => $campaignId]);

        return $stmt->rowCount() === 1;
    }

    /** Latido: indica que la campana sigue procesandose. */
    public static function heartbeat(int $campaignId): void
    {
        BD::get()
            ->prepare("UPDATE campanas_correo SET procesamiento_iniciado_en = UTC_TIMESTAMP() WHERE id = :id AND estado = 'processing'")
            ->execute(['id' => $campaignId]);
    }

    public static function markCompleted(int $campaignId): void
    {
        BD::get()
            ->prepare("UPDATE campanas_correo SET estado = 'completed', procesamiento_iniciado_en = NULL WHERE id = :id")
            ->execute(['id' => $campaignId]);
    }

    /** Campanas listas para enviarse (de todos los usuarios) o abandonadas a medias. */
    public static function getDue(int $limit = 10): array
    {
        $stmt = BD::get()->prepare("
            SELECT id, usuario_id
            FROM campanas_correo
            WHERE (estado = 'scheduled'
                     AND (programado_en IS NULL OR programado_en <= UTC_TIMESTAMP()))
               OR (estado = 'processing'
                     AND procesamiento_iniciado_en < UTC_TIMESTAMP() - INTERVAL " . self::STALE_MINUTES . " MINUTE)
            ORDER BY COALESCE(programado_en, creado_en) ASC
            LIMIT :limit
        ");

        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Cancela una campana programada que aun no ha empezado a enviarse. */
    public static function cancel(int $userId, int $campaignId): void
    {
        $db = BD::get();
        $db->beginTransaction();

        try {
            $stmt = $db->prepare("
                UPDATE campanas_correo
                SET estado = 'cancelled'
                WHERE id = :id AND usuario_id = :usuario_id AND estado = 'scheduled'
            ");
            $stmt->execute(['id' => $campaignId, 'usuario_id' => $userId]);

            if ($stmt->rowCount() === 0) {
                throw new ExcepcionApi('Solo se pueden cancelar campañas programadas que no hayan empezado', 409);
            }

            $db->prepare("
                UPDATE entregas_correo
                SET estado = 'skipped', mensaje_error = 'Campaña cancelada'
                WHERE campana_id = :id AND estado = 'pending'
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
        $db = BD::get();
        $db->beginTransaction();

        try {
            $stmt = $db->prepare("
                SELECT estado FROM campanas_correo
                WHERE id = :id AND usuario_id = :usuario_id
                FOR UPDATE
            ");
            $stmt->execute(['id' => $campaignId, 'usuario_id' => $userId]);
            $estado = $stmt->fetchColumn();

            if ($estado === false) {
                throw ExcepcionApi::notFound('Campaña no encontrada');
            }

            if ($estado !== 'completed') {
                throw new ExcepcionApi('Solo se pueden reintentar campañas ya finalizadas', 409);
            }

            $stmt = $db->prepare("
                UPDATE entregas_correo
                SET estado = 'pending', mensaje_error = NULL, reintentos = 0
                WHERE campana_id = :id AND estado = 'failed'
            ");
            $stmt->execute(['id' => $campaignId]);
            $requeued = $stmt->rowCount();

            if ($requeued === 0) {
                throw new ExcepcionApi('La campaña no tiene entregas fallidas', 409);
            }

            $db->prepare("
                UPDATE campanas_correo
                SET estado = 'scheduled', programado_en = NULL
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

        $stmt = BD::get()->prepare("
            SELECT
                ec.id,
                ec.nombre,
                ec.tipo,
                ec.asunto,
                ec.estado,
                DATE_FORMAT(ec.programado_en, $iso) AS programado_en,
                DATE_FORMAT(ec.creado_en,   $iso) AS creado_en,
                DATE_FORMAT(ec.actualizado_en,   $iso) AS actualizado_en,
                t.nombre                                        AS nombre_plantilla,
                COUNT(ed.id)                                  AS total_destinatarios,
                COALESCE(SUM(ed.estado = 'sent'),    0)      AS enviados,
                COALESCE(SUM(ed.estado = 'failed'),  0)      AS fallidos,
                COALESCE(SUM(ed.estado = 'pending'), 0)      AS pendientes,
                COALESCE(SUM(ed.estado = 'skipped'), 0)      AS omitidos
            FROM campanas_correo ec
            LEFT JOIN plantillas t         ON ec.plantilla_id = t.id
            LEFT JOIN entregas_correo ed ON ed.campana_id = ec.id
            WHERE ec.usuario_id = :usuario_id
            GROUP BY ec.id, ec.nombre, ec.tipo, ec.asunto, ec.estado,
                     ec.programado_en, ec.creado_en, ec.actualizado_en, t.nombre
            ORDER BY ec.creado_en DESC, ec.id DESC
        ");

        $stmt->execute(['usuario_id' => $userId]);

        return array_map(static function (array $row): array {
            foreach (['total_destinatarios', 'enviados', 'fallidos', 'pendientes', 'omitidos'] as $field) {
                $row[$field] = (int) $row[$field];
            }

            return $row;
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }
}
