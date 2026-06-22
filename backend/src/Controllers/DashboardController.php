<?php

declare(strict_types=1);

namespace TempliMail\Controllers;

use TempliMail\Models\DashboardModel;
use Exception;

class DashboardController
{
    public function stats(): void
    {
        try {
            $userId = (int) $_SERVER['AUTH_USER_ID'];

            $data = DashboardModel::getStats($userId);

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'data'    => $data,
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error'   => 'Internal server error',
            ]);
        }
    }

    public function summary(): void
    {
        try {
            $userId = (int) $_SERVER['AUTH_USER_ID'];

            $data = [
                'top_template' => DashboardModel::getTopTemplate($userId),
                'top_contact'  => DashboardModel::getTopContact($userId),
            ];

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'data'    => $data,
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error'   => 'Internal server error',
            ]);
        }
    }
}
