<?php
class ContactController
{
    private $pdo;

    public function __construct()
    {
       
    }

    public function getAll()
    {
        $stmt = $this->pdo->query("SELECT * FROM contactos ORDER BY creado_en DESC");
        $contactos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($contactos);
    }

    public function create()
    {
        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['nombre'], $data['email'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Faltan campos obligatorios']);
            return;
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO contactos (nombre, apellidos, email, telefono, empresa, cargo, etiquetas)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['nombre'],
            $data['apellidos'] ?? '',
            $data['email'],
            $data['telefono'] ?? '',
            $data['empresa'] ?? '',
            $data['cargo'] ?? '',
            $data['etiquetas'] ?? ''
        ]);

        echo json_encode(['success' => true]);
    }

    public function update($id)
    {
        $data = json_decode(file_get_contents('php://input'), true);

        $stmt = $this->pdo->prepare("
            UPDATE contactos
            SET nombre = ?, apellidos = ?, email = ?, telefono = ?, empresa = ?, cargo = ?, etiquetas = ?, actualizado_en = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $data['nombre'],
            $data['apellidos'],
            $data['email'],
            $data['telefono'],
            $data['empresa'],
            $data['cargo'],
            $data['etiquetas'],
            $id
        ]);

        echo json_encode(['success' => true]);
    }

    public function delete($id)
    {
        $stmt = $this->pdo->prepare("DELETE FROM contactos WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
    }
}
