<?php

declare(strict_types=1);

namespace TempliMail\Models;

use TempliMail\Exceptions\ApiException;
use TempliMail\Utils\DB;
use PDO;

class ContactModel
{
    public static function getAllByUser(int $userId): array
    {
        $stmt = DB::get()->prepare("
            SELECT id, first_name, last_name, email, phone, company, position,
                   DATE_FORMAT(unsubscribed_at, '%Y-%m-%dT%H:%i:%sZ') AS unsubscribed_at,
                   DATE_FORMAT(created_at,      '%Y-%m-%dT%H:%i:%sZ') AS created_at,
                   DATE_FORMAT(updated_at,      '%Y-%m-%dT%H:%i:%sZ') AS updated_at
            FROM contacts
            WHERE user_id = :user_id
              AND deleted_at IS NULL
            ORDER BY created_at DESC, id DESC
        ");

        $stmt->execute(['user_id' => $userId]);

        $contacts    = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $memberships = GroupModel::membershipsByContact($userId);

        foreach ($contacts as &$contact) {
            $contact['group_ids'] = $memberships[(int) $contact['id']] ?? [];
        }

        return $contacts;
    }

    /**
     * De los ids recibidos, devuelve SOLO los contactos que pertenecen al usuario,
     * no estan eliminados y siguen suscritos. Evita enviar correo a contactos ajenos.
     *
     * @param  int[] $ids
     * @return array<int,array<string,mixed>>
     */
    public static function getSendableByIds(int $userId, array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));

