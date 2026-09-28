<?php

declare(strict_types=1);

namespace TempliMail\Services;

use TempliMail\Exceptions\ApiException;
use TempliMail\Models\ContactModel;
use TempliMail\Models\GroupModel;

class ContactService
{
    public static function getAll(int $userId): array
    {
        return ContactModel::getAllByUser($userId);
    }

    public static function create(int $userId, array $data): int
    {
        return ContactModel::create($userId, $data);
    }

    public static function update(int $userId, int $id, array $data): void
    {
        ContactModel::update($userId, $id, $data);
    }

    public static function delete(int $userId, int $id): void
    {
        ContactModel::delete($userId, $id);
    }

    public static function setSubscribed(int $userId, int $id, bool $subscribed): void
    {
        ContactModel::setSubscribed($userId, $id, $subscribed);
    }
    public static function setGroups(int $userId, int $id, array $data): void
    {
        $groupIds = $data['group_ids'] ?? [];

        if (!is_array($groupIds)) {
            throw new ApiException('group_ids debe ser una lista');
        }

        GroupModel::setContactGroups($userId, $id, $groupIds);
    }

    /** @return array{created:int,duplicates:int,invalid:array} */
    public static function import(int $userId, array $data): array
    {
        $rows = $data['contacts'] ?? null;

        if (!is_array($rows) || $rows === []) {
            throw new ApiException('No hay contactos que importar');
        }

        if (count($rows) > 2000) {
            throw new ApiException('Máximo 2000 contactos por importación');
        }

        $groupId = !empty($data['group_id']) ? (int) $data['group_id'] : null;

        return ContactModel::importMany($userId, array_filter($rows, 'is_array'), $groupId);
    }
}
