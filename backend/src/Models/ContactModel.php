<?php

namespace TempliMail\Models;
use TempliMail\Utils\DB;
use PDO;

class ContactModel
{
    public static function getAll(): array
    {
        $db = DB::get();

        $stmt = $db->query(
            "SELECT * FROM contactos ORDER BY creado_en DESC"
        );

        return $stmt->fetchAll();
    }

    public static function create(array $data): void
    {
        $db = DB::get();

        $stmt = $db->prepare("
            INSERT INTO contactos 
            (nombre, apellidos, email, telefono, empresa, cargo, etiquetas)
            VALUES (:nombre, :apellidos, :email, :telefono, :empresa, :cargo, :etiquetas)
        ");

        $stmt->execute([
            'nombre'    => $data['nombre'],
            'apellidos' => $data['apellidos'] ?? '',
            'email'     => $data['email'],
            'telefono'  => $data['telefono'] ?? '',
            'empresa'   => $data['empresa'] ?? '',
            'cargo'     => $data['cargo'] ?? '',
            'etiquetas' => $data['etiquetas'] ?? ''
        ]);
    }

    public static function update(int $id, array $data): void
    {
        $db = DB::get();

        $stmt = $db->prepare("
            UPDATE contactos
            SET nombre = :nombre,
                apellidos = :apellidos,
                email = :email,
                telefono = :telefono,
                empresa = :empresa,
                cargo = :cargo,
                etiquetas = :etiquetas,
                actualizado_en = NOW()
            WHERE id = :id
        ");

        $stmt->execute([
            'id'        => $id,
            'nombre'    => $data['nombre'],
            'apellidos' => $data['apellidos'],
            'email'     => $data['email'],
            'telefono'  => $data['telefono'],
            'empresa'   => $data['empresa'],
            'cargo'     => $data['cargo'],
            'etiquetas' => $data['etiquetas']
        ]);
    }

    public static function delete(int $id): void
    {
        $db = DB::get();

        $stmt = $db->prepare("DELETE FROM contactos WHERE id = :id");
        $stmt->execute(['id' => $id]);
    }
}
