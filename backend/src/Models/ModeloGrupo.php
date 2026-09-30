<?php

declare(strict_types=1);

namespace TempliMail\Models;

use PDOException;
use TempliMail\Exceptions\ExcepcionApi;
use TempliMail\Utils\BD;
use PDO;

class ModeloGrupo
{
    public static function getAllByUser(int $userId): array
    {
        $stmt = BD::get()->prepare("
            SELECT g.id, g.nombre, COUNT(c.id) AS total_miembros
            FROM grupos_contacto g
            LEFT JOIN miembros_grupo_contacto m ON m.grupo_id = g.id
            LEFT JOIN contactos c ON c.id = m.contacto_id AND c.eliminado_en IS NULL
            WHERE g.usuario_id = :usuario_id
            GROUP BY g.id, g.nombre
            ORDER BY g.nombre ASC
        ");

        $stmt->execute(['usuario_id' => $userId]);

        return array_map(static function (array $row): array {
            $row['id']           = (int) $row['id'];
            $row['total_miembros'] = (int) $row['total_miembros'];

            return $row;
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public static function create(int $userId, string $nombre): int
    {
        $nombre = self::validName($nombre);

        try {
            $db = BD::get();
            $db->prepare("INSERT INTO grupos_contacto (usuario_id, nombre) VALUES (:usuario_id, :nombre)")
               ->execute(['usuario_id' => $userId, 'nombre' => $nombre]);

            return (int) $db->lastInsertId();
        } catch (PDOException $e) {
            throw self::duplicateOrRethrow($e);
        }
    }

    public static function rename(int $userId, int $id, string $nombre): void
    {
        $nombre = self::validName($nombre);

        try {
            BD::get()->prepare("UPDATE grupos_contacto SET nombre = :nombre WHERE id = :id AND usuario_id = :usuario_id")
               ->execute(['nombre' => $nombre, 'id' => $id, 'usuario_id' => $userId]);
        } catch (PDOException $e) {
            throw self::duplicateOrRethrow($e);
        }

        if (!self::owns($userId, $id)) {
            throw ExcepcionApi::notFound('Grupo no encontrado');
        }
    }

    public static function delete(int $userId, int $id): void
    {
        $stmt = BD::get()->prepare("DELETE FROM grupos_contacto WHERE id = :id AND usuario_id = :usuario_id");
        $stmt->execute(['id' => $id, 'usuario_id' => $userId]);

        if ($stmt->rowCount() === 0) {
            throw ExcepcionApi::notFound('Grupo no encontrado');
        }
    }

    public static function owns(int $userId, int $groupId): bool
    {
        $stmt = BD::get()->prepare("SELECT 1 FROM grupos_contacto WHERE id = :id AND usuario_id = :usuario_id");
        $stmt->execute(['id' => $groupId, 'usuario_id' => $userId]);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * Pertenencia de los contactos del usuario: [contacto_id => [grupo_id, ...]]
     *
     * @return array<int,int[]>
     */
    public static function membershipsByContact(int $userId): array
    {
        $stmt = BD::get()->prepare("
            SELECT m.contacto_id, m.grupo_id
            FROM miembros_grupo_contacto m
            JOIN grupos_contacto g ON g.id = m.grupo_id
            WHERE g.usuario_id = :usuario_id
        ");

        $stmt->execute(['usuario_id' => $userId]);

        $map = [];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $map[(int) $row['contacto_id']][] = (int) $row['grupo_id'];
        }

        return $map;
    }

    /** Sustituye los grupos de un contacto (solo grupos y contacto del propio usuario). */
    public static function setContactGroups(int $userId, int $contactId, array $groupIds): void
    {
        $groupIds = array_values(array_unique(array_filter(array_map('intval', $groupIds))));

        if (ModeloContacto::getById($userId, $contactId) === null) {
            throw ExcepcionApi::notFound('Contacto no encontrado');
        }

        foreach ($groupIds as $groupId) {
            if (!self::owns($userId, $groupId)) {
                throw ExcepcionApi::notFound('Grupo no encontrado');
            }
        }

        $db = BD::get();
        $db->beginTransaction();

        try {
            $db->prepare("
                DELETE m FROM miembros_grupo_contacto m
                JOIN grupos_contacto g ON g.id = m.grupo_id
                WHERE m.contacto_id = :contacto_id AND g.usuario_id = :usuario_id
            ")->execute(['contacto_id' => $contactId, 'usuario_id' => $userId]);

            $insert = $db->prepare("INSERT INTO miembros_grupo_contacto (grupo_id, contacto_id) VALUES (:grupo_id, :contacto_id)");

            foreach ($groupIds as $groupId) {
                $insert->execute(['grupo_id' => $groupId, 'contacto_id' => $contactId]);
            }

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /** @param int[] $contactIds ids recien creados por el propio usuario */
    public static function addContacts(int $groupId, array $contactIds): void
    {
        if ($contactIds === []) {
            return;
        }

        $insert = BD::get()->prepare("INSERT IGNORE INTO miembros_grupo_contacto (grupo_id, contacto_id) VALUES (:grupo_id, :contacto_id)");

        foreach ($contactIds as $contactId) {
            $insert->execute(['grupo_id' => $groupId, 'contacto_id' => $contactId]);
        }
    }

    // -----------------------------------------------------------------

    private static function validName(string $nombre): string
    {
        $nombre = trim($nombre);

        if ($nombre === '') {
            throw new ExcepcionApi('El nombre del grupo es obligatorio');
        }

        if (mb_strlen($nombre) > 100) {
            throw new ExcepcionApi('El nombre del grupo es demasiado largo (máximo 100 caracteres)');
        }

        return $nombre;
    }

    private static function duplicateOrRethrow(PDOException $e): \Throwable
    {
        // 23000 = violacion de UNIQUE (nombre repetido para el usuario)
        return $e->getCode() === '23000'
            ? ExcepcionApi::conflict('Ya existe un grupo con ese nombre')
            : $e;
    }
}
