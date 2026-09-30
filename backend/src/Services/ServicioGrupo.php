<?php

declare(strict_types=1);

namespace TempliMail\Services;

use TempliMail\Models\ModeloGrupo;

class ServicioGrupo
{
    public static function getAll(int $userId): array
    {
        return ModeloGrupo::getAllByUser($userId);
    }

    public static function create(int $userId, array $data): int
    {
        return ModeloGrupo::create($userId, (string) ($data['nombre'] ?? ''));
    }

    public static function rename(int $userId, int $id, array $data): void
    {
        ModeloGrupo::rename($userId, $id, (string) ($data['nombre'] ?? ''));
    }

    public static function delete(int $userId, int $id): void
    {
        ModeloGrupo::delete($userId, $id);
    }
}
