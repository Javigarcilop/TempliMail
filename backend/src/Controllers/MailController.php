<?php

namespace TempliMail\Controllers;

use TempliMail\Services\MailService;
use Exception;

class MailController
{
    public function send(): void
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            MailService::sendSingle($data);

            echo json_encode(['success' => true]);

        } catch (Exception $e) {

            http_response_code(400);
            echo json_encode([
                'error' => $e->getMessage()
            ]);
        }
    }

    public function sendMassive(): void
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            $result = MailService::sendMassive($data);

            echo json_encode([
                'success'    => true,
                'programado' => $result['programado']
            ]);

        } catch (Exception $e) {

            http_response_code(400);
            echo json_encode([
                'error' => $e->getMessage()
            ]);
        }
    }

    public function getHistorial(): void
    {
        echo json_encode([
            'success' => true,
            'data'    => MailService::historial()
        ]);
    }

    public function ejecutarProgramados(): void
    {
        $procesados = MailService::ejecutarProgramados();

        echo json_encode([
            'success'    => true,
            'procesados' => $procesados
        ]);
    }

    public function previewTest(): void
    {
        $template = "Hola {{name}}, bienvenido a {{app}}";

        $data = [
            'name' => 'Javi',
            'app'  => 'TempliMail'
        ];

        foreach ($data as $key => $value) {
            $template = str_replace('{{' . $key . '}}', $value, $template);
        }

        echo $template;
    }
}
