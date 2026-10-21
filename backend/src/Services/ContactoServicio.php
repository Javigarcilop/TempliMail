<?php

declare(strict_types=1);

namespace TempliMail\Services;

use TempliMail\Exceptions\ApiExcepcion;
use TempliMail\Models\ContactoModelo;
use TempliMail\Models\GrupoModelo;

class ContactoServicio
{
    public static function obtenerTodos(int $userId): array
    {
        return ContactoModelo::obtenerTodosPorUsuario($userId);
    }

    public static function crear(int $userId, array $data): int
    {
        return ContactoModelo::crear($userId, $data);
    }

    public static function actualizar(int $userId, int $id, array $data): void
    {
        ContactoModelo::actualizar($userId, $id, $data);
    }

    public static function eliminar(int $userId, int $id): void
    {
        ContactoModelo::eliminar($userId, $id);
    }

    public static function establecerSuscripcion(int $userId, int $id, bool $subscribed): void
    {
        ContactoModelo::establecerSuscripcion($userId, $id, $subscribed);
    }
    public static function establecerGrupos(int $userId, int $id, array $data): void
    {
        $groupIds = $data['ids_grupo'] ?? [];

        if (!is_array($groupIds)) {
            throw new ApiExcepcion('ids_grupo debe ser una lista');
        }

        GrupoModelo::establecerGruposDeContacto($userId, $id, $groupIds);
    }

    /** @return array{created:int,duplicates:int,invalid:array} */
    public static function importar(int $userId, array $data): array
    {
        $rows = $data['contactos'] ?? null;

        if (!is_array($rows) || $rows === []) {
            throw new ApiExcepcion('No hay contactos que importar');
        }

        if (count($rows) > 2000) {
            throw new ApiExcepcion('Máximo 2000 contactos por importación');
        }

        $groupId = !empty($data['grupo_id']) ? (int) $data['grupo_id'] : null;

        return ContactoModelo::importarVarios($userId, array_filter($rows, 'is_array'), $groupId);
    }
}
