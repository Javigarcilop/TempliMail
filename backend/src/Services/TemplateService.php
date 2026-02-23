<?php

namespace TempliMail\Services;

use TempliMail\Models\TemplateModel;
use Exception;

class TemplateService
{
    
    private static function getUserId(): int
    {
        return 1; 
    }

    public static function getAll(): array
    {
        return TemplateModel::getAllByUser(self::getUserId());
    }

    public static function create(array $data): void
    {
        if (!isset($data['name'], $data['subject'], $data['content_html'])) {
            throw new Exception('Missing required fields');
        }

        TemplateModel::create(self::getUserId(), $data);
    }

    public static function update(int $id, array $data): void
    {
        if (!isset($data['name'], $data['subject'], $data['content_html'])) {
            throw new Exception('Missing required fields');
        }

        TemplateModel::update(self::getUserId(), $id, $data);
    }

    public static function delete(int $id): void
    {
        TemplateModel::softDelete(self::getUserId(), $id);
    }

    public static function getById(int $id): ?array
    {
        return TemplateModel::getById(self::getUserId(), $id);
    }
}
