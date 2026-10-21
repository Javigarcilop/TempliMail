<?php

declare(strict_types=1);

namespace TempliMail\Services;

use TempliMail\Models\GrupoModelo;

class GrupoServicio
{
    public static function getAll(int $userId): array
    {
        return GrupoModelo::getAllByUser($userId);
    }

    public static function create(int $userId, array $data): int
    {
        return GrupoModelo::create($userId, (string) ($data['nombre'] ?? ''));
    }

    public static function rename(int $userId, int $id, array $data): void
    {
        GrupoModelo::rename($userId, $id, (string) ($data['nombre'] ?? ''));
    }

    public static function delete(int $userId, int $id): void
    {
        GrupoModelo::delete($userId, $id);
    }
}
