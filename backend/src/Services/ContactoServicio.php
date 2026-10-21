<?php

declare(strict_types=1);

namespace TempliMail\Services;

use TempliMail\Exceptions\ApiExcepcion;
use TempliMail\Models\ContactoModelo;
use TempliMail\Models\GrupoModelo;

class ContactoServicio
{
    public static function getAll(int $userId): array
    {
        return ContactoModelo::getAllByUser($userId);
    }

    public static function create(int $userId, array $data): int
    {
        return ContactoModelo::create($userId, $data);
    }

    public static function update(int $userId, int $id, array $data): void
    {
        ContactoModelo::update($userId, $id, $data);
    }

    public static function delete(int $userId, int $id): void
    {
        ContactoModelo::delete($userId, $id);
    }

    public static function setSubscribed(int $userId, int $id, bool $subscribed): void
    {
        ContactoModelo::setSubscribed($userId, $id, $subscribed);
    }
    public static function setGroups(int $userId, int $id, array $data): void
    {
        $groupIds = $data['ids_grupo'] ?? [];

        if (!is_array($groupIds)) {
            throw new ApiExcepcion('ids_grupo debe ser una lista');
        }

        GrupoModelo::setContactGroups($userId, $id, $groupIds);
    }

    /** @return array{created:int,duplicates:int,invalid:array} */
    public static function import(int $userId, array $data): array
    {
        $rows = $data['contactos'] ?? null;

        if (!is_array($rows) || $rows === []) {
            throw new ApiExcepcion('No hay contactos que importar');
        }

        if (count($rows) > 2000) {
            throw new ApiExcepcion('Máximo 2000 contactos por importación');
        }

        $groupId = !empty($data['grupo_id']) ? (int) $data['grupo_id'] : null;

        return ContactoModelo::importMany($userId, array_filter($rows, 'is_array'), $groupId);
    }
}
