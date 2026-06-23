<?php

declare(strict_types=1);

namespace TempliMail\Controllers;

use TempliMail\Services\AiService;
use Exception;

class AiController
{
    public function suggestSubjects(): void
    {
        try {
            $data  = json_decode(file_get_contents('php://input'), true);
            $topic = trim($data['topic'] ?? '');

            if ($topic === '') {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error'   => 'Topic is required',
                ]);
                return;
                
            }

            if (mb_strlen($topic) > 300) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error'   => 'Topic is too long (max 300 characters)',
                ]);
                return;
            }

            $subjects = AiService::suggestSubjects($topic);

            http_response_code(200);
            echo json_encode([
                'success'  => true,
                'subjects' => $subjects,
            ]);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error'   => $e->getMessage(),
            ]);
        }
    }
}
