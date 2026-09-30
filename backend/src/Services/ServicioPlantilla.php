<?php

declare(strict_types=1);

namespace TempliMail\Services;

use TempliMail\Exceptions\ExcepcionApi;
use TempliMail\Models\ModeloPlantilla;

class ServicioPlantilla
{
    public static function getAll(int $userId): array
    {
        return ModeloPlantilla::getAllByUser($userId);
    }

    public static function create(int $userId, array $data): void
    {
        ModeloPlantilla::create($userId, self::validated($data));
    }

    public static function update(int $userId, int $id, array $data): void
    {
        ModeloPlantilla::update($userId, $id, self::validated($data));
    }

    public static function delete(int $userId, int $id): void
    {
        ModeloPlantilla::softDelete($userId, $id);
    }

    public static function getById(int $userId, int $id): ?array
    {
        return ModeloPlantilla::getById($userId, $id);
    }

    private static function validated(array $data): array
    {
        $nombre    = trim((string) ($data['nombre'] ?? ''));
        $asunto = trim((string) ($data['asunto'] ?? ''));
        $content = (string) ($data['contenido_html'] ?? '');

        if ($nombre === '' || $asunto === '' || trim(strip_tags($content)) === '') {
            throw new ExcepcionApi('Nombre, asunto y contenido son obligatorios');
        }

        return [
            'nombre'         => mb_substr($nombre, 0, 255),
            'asunto'      => mb_substr($asunto, 0, 255),
            'contenido_html' => $content,
        ];
    }
}
