<?php

require_once __DIR__ . '/../utils/DB.php';

class MailModel
{
    public static function getTemplateById(int $id): ?array
    {
        $db = DB::get();

        $stmt = $db->prepare("SELECT * FROM plantillas WHERE id = :id");
        $stmt->execute(['id' => $id]);

        return $stmt->fetch() ?: null;
    }

    public static function createEnvio(
        int $plantillaId,
        string $asunto,
        string $mensaje,
        string $estado,
        ?string $fechaProgramada,
        ?string $fechaEnvio
    ): int {
        $db = DB::get();

        $stmt = $db->prepare("
            INSERT INTO envios 
            (plantilla_id, asunto, mensaje, estado, fecha_programada, enviado_en)
            VALUES (:pid, :asunto, :mensaje, :estado, :fp, :fe)
        ");

        $stmt->execute([
            'pid'     => $plantillaId,
            'asunto'  => $asunto,
            'mensaje' => $mensaje,
            'estado'  => $estado,
            'fp'      => $fechaProgramada,
            'fe'      => $fechaEnvio
        ]);

        return (int) $db->lastInsertId();
    }

    public static function getContactsByIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $db = DB::get();
        $in = implode(',', array_fill(0, count($ids), '?'));

        $stmt = $db->prepare(
            "SELECT id, email FROM contactos WHERE id IN ($in)"
        );
        $stmt->execute($ids);

        return $stmt->fetchAll();
    }

    public static function linkEnvioContacto(int $envioId, int $contactoId): void
    {
        $db = DB::get();

        $stmt = $db->prepare(
            "INSERT INTO envios_contacto (envio_id, contacto_id)
             VALUES (:envio, :contacto)"
        );
        $stmt->execute([
            'envio'    => $envioId,
            'contacto' => $contactoId
        ]);
    }

    public static function getHistorial(): array
    {
        $db = DB::get();

        $stmt = $db->query("
            SELECT e.id, e.asunto, e.enviado_en, e.estado, e.fecha_programada,
                   COALESCE(p.nombre, 'Sin plantilla') AS plantilla,
                   COUNT(ec.contacto_id) AS total_destinatarios
            FROM envios e
            LEFT JOIN plantillas p ON e.plantilla_id = p.id
            LEFT JOIN envios_contacto ec ON e.id = ec.envio_id
            GROUP BY e.id
            ORDER BY e.enviado_en DESC
        ");

        return $stmt->fetchAll();
    }

    public static function getPendientes(): array
    {
        $db = DB::get();

        $stmt = $db->prepare("
            SELECT * FROM envios
            WHERE estado = 'pendiente'
              AND fecha_programada <= NOW()
        ");
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public static function getDestinatarios(int $envioId): array
    {
        $db = DB::get();

        $stmt = $db->prepare("
            SELECT c.email
            FROM envios_contacto ec
            JOIN contactos c ON ec.contacto_id = c.id
            WHERE ec.envio_id = :id
        ");
        $stmt->execute(['id' => $envioId]);

        return $stmt->fetchAll();
    }

    public static function marcarEnviado(int $envioId): void
    {
        $db = DB::get();

        $stmt = $db->prepare(
            "UPDATE envios
             SET estado = 'enviado', enviado_en = NOW()
             WHERE id = :id"
        );
        $stmt->execute(['id' => $envioId]);
    }
}
