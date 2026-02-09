<?php

require_once __DIR__ . '/../models/TemplateModel.php';

class TemplateService
{
    public static function getAll(): array
    {
        return TemplateModel::getAll();
    }

    public static function create(array $data): void
    {
        if (!isset($data['nombre'], $data['asunto'], $data['contenido_html'])) {
            throw new Exception('Faltan campos');
        }

        TemplateModel::create($data);
    }

    public static function update(int $id, array $data): void
    {
        if (!isset($data['nombre'], $data['asunto'], $data['contenido_html'])) {
            throw new Exception('Faltan campos');
        }

        TemplateModel::update($id, $data);
    }

    public static function delete(int $id): void
    {
        TemplateModel::delete($id);
    }
}
