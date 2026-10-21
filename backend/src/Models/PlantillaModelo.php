<?php

namespace TempliMail\Models;

use TempliMail\Exceptions\ApiExcepcion;
use TempliMail\Utils\BD;
use PDO;

class PlantillaModelo
{
    public static function obtenerTodosPorUsuario(int $userId): array
    {
        $db = BD::obtener();

        $stmt = $db->prepare("
            SELECT id, nombre, asunto, contenido_html,
                   DATE_FORMAT(creado_en, '%Y-%m-%dT%H:%i:%sZ') AS creado_en,
                   DATE_FORMAT(actualizado_en, '%Y-%m-%dT%H:%i:%sZ') AS actualizado_en
            FROM plantillas
            WHERE usuario_id = :usuario_id
              AND eliminado_en IS NULL
            ORDER BY creado_en DESC
        ");

        $stmt->execute(['usuario_id' => $userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function obtenerPorId(int $userId, int $id): ?array
    {
        $db = BD::obtener();

        $stmt = $db->prepare("
            SELECT id, nombre, asunto, contenido_html,
                   DATE_FORMAT(creado_en, '%Y-%m-%dT%H:%i:%sZ') AS creado_en,
                   DATE_FORMAT(actualizado_en, '%Y-%m-%dT%H:%i:%sZ') AS actualizado_en
            FROM plantillas
            WHERE id = :id
              AND usuario_id = :usuario_id
              AND eliminado_en IS NULL
            LIMIT 1
        ");

        $stmt->execute([
            'id' => $id,
            'usuario_id' => $userId
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function crear(int $userId, array $data): void
    {
        $db = BD::obtener();

        $stmt = $db->prepare("
            INSERT INTO plantillas (usuario_id, nombre, asunto, contenido_html)
            VALUES (:usuario_id, :nombre, :asunto, :contenido_html)
        ");

        $stmt->execute([
            'usuario_id' => $userId,
            'nombre' => $data['nombre'],
            'asunto' => $data['asunto'],
            'contenido_html' => $data['contenido_html']
        ]);
    }

    public static function actualizar(int $userId, int $id, array $data): void
    {
        $db = BD::obtener();

        $stmt = $db->prepare("
            UPDATE plantillas
            SET nombre = :nombre,
                asunto = :asunto,
                contenido_html = :contenido_html
            WHERE id = :id
              AND usuario_id = :usuario_id
              AND eliminado_en IS NULL
        ");

        $stmt->execute([
            'id' => $id,
            'usuario_id' => $userId,
            'nombre' => $data['nombre'],
            'asunto' => $data['asunto'],
            'contenido_html' => $data['contenido_html']
        ]);

        // rowCount() vale 0 si no cambio nada: se comprueba la existencia aparte
        if ($stmt->rowCount() === 0 && self::obtenerPorId($userId, $id) === null) {
            throw ApiExcepcion::noEncontrado('Plantilla no encontrada');
        }
    }

    public static function eliminarLogico(int $userId, int $id): void
    {
        $db = BD::obtener();

        $stmt = $db->prepare("
            UPDATE plantillas
            SET eliminado_en = NOW()
            WHERE id = :id
              AND usuario_id = :usuario_id
              AND eliminado_en IS NULL
        ");

        $stmt->execute([
            'id' => $id,
            'usuario_id' => $userId
        ]);

        if ($stmt->rowCount() === 0) {
            throw ApiExcepcion::noEncontrado('Plantilla no encontrada');
        }
    }
}