<?php

declare(strict_types=1);

namespace TempliMail\Models;

use PDOException;
use TempliMail\Exceptions\ApiException;
use TempliMail\Utils\DB;
use PDO;

class GroupModel
{
    public static function getAllByUser(int $userId): array
    {
        $stmt = DB::get()->prepare("
            SELECT g.id, g.name, COUNT(c.id) AS member_count
            FROM contact_groups g
            LEFT JOIN contact_group_members m ON m.group_id = g.id
            LEFT JOIN contacts c ON c.id = m.contact_id AND c.deleted_at IS NULL
            WHERE g.user_id = :user_id
            GROUP BY g.id, g.name
            ORDER BY g.name ASC
        ");

        $stmt->execute(['user_id' => $userId]);

        return array_map(static function (array $row): array {
            $row['id']           = (int) $row['id'];
            $row['member_count'] = (int) $row['member_count'];

            return $row;
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public static function create(int $userId, string $name): int
    {
        $name = self::validName($name);

        try {
            $db = DB::get();
            $db->prepare("INSERT INTO contact_groups (user_id, name) VALUES (:user_id, :name)")
               ->execute(['user_id' => $userId, 'name' => $name]);

            return (int) $db->lastInsertId();
        } catch (PDOException $e) {
            throw self::duplicateOrRethrow($e);
        }
    }

    public static function rename(int $userId, int $id, string $name): void
    {
        $name = self::validName($name);

        try {
            DB::get()->prepare("UPDATE contact_groups SET name = :name WHERE id = :id AND user_id = :user_id")
               ->execute(['name' => $name, 'id' => $id, 'user_id' => $userId]);
        } catch (PDOException $e) {
            throw self::duplicateOrRethrow($e);
        }

        if (!self::owns($userId, $id)) {
            throw ApiException::notFound('Grupo no encontrado');
        }
    }

    public static function delete(int $userId, int $id): void
    {
        $stmt = DB::get()->prepare("DELETE FROM contact_groups WHERE id = :id AND user_id = :user_id");
        $stmt->execute(['id' => $id, 'user_id' => $userId]);

        if ($stmt->rowCount() === 0) {
            throw ApiException::notFound('Grupo no encontrado');
        }
    }

    public static function owns(int $userId, int $groupId): bool
    {
        $stmt = DB::get()->prepare("SELECT 1 FROM contact_groups WHERE id = :id AND user_id = :user_id");
        $stmt->execute(['id' => $groupId, 'user_id' => $userId]);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * Pertenencia de los contactos del usuario: [contact_id => [group_id, ...]]
     *
     * @return array<int,int[]>
     */
    public static function membershipsByContact(int $userId): array
    {
        $stmt = DB::get()->prepare("
            SELECT m.contact_id, m.group_id
            FROM contact_group_members m
            JOIN contact_groups g ON g.id = m.group_id
            WHERE g.user_id = :user_id
        ");

        $stmt->execute(['user_id' => $userId]);

        $map = [];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $map[(int) $row['contact_id']][] = (int) $row['group_id'];
        }

        return $map;
    }

    /** Sustituye los grupos de un contacto (solo grupos y contacto del propio usuario). */
    public static function setContactGroups(int $userId, int $contactId, array $groupIds): void
    {
        $groupIds = array_values(array_unique(array_filter(array_map('intval', $groupIds))));

        if (ContactModel::getById($userId, $contactId) === null) {
            throw ApiException::notFound('Contacto no encontrado');
        }

        foreach ($groupIds as $groupId) {
            if (!self::owns($userId, $groupId)) {
                throw ApiException::notFound('Grupo no encontrado');
            }
        }

        $db = DB::get();
        $db->beginTransaction();

        try {
            $db->prepare("
                DELETE m FROM contact_group_members m
                JOIN contact_groups g ON g.id = m.group_id
                WHERE m.contact_id = :contact_id AND g.user_id = :user_id
            ")->execute(['contact_id' => $contactId, 'user_id' => $userId]);

            $insert = $db->prepare("INSERT INTO contact_group_members (group_id, contact_id) VALUES (:group_id, :contact_id)");

            foreach ($groupIds as $groupId) {
                $insert->execute(['group_id' => $groupId, 'contact_id' => $contactId]);
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

        $insert = DB::get()->prepare("INSERT IGNORE INTO contact_group_members (group_id, contact_id) VALUES (:group_id, :contact_id)");

        foreach ($contactIds as $contactId) {
            $insert->execute(['group_id' => $groupId, 'contact_id' => $contactId]);
        }
    }

    // -----------------------------------------------------------------

    private static function validName(string $name): string
    {
        $name = trim($name);

        if ($name === '') {
            throw new ApiException('El nombre del grupo es obligatorio');
        }

        if (mb_strlen($name) > 100) {
            throw new ApiException('El nombre del grupo es demasiado largo (máximo 100 caracteres)');
        }

        return $name;
    }

    private static function duplicateOrRethrow(PDOException $e): \Throwable
    {
        // 23000 = violacion de UNIQUE (nombre repetido para el usuario)
        return $e->getCode() === '23000'
            ? ApiException::conflict('Ya existe un grupo con ese nombre')
            : $e;
    }
}