        if ($ids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $stmt = DB::get()->prepare("
            SELECT id, email
            FROM contacts
            WHERE user_id = ?
              AND deleted_at IS NULL
              AND unsubscribed_at IS NULL
              AND id IN ($placeholders)
        ");

        $stmt->execute([$userId, ...$ids]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getById(int $userId, int $id): ?array
    {
        $stmt = DB::get()->prepare("
            SELECT id, first_name, last_name, email, company, position
            FROM contacts
            WHERE id = :id AND user_id = :user_id AND deleted_at IS NULL
            LIMIT 1
        ");

        $stmt->execute(['id' => $id, 'user_id' => $userId]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function create(int $userId, array $data): int
    {
        $email = self::validEmail($data['email'] ?? '');

        if (self::emailExists($userId, $email)) {
            throw ApiException::conflict('Ya existe un contacto con ese email');
        }

        $db   = DB::get();
        $stmt = $db->prepare("
            INSERT INTO contacts
            (user_id, first_name, last_name, email, phone, company, position)
            VALUES (:user_id, :first_name, :last_name, :email, :phone, :company, :position)
        ");

        $stmt->execute([
            'user_id'    => $userId,
            'first_name' => self::nullable($data['first_name'] ?? null),
            'last_name'  => self::nullable($data['last_name'] ?? null),
            'email'      => $email,
            'phone'      => self::nullable($data['phone'] ?? null),
            'company'    => self::nullable($data['company'] ?? null),
            'position'   => self::nullable($data['position'] ?? null),
        ]);

        return (int) $db->lastInsertId();
    }

    public static function update(int $userId, int $id, array $data): void
    {
        $email = self::validEmail($data['email'] ?? '');

        if (self::emailExistsForAnotherContact($userId, $email, $id)) {
            throw ApiException::conflict('Ya existe otro contacto con ese email');
        }

        $stmt = DB::get()->prepare("
            UPDATE contacts
            SET first_name = :first_name,
                last_name  = :last_name,
                email      = :email,
                phone      = :phone,
                company    = :company,
                position   = :position
            WHERE id = :id
              AND user_id = :user_id
              AND deleted_at IS NULL
        ");

        $stmt->execute([
            'id'         => $id,
            'user_id'    => $userId,
            'first_name' => self::nullable($data['first_name'] ?? null),
            'last_name'  => self::nullable($data['last_name'] ?? null),
            'email'      => $email,
            'phone'      => self::nullable($data['phone'] ?? null),
            'company'    => self::nullable($data['company'] ?? null),
            'position'   => self::nullable($data['position'] ?? null),
        ]);

        // rowCount() vale 0 si no cambio nada: se comprueba la existencia aparte
        if ($stmt->rowCount() === 0 && self::getById($userId, $id) === null) {
            throw ApiException::notFound('Contacto no encontrado');
        }
    }

    public static function delete(int $userId, int $id): void
    {
        $stmt = DB::get()->prepare("
            DELETE FROM contacts
            WHERE id = :id AND user_id = :user_id
        ");

        $stmt->execute(['id' => $id, 'user_id' => $userId]);

        if ($stmt->rowCount() === 0) {
            throw ApiException::notFound('Contacto no encontrado');
        }
    }

    /**
     * Importacion masiva (CSV ya parseado en el navegador).
     * Se omiten emails repetidos (en el fichero o ya existentes) y se informan los invalidos.
     *
     * @param  array<int,array<string,mixed>> $rows
     * @return array{created:int,duplicates:int,invalid:array<int,array{row:int,email:string,reason:string}>}
     */
    public static function importMany(int $userId, array $rows, ?int $groupId = null): array
    {
        if ($groupId !== null && !GroupModel::owns($userId, $groupId)) {
            throw ApiException::notFound('Grupo no encontrado');
        }

        $db = DB::get();

        $stmt = $db->prepare("SELECT LOWER(email) FROM contacts WHERE user_id = :user_id AND deleted_at IS NULL");
        $stmt->execute(['user_id' => $userId]);
        $known = array_flip($stmt->fetchAll(PDO::FETCH_COLUMN));

        $insert = $db->prepare("
            INSERT INTO contacts (user_id, first_name, last_name, email, phone, company, position)
            VALUES (:user_id, :first_name, :last_name, :email, :phone, :company, :position)
        ");

        $created    = 0;
        $duplicates = 0;
        $invalid    = [];
        $newIds     = [];

        $db->beginTransaction();

        try {
            foreach ($rows as $index => $row) {
                $email = trim((string) ($row['email'] ?? ''));

                if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 255) {
                    if (count($invalid) < 50) {
                        $invalid[] = [
                            'row'    => $index + 1,
                            'email'  => mb_substr($email, 0, 80),
                            'reason' => $email === '' ? 'Email vacío' : 'Email no válido',
                        ];
                    }
                    continue;
                }

                if (isset($known[strtolower($email)])) {
                    $duplicates++;
                    continue;
                }

                $insert->execute([
                    'user_id'    => $userId,
                    'first_name' => self::nullable(isset($row['first_name']) ? mb_substr((string) $row['first_name'], 0, 100) : null),
                    'last_name'  => self::nullable(isset($row['last_name']) ? mb_substr((string) $row['last_name'], 0, 100) : null),
                    'email'      => $email,
                    'phone'      => self::nullable(isset($row['phone']) ? mb_substr((string) $row['phone'], 0, 50) : null),
                    'company'    => self::nullable(isset($row['company']) ? mb_substr((string) $row['company'], 0, 150) : null),
                    'position'   => self::nullable(isset($row['position']) ? mb_substr((string) $row['position'], 0, 150) : null),
                ]);

                $known[strtolower($email)] = true;
                $newIds[] = (int) $db->lastInsertId();
                $created++;
            }

            if ($groupId !== null) {
                GroupModel::addContacts($groupId, $newIds);
            }

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        return ['created' => $created, 'duplicates' => $duplicates, 'invalid' => $invalid];
    }

    /** Baja / alta manual desde el panel. */
    public static function setSubscribed(int $userId, int $id, bool $subscribed): void
    {
        $stmt = DB::get()->prepare("
            UPDATE contacts
            SET unsubscribed_at = " . ($subscribed ? 'NULL' : 'UTC_TIMESTAMP()') . "
            WHERE id = :id AND user_id = :user_id AND deleted_at IS NULL
        ");

        $stmt->execute(['id' => $id, 'user_id' => $userId]);

        if ($stmt->rowCount() === 0 && self::getById($userId, $id) === null) {
            throw ApiException::notFound('Contacto no encontrado');
        }
    }

    /** Baja pedida por el propio contacto desde el enlace del correo. */
    public static function unsubscribe(int $id): ?string
    {
        $db = DB::get();

        $stmt = $db->prepare("SELECT email FROM contacts WHERE id = :id AND deleted_at IS NULL");
        $stmt->execute(['id' => $id]);
        $email = $stmt->fetchColumn();

        if ($email === false) {
            return null;
        }

        $db->prepare("
            UPDATE contacts
            SET unsubscribed_at = COALESCE(unsubscribed_at, UTC_TIMESTAMP())
            WHERE id = :id
        ")->execute(['id' => $id]);

        return (string) $email;
    }

    // -----------------------------------------------------------------

    private static function validEmail(mixed $value): string
    {
        $email = trim((string) $value);

        if ($email === '') {
            throw new ApiException('El email es obligatorio');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new ApiException('El formato del email no es valido');
        }

        return $email;
    }

    private static function nullable(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    private static function emailExists(int $userId, string $email): bool
    {
        $stmt = DB::get()->prepare("
            SELECT 1 FROM contacts
            WHERE user_id = :user_id AND email = :email AND deleted_at IS NULL
            LIMIT 1
        ");

        $stmt->execute(['user_id' => $userId, 'email' => $email]);

        return (bool) $stmt->fetchColumn();
    }

    private static function emailExistsForAnotherContact(int $userId, string $email, int $contactId): bool
    {
        $stmt = DB::get()->prepare("
            SELECT 1 FROM contacts
            WHERE user_id = :user_id AND email = :email AND id != :id AND deleted_at IS NULL
            LIMIT 1
        ");

        $stmt->execute(['user_id' => $userId, 'email' => $email, 'id' => $contactId]);

        return (bool) $stmt->fetchColumn();
    }
}
