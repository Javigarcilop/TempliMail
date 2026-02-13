<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use TempliMail\Controllers\AuthController;
use TempliMail\Controllers\MailController;
use TempliMail\Controllers\ContactController;
use TempliMail\Controllers\TemplateController;
use TempliMail\Controllers\UploadTemplateController;

// =======================
// CORS
// =======================
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {

    // =======================
    // Routing
    // =======================
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $uri = str_replace(
        ['/TempliMail/backend/api/index.php', '/TempliMail/backend/api'],
        '',
        $uri
    );

    $request = rtrim($uri, '/');
    $method  = $_SERVER['REQUEST_METHOD'];

    // =======================
    // AUTH
    // =======================
    if ($request === '/login' && $method === 'POST') {
        (new AuthController())->login();
        exit;
    }

    if ($request === '/register' && $method === 'POST') {
        (new AuthController())->register();
        exit;
    }
    
    // =======================
    // MAIL
    // =======================
    if ($request === '/send-mail' && $method === 'POST') {
        (new MailController())->send();
        exit;
    }

    if ($request === '/templates/mass-send' && $method === 'POST') {
        (new MailController())->sendMassive();
        exit;
    }

    if ($request === '/mail/ejecutar-programados' && $method === 'GET') {
        (new MailController())->ejecutarProgramados();
        exit;
    }

    if ($request === '/history' && $method === 'GET') {
        (new MailController())->getHistorial();
        exit;
    }

    // =======================
    // CONTACTS
    // =======================
    if ($request === '/contacts') {
        $contactController = new ContactController();

        if ($method === 'GET') {
            $contactController->getAll();
            exit;
        }

        if ($method === 'POST') {
            $contactController->create();
            exit;
        }
    }

    if (preg_match('#^/contacts/(\d+)$#', $request, $matches)) {
        $contactController = new ContactController();
        $id = (int) $matches[1];

        if ($method === 'PUT') {
            $contactController->update($id);
            exit;
        }

        if ($method === 'DELETE') {
            $contactController->delete($id);
            exit;
        }
    }

    // =======================
    // TEMPLATES
    // =======================
    if ($request === '/templates') {
        $controller = new TemplateController();

        if ($method === 'GET') {
            $controller->getAll();
            exit;
        }

        if ($method === 'POST') {
            $controller->create();
            exit;
        }
    }

    if (preg_match('#^/templates/(\d+)$#', $request, $matches)) {
        $controller = new TemplateController();
        $id = (int) $matches[1];

        if ($method === 'PUT') {
            $controller->update($id);
            exit;
        }

        if ($method === 'DELETE') {
            $controller->delete($id);
            exit;
        }
    }

    // =======================
    // UPLOAD TEMPLATE FILE
    // =======================
    if ($request === '/upload-template-file' && $method === 'POST') {
        (new UploadTemplateController())->handleUpload();
        exit;
    }

    // =======================
    // 404
    // =======================
    http_response_code(404);
    echo json_encode(['error' => 'Endpoint not found']);

} catch (Throwable $e) {

    http_response_code(500);
    echo json_encode([
        'error' => 'Internal error',
        'detail' => $e->getMessage() // quitar en producción
    ]);
}
