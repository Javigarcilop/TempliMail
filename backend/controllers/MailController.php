<?php
require_once __DIR__ . '/../utils/Mailer.php';

class MailController
{
    private $pdo;

    public function __construct($pdo = null)
    {
        $this->pdo = $pdo;
    }

    public function send()
    {
        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['to'], $data['subject'], $data['body'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Faltan datos obligatorios']);
            return;
        }

        $to = $data['to'];
        $subject = $data['subject'];
        $body = $data['body'];

        $result = Mailer::send($to, $subject, $body);

        if ($result === true) {
            echo json_encode(['success' => true]);
        } else {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error'   => $result
            ]);
        }

    }

    public function sendMassive()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $contactoIds = $data['contactos'] ?? [];
        $plantillaId = $data['plantilla_id'] ?? null;
        $fechaProgramada = $data['fecha_programada'] ?? null;

        if (empty($contactoIds) || !$plantillaId) {
            http_response_code(400);
            echo json_encode(['error' => 'Datos incompletos']);
            return;
        }

        $stmt = $this->pdo->prepare("SELECT * FROM plantillas WHERE id = ?");
        $stmt->execute([$plantillaId]);
        $plantilla = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$plantilla) {
            http_response_code(404);
            echo json_encode(['error' => 'Plantilla no encontrada']);
            return;
        }

        $estado = $fechaProgramada ? 'pendiente' : 'enviado';
        $fechaEnvio = $fechaProgramada ? null : date('Y-m-d H:i:s');

        $stmt = $this->pdo->prepare("
            INSERT INTO envios (plantilla_id, asunto, mensaje, estado, fecha_programada, enviado_en) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $plantillaId,
            $plantilla['asunto'],
            $plantilla['contenido_html'],
            $estado,
            $fechaProgramada,
            $fechaEnvio
        ]);
        $envioId = $this->pdo->lastInsertId();

        $inClause = implode(',', array_fill(0, count($contactoIds), '?'));
        $stmt = $this->pdo->prepare("SELECT id, email, nombre FROM contactos WHERE id IN ($inClause)");
        $stmt->execute($contactoIds);
        $contactos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($contactos as $contacto) {
            $stmt = $this->pdo->prepare("INSERT INTO envios_contacto (envio_id, contacto_id) VALUES (?, ?)");
            $stmt->execute([$envioId, $contacto['id']]);

            if (!$fechaProgramada) {
                Mailer::send($contacto['email'], $plantilla['asunto'], $plantilla['contenido_html']);
            }
        }

        echo json_encode(['success' => true, 'programado' => (bool) $fechaProgramada]);
    }

    public function getHistorial()
    {
        $stmt = $this->pdo->query("
            SELECT e.id, e.asunto, e.enviado_en, e.estado, e.fecha_programada,
                   COALESCE(p.nombre, 'Sin plantilla') AS plantilla,
                   COUNT(ec.contacto_id) AS total_destinatarios
            FROM envios e
            LEFT JOIN plantillas p ON e.plantilla_id = p.id
            LEFT JOIN envios_contacto ec ON e.id = ec.envio_id
            GROUP BY e.id
            ORDER BY e.enviado_en DESC
        ");

        $historial = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $historial]);
    }

    public function ejecutarProgramados()
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM envios 
            WHERE estado = 'pendiente' 
            AND fecha_programada <= NOW()
        ");
        $stmt->execute();
        $envios = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $ahora = strtotime(date('Y-m-d H:i:00'));

        foreach ($envios as $envio) {
            if (strtotime($envio['fecha_programada']) > $ahora) {
                continue;
            }

            $envioId = $envio['id'];

            $stmt = $this->pdo->prepare("
                SELECT c.email FROM envios_contacto ec
                JOIN contactos c ON ec.contacto_id = c.id
                WHERE ec.envio_id = ?
            ");
            $stmt->execute([$envioId]);
            $destinatarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($destinatarios as $destino) {
                Mailer::send($destino['email'], $envio['asunto'], $envio['mensaje']);
            }

            $stmt = $this->pdo->prepare("
                UPDATE envios SET estado = 'enviado', enviado_en = NOW() WHERE id = ?
            ");
            $stmt->execute([$envioId]);
        }

        echo json_encode(['success' => true, 'procesados' => count($envios)]);
    }

    public function previewTest()
{
    $template = "Hola {{name}}, bienvenido a {{app}}";
    $data = [
        'name' => 'Javi',
        'app' => 'TempliMail'
    ];

    $content = $template;

    foreach ($data as $key => $value) {
        $content = str_replace('{{' . $key . '}}', $value, $content);
    }

    echo $content;
    exit;
}

}
