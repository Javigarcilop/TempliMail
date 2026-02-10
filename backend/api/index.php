<?php
// =======================
// CORS
// =======================
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Content-Type: application/json");

require_once __DIR__ . '/../controllers/UserController.php';
require_once __DIR__ . '/../controllers/MailController.php';
require_once __DIR__ . '/../controllers/ContactController.php';
require_once __DIR__ . '/../controllers/TemplateController.php';
require_once __DIR__ . '/../controllers/UploadTemplateController.php';


if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

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

file_put_contents(
    __DIR__ . '/../request.log',
    '[' . date('Y-m-d H:i:s') . '] ' . $method . ' ' . $request . PHP_EOL,
    FILE_APPEND
);

// =======================
// AUTH
// =======================
if ($request === '/login' && $method === 'POST') {
    $contact = new UserController();
    $contact->login();
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
echo json_encode(['error' => 'Endpoint not found']);
