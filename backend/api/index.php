<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use TempliMail\Controllers\AiController;
use TempliMail\Controllers\AuthController;
use TempliMail\Controllers\ContactController;
use TempliMail\Controllers\DashboardController;
use TempliMail\Controllers\MailController;
use TempliMail\Controllers\TemplateController;
use TempliMail\Controllers\UnsubscribeController;
use TempliMail\Controllers\UploadTemplateController;
use TempliMail\Middleware\AuthMiddleware;
use TempliMail\Services\JwtService;
use TempliMail\Utils\Env;

// =======================
// CORS
// =======================
// CORS_ORIGIN admite varios origenes separados por comas.
$allowedOrigins = array_map('trim', explode(',', Env::get('CORS_ORIGIN', 'http://localhost:4200')));
$requestOrigin  = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array('*', $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: *');
} elseif ($requestOrigin !== '' && in_array($requestOrigin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $requestOrigin);
    header('Vary: Origin');
}

header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

/**
 * Tabla de rutas: [metodo, patron, controlador, accion, publica?]
 * Los grupos capturados del patron (ids numericos) se pasan a la accion.
 */
$routes = [
    // Publicas
    ['POST', '#^/login$#',                              AuthController::class,           'login',              true],
    ['POST', '#^/register$#',                           AuthController::class,           'register',           true],
    ['GET',  '#^/unsubscribe/(\d+)/([a-f0-9]{64})$#',   UnsubscribeController::class,    'handle',             true],
    ['POST', '#^/unsubscribe/(\d+)/([a-f0-9]{64})$#',   UnsubscribeController::class,    'handle',             true],

    // Sesion
    ['GET',  '#^/me$#',                                 AuthController::class,           'me',                 false],
    ['POST', '#^/logout$#',                             AuthController::class,           'logout',             false],

    // IA
    ['POST', '#^/ai/suggest-subject$#',                 AiController::class,             'suggestSubjects',    false],

    // Dashboard
    ['GET',  '#^/dashboard/stats$#',                    DashboardController::class,      'stats',              false],
    ['GET',  '#^/dashboard/summary$#',                  DashboardController::class,      'summary',            false],

    // Correo
    ['POST', '#^/send-mail$#',                          MailController::class,           'sendSingle',         false],
    ['POST', '#^/send-massive$#',                       MailController::class,           'sendMassive',        false],
    ['POST', '#^/mail/preview$#',                       MailController::class,           'preview',            false],
    ['POST', '#^/mail/test$#',                          MailController::class,           'sendTest',           false],
    ['GET',  '#^/process-scheduled$#',                  MailController::class,           'processScheduled',   false],
    ['GET',  '#^/history$#',                            MailController::class,           'getHistory',         false],
    ['GET',  '#^/history/(\d+)/deliveries$#',           MailController::class,           'getCampaignDeliveries', false],
    ['POST', '#^/history/(\d+)/cancel$#',               MailController::class,           'cancelCampaign',     false],
    ['POST', '#^/history/(\d+)/retry-failed$#',         MailController::class,           'retryFailed',        false],

    // Contactos
    ['GET',    '#^/contacts$#',                         ContactController::class,        'getAll',             false],
    ['POST',   '#^/contacts$#',                         ContactController::class,        'create',             false],
    ['PUT',    '#^/contacts/(\d+)$#',                   ContactController::class,        'update',             false],
    ['DELETE', '#^/contacts/(\d+)$#',                   ContactController::class,        'delete',             false],
    ['PUT',    '#^/contacts/(\d+)/subscription$#',      ContactController::class,        'setSubscription',    false],

    // Plantillas
    ['GET',    '#^/templates$#',                        TemplateController::class,       'getAll',             false],
    ['POST',   '#^/templates$#',                        TemplateController::class,       'create',             false],
    ['PUT',    '#^/templates/(\d+)$#',                  TemplateController::class,       'update',             false],
    ['DELETE', '#^/templates/(\d+)$#',                  TemplateController::class,       'delete',             false],
    ['POST',   '#^/upload-template-file$#',             UploadTemplateController::class, 'handleUpload',       false],
];

try {
    // =======================
    // Ruta solicitada
    // =======================
    // Se quita el prefijo del script (XAMPP: /TempliMail/backend/api, Docker: /backend/api)
    $uri = (string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $uri = str_replace(
        [
            '/TempliMail/backend/api/index.php',
            '/TempliMail/backend/api',
            '/backend/api/index.php',
            '/backend/api',
        ],
        '',
        $uri
    );

    $path   = rtrim($uri, '/');
    $method = $_SERVER['REQUEST_METHOD'];

    // =======================
    // Despacho
    // =======================
    $pathMatched = false;

    foreach ($routes as [$routeMethod, $pattern, $controller, $action, $isPublic]) {
        if (!preg_match($pattern, $path, $matches)) {
            continue;
        }

        $pathMatched = true;

        if ($routeMethod !== $method) {
            continue;
        }

        if (!$isPublic) {
            (new AuthMiddleware(new JwtService(Env::get('JWT_SECRET', ''))))->handle();
        }

        array_shift($matches);
        $args = array_map(static fn(string $m) => ctype_digit($m) ? (int) $m : $m, $matches);

        (new $controller())->$action(...$args);
        exit;
    }

    http_response_code($pathMatched ? 405 : 404);
    echo json_encode([
        'success' => false,
        'error'   => $pathMatched ? 'Método no permitido' : 'Endpoint no encontrado',
    ]);

} catch (Throwable $e) {
    error_log(sprintf('Unhandled: %s in %s:%d', $e->getMessage(), $e->getFile(), $e->getLine()));

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Error interno del servidor',
    ]);
}
