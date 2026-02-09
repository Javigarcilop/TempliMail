<?php

require_once __DIR__ . '/../models/ContactModel.php';

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
