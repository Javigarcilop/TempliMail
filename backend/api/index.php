<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use TempliMail\Controllers\ControladorIA;
use TempliMail\Controllers\ControladorAutenticacion;
use TempliMail\Controllers\ControladorContacto;
use TempliMail\Controllers\ControladorPanel;
use TempliMail\Controllers\ControladorGrupo;
use TempliMail\Controllers\ControladorCorreo;
use TempliMail\Controllers\ControladorPlantilla;
use TempliMail\Controllers\ControladorBaja;
use TempliMail\Controllers\ControladorSubidaPlantilla;
use TempliMail\Middleware\MiddlewareAutenticacion;
use TempliMail\Services\ServicioJwt;
use TempliMail\Utils\Entorno;

// =======================
// CORS
// =======================
// CORS_ORIGIN admite varios origenes separados por comas.
$allowedOrigins = array_map('trim', explode(',', Entorno::get('CORS_ORIGIN', 'http://localhost:4200')));
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
    ['POST', '#^/login$#',                              ControladorAutenticacion::class,           'login',              true],
    ['POST', '#^/register$#',                           ControladorAutenticacion::class,           'register',           true],
    ['GET',  '#^/unsubscribe/(\d+)/([a-f0-9]{64})$#',   ControladorBaja::class,    'handle',             true],
    ['POST', '#^/unsubscribe/(\d+)/([a-f0-9]{64})$#',   ControladorBaja::class,    'handle',             true],

    // Sesion
    ['GET',  '#^/me$#',                                 ControladorAutenticacion::class,           'me',                 false],
    ['PUT',  '#^/me$#',                                 ControladorAutenticacion::class,           'updateProfile',      false],
    ['PUT',  '#^/me/contrasena$#',                        ControladorAutenticacion::class,           'changePassword',     false],
    ['POST', '#^/logout$#',                             ControladorAutenticacion::class,           'logout',             false],

    // IA
    ['POST', '#^/ai/suggest-asunto$#',                 ControladorIA::class,             'suggestSubjects',    false],

    // Dashboard
    ['GET',  '#^/dashboard/stats$#',                    ControladorPanel::class,      'stats',              false],
    ['GET',  '#^/dashboard/activity$#',                 ControladorPanel::class,      'activity',           false],
    ['GET',  '#^/dashboard/summary$#',                  ControladorPanel::class,      'summary',            false],

    // Correo
    ['POST', '#^/send-mail$#',                          ControladorCorreo::class,           'sendSingle',         false],
    ['POST', '#^/send-massive$#',                       ControladorCorreo::class,           'sendMassive',        false],
    ['POST', '#^/mail/preview$#',                       ControladorCorreo::class,           'preview',            false],
    ['POST', '#^/mail/test$#',                          ControladorCorreo::class,           'sendTest',           false],
    ['GET',  '#^/process-scheduled$#',                  ControladorCorreo::class,           'processScheduled',   false],
    ['GET',  '#^/history$#',                            ControladorCorreo::class,           'getHistory',         false],
    ['GET',  '#^/history/(\d+)/deliveries$#',           ControladorCorreo::class,           'getCampaignDeliveries', false],
    ['POST', '#^/history/(\d+)/cancel$#',               ControladorCorreo::class,           'cancelCampaign',     false],
    ['POST', '#^/history/(\d+)/retry-failed$#',         ControladorCorreo::class,           'retryFailed',        false],

    // Contactos
    ['GET',    '#^/contactos$#',                         ControladorContacto::class,        'getAll',             false],
    ['POST',   '#^/contactos$#',                         ControladorContacto::class,        'create',             false],
    ['PUT',    '#^/contactos/(\d+)$#',                   ControladorContacto::class,        'update',             false],
    ['DELETE', '#^/contactos/(\d+)$#',                   ControladorContacto::class,        'delete',             false],
    ['PUT',    '#^/contactos/(\d+)/subscription$#',      ControladorContacto::class,        'setSubscription',    false],
    ['PUT',    '#^/contactos/(\d+)/grupos$#',            ControladorContacto::class,        'setGroups',          false],
    ['POST',   '#^/contactos/import$#',                  ControladorContacto::class,        'import',             false],

    // Grupos de contactos
    ['GET',    '#^/grupos$#',                           ControladorGrupo::class,          'getAll',             false],
    ['POST',   '#^/grupos$#',                           ControladorGrupo::class,          'create',             false],
    ['PUT',    '#^/grupos/(\d+)$#',                     ControladorGrupo::class,          'update',             false],
    ['DELETE', '#^/grupos/(\d+)$#',                     ControladorGrupo::class,          'delete',             false],

    // Plantillas
    ['GET',    '#^/plantillas$#',                        ControladorPlantilla::class,       'getAll',             false],
    ['POST',   '#^/plantillas$#',                        ControladorPlantilla::class,       'create',             false],
    ['PUT',    '#^/plantillas/(\d+)$#',                  ControladorPlantilla::class,       'update',             false],
    ['DELETE', '#^/plantillas/(\d+)$#',                  ControladorPlantilla::class,       'delete',             false],
    ['POST',   '#^/upload-template-file$#',             ControladorSubidaPlantilla::class, 'handleUpload',       false],
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
            (new MiddlewareAutenticacion(new ServicioJwt(Entorno::get('JWT_SECRET', ''))))->handle();
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
