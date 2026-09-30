<?php

declare(strict_types=1);

namespace TempliMail\Models;

use TempliMail\Utils\BD;
use PDO;

class ModeloPanel
{
    public static function getStats(int $userId): array
    {
        $db = BD::get();

        $stmt = $db->prepare("
            SELECT
                (SELECT COUNT(*) FROM campanas_correo
                  WHERE usuario_id = :uid1 AND tipo = 'mass')           AS total_campanas,
                (SELECT COUNT(*) FROM contactos
                  WHERE usuario_id = :uid2 AND eliminado_en IS NULL)      AS total_contactos,
                (SELECT COUNT(*) FROM plantillas
                  WHERE usuario_id = :uid3 AND eliminado_en IS NULL)      AS total_plantillas
        ");

        $stmt->execute([
            'uid1' => $userId,
            'uid2' => $userId,
            'uid3' => $userId,
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [
            'total_campanas'   => 0,
            'total_contactos'  => 0,
            'total_plantillas' => 0,
        ];
    }

    /** Correos enviados / fallidos (campañas masivas e individuales). */
    public static function getDeliveryTotals(int $userId): array
    {
        $stmt = BD::get()->prepare("
            SELECT
                COALESCE(SUM(ed.estado = 'sent'),   0) AS total_enviados,
                COALESCE(SUM(ed.estado = 'failed'), 0) AS total_fallidos
            FROM entregas_correo ed
            JOIN campanas_correo ec ON ec.id = ed.campana_id
            WHERE ec.usuario_id = :usuario_id
        ");
        $stmt->execute(['usuario_id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return ['total_enviados' => (int) $row['total_enviados'], 'total_fallidos' => (int) $row['total_fallidos']];
    }

    /**
     * Correos enviados por dia (UTC) en los ultimos $days dias, rellenando con 0 los dias vacios.
     *
     * @return array<int,array{fecha:string,enviados:int}>
     */
    public static function getActivity(int $userId, int $days = 14): array
    {
        $stmt = BD::get()->prepare("
            SELECT DATE(ed.enviado_en) AS dia, COUNT(*) AS enviados
            FROM entregas_correo ed
            JOIN campanas_correo ec ON ec.id = ed.campana_id
            WHERE ec.usuario_id = :usuario_id
              AND ed.estado = 'sent'
              AND ed.enviado_en >= UTC_DATE() - INTERVAL " . ($days - 1) . " DAY
            GROUP BY DATE(ed.enviado_en)
        ");
        $stmt->execute(['usuario_id' => $userId]);

        $porDia = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $porDia[$row['dia']] = (int) $row['enviados'];
        }

        $result = [];
        $hoy  = new \DateTimeImmutable('today', new \DateTimeZone('UTC'));

        for ($i = $days - 1; $i >= 0; $i--) {
            $dia      = $hoy->modify("-{$i} day")->format('Y-m-d');
            $result[] = ['fecha' => $dia, 'enviados' => $porDia[$dia] ?? 0];
        }

        return $result;
    }

    public static function getTopTemplate(int $userId): ?array
    {
        $db = BD::get();

        $stmt = $db->prepare("
            SELECT t.nombre, COUNT(ec.id) AS total
            FROM campanas_correo ec
            JOIN plantillas t ON ec.plantilla_id = t.id
            WHERE ec.usuario_id = :usuario_id
              AND ec.tipo = 'mass'
            GROUP BY t.id, t.nombre
            ORDER BY total DESC
            LIMIT 1
        ");

        $stmt->execute(['usuario_id' => $userId]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function getTopContact(int $userId): ?array
    {
        $db = BD::get();

        $stmt = $db->prepare("
            SELECT
                COALESCE(NULLIF(TRIM(CONCAT_WS(' ', c.nombre, c.apellidos)), ''), c.correo) AS nombre,
                COUNT(ed.id) AS total
            FROM entregas_correo ed
            JOIN contactos c         ON ed.contacto_id  = c.id
            JOIN campanas_correo ec ON ed.campana_id = ec.id
            WHERE ec.usuario_id = :usuario_id
              AND ec.tipo = 'mass'
              AND ed.estado   = 'sent'
            GROUP BY c.id, c.nombre, c.apellidos, c.correo
            ORDER BY total DESC
            LIMIT 1
        ");

        $stmt->execute(['usuario_id' => $userId]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}
