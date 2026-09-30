<?php

declare(strict_types=1);

namespace TempliMail\Models;

use TempliMail\Utils\BD;
use PDO;

class ModeloEntregaCorreo
{
    /**
     * @param array<int,array{id:int|string|null,correo:string}> $recipients
     *        contacto_id puede ser null (envio individual)
     */
    public static function insertBatch(int $campaignId, array $recipients): void
    {
        if ($recipients === []) {
            return;
        }

        $db = BD::get();

        foreach (array_chunk($recipients, 500) as $chunk) {
            $rows   = [];
            $params = [];

            foreach ($chunk as $recipient) {
                $rows[]   = '(?, ?, ?, \'pending\')';
                $params[] = $campaignId;
                $params[] = $recipient['id'] ?? null;
                $params[] = $recipient['correo'];
            }

            $db->prepare(
                'INSERT INTO entregas_correo (campana_id, contacto_id, correo_destinatario, estado) VALUES '
                . implode(',', $rows)
            )->execute($params);
        }
    }

    /**
     * Entregas pendientes con los datos del contacto (para personalizar el correo).
     * Los contactos borrados aparecen con contacto_id NULL.
     */
    public static function getPendingByCampaign(int $campaignId, int $limit = 50): array
    {
        $stmt = BD::get()->prepare("
            SELECT ed.id, ed.contacto_id, ed.correo_destinatario,
                   c.nombre, c.apellidos, c.empresa, c.cargo,
                   c.baja_en
            FROM entregas_correo ed
            LEFT JOIN contactos c ON ed.contacto_id = c.id AND c.eliminado_en IS NULL
            WHERE ed.campana_id = :campana_id
              AND ed.estado = 'pending'
            ORDER BY ed.id ASC
            LIMIT :limit
        ");

        $stmt->bindValue('campana_id', $campaignId, PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function markSent(int $deliveryId, int $attempts = 1): void
    {
        BD::get()->prepare("
            UPDATE entregas_correo
            SET estado = 'sent', enviado_en = UTC_TIMESTAMP(), mensaje_error = NULL, reintentos = :attempts
            WHERE id = :id
        ")->execute(['id' => $deliveryId, 'attempts' => max(0, $attempts - 1)]);
    }

    public static function markFailed(int $deliveryId, string $error, int $attempts = 1): void
    {
        BD::get()->prepare("
            UPDATE entregas_correo
            SET estado = 'failed', mensaje_error = :error, reintentos = :attempts
            WHERE id = :id
        ")->execute([
            'id'       => $deliveryId,
            'error'    => mb_substr($error, 0, 1000),
            'attempts' => $attempts,
        ]);
    }

    public static function markSkipped(int $deliveryId, string $reason): void
    {
        BD::get()->prepare("
            UPDATE entregas_correo
            SET estado = 'skipped', mensaje_error = :reason
            WHERE id = :id
        ")->execute(['id' => $deliveryId, 'reason' => $reason]);
    }

    public static function getByCampaign(int $campaignId, int $userId): array
    {
        $stmt = BD::get()->prepare("
            SELECT
                ed.id,
                ed.estado,
                ed.reintentos,
                DATE_FORMAT(ed.enviado_en, '%Y-%m-%dT%H:%i:%sZ') AS enviado_en,
                ed.mensaje_error,
                c.nombre,
                c.apellidos,
                ed.correo_destinatario AS correo
            FROM entregas_correo ed
            JOIN campanas_correo ec ON ed.campana_id = ec.id
            LEFT JOIN contactos c    ON ed.contacto_id  = c.id
            WHERE ed.campana_id = :campana_id
              AND ec.usuario_id     = :usuario_id
            ORDER BY ed.id ASC
        ");

        $stmt->execute(['campana_id' => $campaignId, 'usuario_id' => $userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
