<?php

namespace TempliMail\Controllers;

use TempliMail\Services\MailService;
use TempliMail\Models\EmailCampaignModel;
use Exception;

class MailController
{

    public function sendSingle(): void
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            MailService::sendSingle($data);

            http_response_code(200);
            echo json_encode([
                'success' => true
            ]);

        } catch (Exception $e) {

            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Create campaign and optionally send immediately
     */
    public function sendMassive(): void
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            // 🔐 TEMPORAL (hasta implementar JWT)
            $userId = 1;

            $result = MailService::sendMassive($userId, $data);

            http_response_code(201);
            echo json_encode([
                'success'   => true,
                'scheduled' => $result['scheduled']
            ]);

        } catch (Exception $e) {

            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error'   => $e->getMessage()
            ]);
        }
    }

    /**
     * Get campaign history
     */
    public function getHistory(): void
    {
        try {
            $campaigns = EmailCampaignModel::getScheduled(); // puedes mejorar esto luego

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'data'    => $campaigns
            ]);

        } catch (Exception $e) {

            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error'   => $e->getMessage()
            ]);
        }
    }

    /**
     * Manually trigger scheduled campaigns
     * (esto será sustituido por worker CLI en el futuro)
     */
    public function processScheduled(): void
    {
        try {
            $campaigns = EmailCampaignModel::getScheduled();

            $processed = 0;

            foreach ($campaigns as $campaign) {
                MailService::processCampaign((int)$campaign['id']);
                $processed++;
            }

            http_response_code(200);
            echo json_encode([
                'success'   => true,
                'processed' => $processed
            ]);

        } catch (Exception $e) {

            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error'   => $e->getMessage()
            ]);
        }
    }

    /**
     * Simple preview rendering test
     */
    public function previewTest(): void
    {
        $template = "Hello {{name}}, welcome to {{app}}";

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
