<?php

namespace TempliMail\Models;
use TempliMail\Utils\DB;
use PDO;
class TemplateModel
{
    public static function getAll(): array
    {
        $db = DB::get();

        $stmt = $db->query(
            "SELECT * FROM plantillas ORDER BY creado_en DESC"
        );

        return $stmt->fetchAll();
    }

    public static function create(array $data): void
    {
        $db = DB::get();

        $stmt = $db->prepare(
            "INSERT INTO plantillas (nombre, asunto, contenido_html)
             VALUES (:nombre, :asunto, :contenido_html)"
        );

        $stmt->execute([
            'nombre'         => $data['nombre'],
            'asunto'         => $data['asunto'],
            'contenido_html' => $data['contenido_html']
        ]);
    }

    public static function update(int $id, array $data): void
    {
        $db = DB::get();

        $stmt = $db->prepare(
            "UPDATE plantillas
             SET nombre = :nombre,
                 asunto = :asunto,
                 contenido_html = :contenido_html
             WHERE id = :id"
        );

        $stmt->execute([
            'id'             => $id,
            'nombre'         => $data['nombre'],
            'asunto'         => $data['asunto'],
            'contenido_html' => $data['contenido_html']
        ]);
    }

    public static function delete(int $id): void
    {
        $db = DB::get();

        // Desvincular envíos primero (integridad)
        $update = $db->prepare(
            "UPDATE envios SET plantilla_id = NULL WHERE plantilla_id = :id"
        );
        $update->execute(['id' => $id]);

        // Eliminar plantilla
        $stmt = $db->prepare(
            "DELETE FROM plantillas WHERE id = :id"
        );
        $stmt->execute(['id' => $id]);
    }
}
