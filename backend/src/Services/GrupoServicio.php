<?php

declare(strict_types=1);

namespace TempliMail\Services;

use TempliMail\Models\GrupoModelo;

class GrupoServicio
{
    public static function obtenerTodos(int $userId): array
    {
        return GrupoModelo::obtenerTodosPorUsuario($userId);
    }

    public static function crear(int $userId, array $data): int
    {
        return GrupoModelo::crear($userId, (string) ($data['nombre'] ?? ''));
    }

    public static function renombrar(int $userId, int $id, array $data): void
    {
        GrupoModelo::renombrar($userId, $id, (string) ($data['nombre'] ?? ''));
    }

    public static function eliminar(int $userId, int $id): void
    {
        GrupoModelo::eliminar($userId, $id);
    }
}
