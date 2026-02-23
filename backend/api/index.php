<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use TempliMail\Controllers\AuthController;
use TempliMail\Controllers\MailController;
use TempliMail\Controllers\ContactController;
use TempliMail\Controllers\TemplateController;
use TempliMail\Controllers\UploadTemplateController;
use TempliMail\Auth\JwtService;
use TempliMail\Middleware\AuthMiddleware;

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
    // Inicializar JWT + Middleware
    // =======================
    $jwtSecret = $_ENV['JWT_SECRET'] ?? 'dev_secret_change_this';
    $jwtService = new JwtService($jwtSecret);
    $authMiddleware = new AuthMiddleware($jwtService);

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
    // Rutas públicas
    // =======================
    $publicRoutes = [
        '/login',
        '/register'
    ];

    if (!in_array($request, $publicRoutes)) {
        $authMiddleware->handle();
    }

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
    // EMAIL
    // =======================
    if ($request === '/send-mail' && $method === 'POST') {
        (new MailController())->sendSingle();
        exit;
    }

    if ($request === '/send-massive' && $method === 'POST') {
        (new MailController())->sendMassive();
        exit;
    }

    if ($request === '/process-scheduled' && $method === 'GET') {
        (new MailController())->processScheduled();
        exit;
    }

    if ($request === '/history' && $method === 'GET') {
        (new MailController())->getHistory();
        exit;
    }

    // =======================
    // CONTACTS
    // =======================
    if ($request === '/contacts') {

        $controller = new ContactController();

        if ($method === 'GET') {
            $controller->getAll();
            exit;
        }

        if ($method === 'POST') {
            $controller->create();
            exit;
        }
    }

    if (preg_match('#^/contacts/(\d+)$#', $request, $matches)) {

        $controller = new ContactController();
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
    echo json_encode([
        'success' => false,
        'error'   => 'Endpoint not found'
    ]);

} catch (Throwable $e) {

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Internal server error'
    ]);
}