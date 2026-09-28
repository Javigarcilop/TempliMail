<?php

declare(strict_types=1);

namespace TempliMail\Services;

use TempliMail\Models\ContactModel;

class ContactService
{
    public static function getAll(int $userId): array
    {
        return ContactModel::getAllByUser($userId);
    }

    public static function create(int $userId, array $data): void
    {
        ContactModel::create($userId, $data);
    }

    public static function update(int $userId, int $id, array $data): void
    {
        ContactModel::update($userId, $id, $data);
    }

    public static function delete(int $userId, int $id): void
    {
        ContactModel::delete($userId, $id);
    }

    public static function setSubscribed(int $userId, int $id, bool $subscribed): void
    {
        ContactModel::setSubscribed($userId, $id, $subscribed);
    }
}
