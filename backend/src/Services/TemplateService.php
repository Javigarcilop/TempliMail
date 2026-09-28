<?php

declare(strict_types=1);

namespace TempliMail\Services;

use TempliMail\Exceptions\ApiException;
use TempliMail\Models\TemplateModel;

class TemplateService
{
    public static function getAll(int $userId): array
    {
        return TemplateModel::getAllByUser($userId);
    }

    public static function create(int $userId, array $data): void
    {
        TemplateModel::create($userId, self::validated($data));
    }

    public static function update(int $userId, int $id, array $data): void
    {
        TemplateModel::update($userId, $id, self::validated($data));
    }

    public static function delete(int $userId, int $id): void
    {
        TemplateModel::softDelete($userId, $id);
    }

    public static function getById(int $userId, int $id): ?array
    {
        return TemplateModel::getById($userId, $id);
    }

    private static function validated(array $data): array
    {
        $name    = trim((string) ($data['name'] ?? ''));
        $subject = trim((string) ($data['subject'] ?? ''));
        $content = (string) ($data['content_html'] ?? '');

        if ($name === '' || $subject === '' || trim(strip_tags($content)) === '') {
            throw new ApiException('Nombre, asunto y contenido son obligatorios');
        }

        return [
            'name'         => mb_substr($name, 0, 255),
            'subject'      => mb_substr($subject, 0, 255),
            'content_html' => $content,
        ];
    }
}
