<?php

declare(strict_types=1);

namespace TempliMail\Services;

use TempliMail\Models\GroupModel;

class GroupService
{
    public static function getAll(int $userId): array
    {
        return GroupModel::getAllByUser($userId);
    }

    public static function create(int $userId, array $data): int
    {
        return GroupModel::create($userId, (string) ($data['name'] ?? ''));
    }

    public static function rename(int $userId, int $id, array $data): void
    {
        GroupModel::rename($userId, $id, (string) ($data['name'] ?? ''));
    }

    public static function delete(int $userId, int $id): void
    {
        GroupModel::delete($userId, $id);
    }
}
