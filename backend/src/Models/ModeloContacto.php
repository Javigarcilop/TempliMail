<?php

declare(strict_types=1);

namespace TempliMail\Models;

use TempliMail\Exceptions\ExcepcionApi;
use TempliMail\Utils\BD;
use PDO;

class ModeloContacto
{
    public static function getAllByUser(int $userId): array
    {
        $stmt = BD::get()->prepare("
            SELECT id, nombre, apellidos, correo, telefono, empresa, cargo,
                   DATE_FORMAT(baja_en, '%Y-%m-%dT%H:%i:%sZ') AS baja_en,
                   DATE_FORMAT(creado_en,      '%Y-%m-%dT%H:%i:%sZ') AS creado_en,
                   DATE_FORMAT(actualizado_en,      '%Y-%m-%dT%H:%i:%sZ') AS actualizado_en
            FROM contactos
            WHERE usuario_id = :usuario_id
              AND eliminado_en IS NULL
            ORDER BY creado_en DESC, id DESC
        ");

        $stmt->execute(['usuario_id' => $userId]);

        $contactos    = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $memberships = ModeloGrupo::membershipsByContact($userId);

        foreach ($contactos as &$contact) {
            $contact['ids_grupo'] = $memberships[(int) $contact['id']] ?? [];
        }

        return $contactos;
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

        $stmt = BD::get()->prepare("
            SELECT id, correo
            FROM contactos
            WHERE usuario_id = ?
              AND eliminado_en IS NULL
              AND baja_en IS NULL
              AND id IN ($placeholders)
        ");

        $stmt->execute([$userId, ...$ids]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getById(int $userId, int $id): ?array
    {
        $stmt = BD::get()->prepare("
            SELECT id, nombre, apellidos, correo, empresa, cargo
            FROM contactos
            WHERE id = :id AND usuario_id = :usuario_id AND eliminado_en IS NULL
            LIMIT 1
        ");

        $stmt->execute(['id' => $id, 'usuario_id' => $userId]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function create(int $userId, array $data): int
    {
        $correo = self::validEmail($data['correo'] ?? '');

        if (self::emailExists($userId, $correo)) {
            throw ExcepcionApi::conflict('Ya existe un contacto con ese correo');
        }

        $db   = BD::get();
        $stmt = $db->prepare("
            INSERT INTO contactos
            (usuario_id, nombre, apellidos, correo, telefono, empresa, cargo)
            VALUES (:usuario_id, :nombre, :apellidos, :correo, :telefono, :empresa, :cargo)
        ");

        $stmt->execute([
            'usuario_id'    => $userId,
            'nombre' => self::nullable($data['nombre'] ?? null),
            'apellidos'  => self::nullable($data['apellidos'] ?? null),
            'correo'      => $correo,
            'telefono'      => self::nullable($data['telefono'] ?? null),
            'empresa'    => self::nullable($data['empresa'] ?? null),
            'cargo'   => self::nullable($data['cargo'] ?? null),
        ]);

        return (int) $db->lastInsertId();
    }

    public static function update(int $userId, int $id, array $data): void
    {
        $correo = self::validEmail($data['correo'] ?? '');

        if (self::emailExistsForAnotherContact($userId, $correo, $id)) {
            throw ExcepcionApi::conflict('Ya existe otro contacto con ese correo');
        }

        $stmt = BD::get()->prepare("
            UPDATE contactos
            SET nombre = :nombre,
                apellidos  = :apellidos,
                correo      = :correo,
                telefono      = :telefono,
                empresa    = :empresa,
                cargo   = :cargo
            WHERE id = :id
              AND usuario_id = :usuario_id
              AND eliminado_en IS NULL
        ");

        $stmt->execute([
            'id'         => $id,
            'usuario_id'    => $userId,
            'nombre' => self::nullable($data['nombre'] ?? null),
            'apellidos'  => self::nullable($data['apellidos'] ?? null),
            'correo'      => $correo,
            'telefono'      => self::nullable($data['telefono'] ?? null),
            'empresa'    => self::nullable($data['empresa'] ?? null),
            'cargo'   => self::nullable($data['cargo'] ?? null),
        ]);

        // rowCount() vale 0 si no cambio nada: se comprueba la existencia aparte
        if ($stmt->rowCount() === 0 && self::getById($userId, $id) === null) {
            throw ExcepcionApi::notFound('Contacto no encontrado');
        }
    }

    public static function delete(int $userId, int $id): void
    {
        $stmt = BD::get()->prepare("
            DELETE FROM contactos
            WHERE id = :id AND usuario_id = :usuario_id
        ");

        $stmt->execute(['id' => $id, 'usuario_id' => $userId]);

        if ($stmt->rowCount() === 0) {
            throw ExcepcionApi::notFound('Contacto no encontrado');
        }
    }

    /**
     * Importacion masiva (CSV ya parseado en el navegador).
     * Se omiten emails repetidos (en el fichero o ya existentes) y se informan los invalidos.
     *
     * @param  array<int,array<string,mixed>> $rows
     * @return array{created:int,duplicates:int,invalid:array<int,array{row:int,correo:string,reason:string}>}
     */
    public static function importMany(int $userId, array $rows, ?int $groupId = null): array
    {
        if ($groupId !== null && !ModeloGrupo::owns($userId, $groupId)) {
            throw ExcepcionApi::notFound('Grupo no encontrado');
        }

        $db = BD::get();

        $stmt = $db->prepare("SELECT LOWER(correo) FROM contactos WHERE usuario_id = :usuario_id AND eliminado_en IS NULL");
        $stmt->execute(['usuario_id' => $userId]);
        $known = array_flip($stmt->fetchAll(PDO::FETCH_COLUMN));

        $insert = $db->prepare("
            INSERT INTO contactos (usuario_id, nombre, apellidos, correo, telefono, empresa, cargo)
            VALUES (:usuario_id, :nombre, :apellidos, :correo, :telefono, :empresa, :cargo)
        ");

        $created    = 0;
        $duplicates = 0;
        $invalid    = [];
        $newIds     = [];

        $db->beginTransaction();

        try {
            foreach ($rows as $index => $row) {
                $correo = trim((string) ($row['correo'] ?? ''));

                if ($correo === '' || !filter_var($correo, FILTER_VALIDATE_EMAIL) || mb_strlen($correo) > 255) {
                    if (count($invalid) < 50) {
                        $invalid[] = [
                            'fila'    => $index + 1,
                            'correo'  => mb_substr($correo, 0, 80),
                            'motivo' => $correo === '' ? 'Correo vacío' : 'Correo no válido',
                        ];
                    }
                    continue;
                }

                if (isset($known[strtolower($correo)])) {
                    $duplicates++;
                    continue;
                }

                $insert->execute([
                    'usuario_id'    => $userId,
                    'nombre' => self::nullable(isset($row['nombre']) ? mb_substr((string) $row['nombre'], 0, 100) : null),
                    'apellidos'  => self::nullable(isset($row['apellidos']) ? mb_substr((string) $row['apellidos'], 0, 100) : null),
                    'correo'      => $correo,
                    'telefono'      => self::nullable(isset($row['telefono']) ? mb_substr((string) $row['telefono'], 0, 50) : null),
                    'empresa'    => self::nullable(isset($row['empresa']) ? mb_substr((string) $row['empresa'], 0, 150) : null),
                    'cargo'   => self::nullable(isset($row['cargo']) ? mb_substr((string) $row['cargo'], 0, 150) : null),
                ]);

                $known[strtolower($correo)] = true;
                $newIds[] = (int) $db->lastInsertId();
                $created++;
            }

            if ($groupId !== null) {
                ModeloGrupo::addContacts($groupId, $newIds);
            }

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        return ['creados' => $created, 'duplicados' => $duplicates, 'invalidos' => $invalid];
    }

    /** Baja / alta manual desde el panel. */
    public static function setSubscribed(int $userId, int $id, bool $subscribed): void
    {
        $stmt = BD::get()->prepare("
            UPDATE contactos
            SET baja_en = " . ($subscribed ? 'NULL' : 'UTC_TIMESTAMP()') . "
            WHERE id = :id AND usuario_id = :usuario_id AND eliminado_en IS NULL
        ");

        $stmt->execute(['id' => $id, 'usuario_id' => $userId]);

        if ($stmt->rowCount() === 0 && self::getById($userId, $id) === null) {
            throw ExcepcionApi::notFound('Contacto no encontrado');
        }
    }

    /** Baja pedida por el propio contacto desde el enlace del correo. */
    public static function unsubscribe(int $id): ?string
    {
        $db = BD::get();

        $stmt = $db->prepare("SELECT correo FROM contactos WHERE id = :id AND eliminado_en IS NULL");
        $stmt->execute(['id' => $id]);
        $correo = $stmt->fetchColumn();

        if ($correo === false) {
            return null;
        }

        $db->prepare("
            UPDATE contactos
            SET baja_en = COALESCE(baja_en, UTC_TIMESTAMP())
            WHERE id = :id
        ")->execute(['id' => $id]);

        return (string) $correo;
    }

    // -----------------------------------------------------------------

    private static function validEmail(mixed $value): string
    {
        $correo = trim((string) $value);

        if ($correo === '') {
            throw new ExcepcionApi('El correo es obligatorio');
        }

        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            throw new ExcepcionApi('El formato del correo no es valido');
        }

        return $correo;
    }

    private static function nullable(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    private static function emailExists(int $userId, string $correo): bool
    {
        $stmt = BD::get()->prepare("
            SELECT 1 FROM contactos
            WHERE usuario_id = :usuario_id AND correo = :correo AND eliminado_en IS NULL
            LIMIT 1
        ");

        $stmt->execute(['usuario_id' => $userId, 'correo' => $correo]);

        return (bool) $stmt->fetchColumn();
    }

    private static function emailExistsForAnotherContact(int $userId, string $correo, int $contactId): bool
    {
        $stmt = BD::get()->prepare("
            SELECT 1 FROM contactos
            WHERE usuario_id = :usuario_id AND correo = :correo AND id != :id AND eliminado_en IS NULL
            LIMIT 1
        ");

        $stmt->execute(['usuario_id' => $userId, 'correo' => $correo, 'id' => $contactId]);

        return (bool) $stmt->fetchColumn();
    }
}
