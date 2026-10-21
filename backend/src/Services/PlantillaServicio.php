<?php

declare(strict_types=1);

namespace TempliMail\Services;

use TempliMail\Exceptions\ApiExcepcion;
use TempliMail\Models\PlantillaModelo;

class PlantillaServicio
{
    public static function obtenerTodos(int $userId): array
    {
        return PlantillaModelo::obtenerTodosPorUsuario($userId);
    }

    public static function crear(int $userId, array $data): void
    {
        PlantillaModelo::crear($userId, self::validarDatos($data));
    }

    public static function actualizar(int $userId, int $id, array $data): void
    {
        PlantillaModelo::actualizar($userId, $id, self::validarDatos($data));
    }

    public static function eliminar(int $userId, int $id): void
    {
        PlantillaModelo::eliminarLogico($userId, $id);
    }

    public static function obtenerPorId(int $userId, int $id): ?array
    {
        return PlantillaModelo::obtenerPorId($userId, $id);
    }

    private static function validarDatos(array $data): array
    {
        $nombre    = trim((string) ($data['nombre'] ?? ''));
        $asunto = trim((string) ($data['asunto'] ?? ''));
        $content = (string) ($data['contenido_html'] ?? '');

        if ($nombre === '' || $asunto === '' || trim(strip_tags($content)) === '') {
            throw new ApiExcepcion('Nombre, asunto y contenido son obligatorios');
        }

        return [
            'nombre'         => mb_substr($nombre, 0, 255),
            'asunto'      => mb_substr($asunto, 0, 255),
            'contenido_html' => $content,
        ];
    }
}
