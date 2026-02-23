<?php

namespace TempliMail\Services;

use TempliMail\Models\ContactModel;
use Exception;

class ContactService
{
    public static function getAll(int $userId): array
    {
        return ContactModel::getAllByUser($userId);
    }

    public static function create(int $userId, array $data): void
    {
        if (empty($data['email'])) {
            throw new Exception('El email es obligatorio');
        }

        ContactModel::create($userId, $data);
    }

    public static function update(int $userId, int $id, array $data): void
    {
        ContactModel::update($userId, $id, $data);
    }

    public static function delete(int $userId, int $id): void
{
    ContactModel::softDelete($userId, $id);
}
}