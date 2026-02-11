<?php

namespace TempliMail\Services;

use TempliMail\Models\ContactModel;
use Exception;

class ContactService
{
    public static function getAll(): array
    {
        return ContactModel::getAll();
    }

    public static function create(array $data): void
    {
        if (!isset($data['nombre'], $data['email'])) {
            throw new Exception('Faltan campos obligatorios');
        }

        ContactModel::create($data);
    }

    public static function update(int $id, array $data): void
    {
        ContactModel::update($id, $data);
    }

    public static function delete(int $id): void
    {
        ContactModel::delete($id);
    }
}
