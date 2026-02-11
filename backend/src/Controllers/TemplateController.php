<?php

namespace TempliMail\Controllers;

use TempliMail\Utils\DB;
use PDO;
use PDOException;

class TemplateController
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = DB::get();
    }

    public function getAll(): void
    {
        $stmt = $this->pdo->query(
            "SELECT * FROM plantillas ORDER BY creado_en DESC"
        );

        $plantillas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode($plantillas);
    }

    public function create(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['nombre'], $data['asunto'], $data['contenido_html'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Faltan campos']);
            return;
        }

        $stmt = $this->pdo->prepare(
            "INSERT INTO plantillas (nombre, asunto, contenido_html)
             VALUES (?, ?, ?)"
        );

        $stmt->execute([
            $data['nombre'],
            $data['asunto'],
            $data['contenido_html']
        ]);

        echo json_encode(['success' => true]);
    }

    public function update(int $id): void
    {
        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['nombre'], $data['asunto'], $data['contenido_html'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Faltan campos']);
            return;
        }

        $stmt = $this->pdo->prepare(
            "UPDATE plantillas
             SET nombre = ?, asunto = ?, contenido_html = ?
             WHERE id = ?"
        );

        $stmt->execute([
            $data['nombre'],
            $data['asunto'],
            $data['contenido_html'],
            $id
        ]);

        echo json_encode(['success' => true]);
    }

    public function delete(int $id): void
    {
        try {
            $id = (int) $id;

            $update = $this->pdo->prepare(
                "UPDATE envios SET plantilla_id = NULL WHERE plantilla_id = ?"
            );
            $update->execute([$id]);

            $stmt = $this->pdo->prepare(
                "DELETE FROM plantillas WHERE id = ?"
            );
            $stmt->execute([$id]);

            echo json_encode(['success' => true]);

        } catch (PDOException $e) {

            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Error al eliminar plantilla'
            ]);
        }
    }
}
