<?php

namespace TempliMail\Services;

use TempliMail\Models\TemplateModel;
use Exception;

class TemplateService
{
    public static function getAll(int $userId): array
    {
        return TemplateModel::getAllByUser($userId);
    }

    public static function create(int $userId, array $data): void
    {
        if (!isset($data['name'], $data['subject'], $data['content_html'])) {
            throw new Exception('Missing required fields');
        }

        TemplateModel::create($userId, $data);
    }

    public static function update(int $userId, int $id, array $data): void
    {
        if (!isset($data['name'], $data['subject'], $data['content_html'])) {
            throw new Exception('Missing required fields');
        }

        TemplateModel::update($userId, $id, $data);
    }

    public static function delete(int $userId, int $id): void
    {
        TemplateModel::softDelete($userId, $id);
    }

    public static function getById(int $userId, int $id): ?array
    {
        return TemplateModel::getById($userId, $id);
    }
}