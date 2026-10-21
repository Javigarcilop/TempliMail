<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use TempliMail\Controllers\IAControlador;
use TempliMail\Controllers\AutenticacionControlador;
use TempliMail\Controllers\ContactoControlador;
use TempliMail\Controllers\PanelControlador;
use TempliMail\Controllers\GrupoControlador;
use TempliMail\Controllers\CorreoControlador;
use TempliMail\Controllers\PlantillaControlador;
use TempliMail\Controllers\BajaControlador;
use TempliMail\Controllers\SeguimientoControlador;
use TempliMail\Controllers\SubidaPlantillaControlador;
use TempliMail\Middleware\AutenticacionMiddleware;
use TempliMail\Services\JwtServicio;
use TempliMail\Utils\Entorno;

// =======================
// CORS
// =======================
// CORS_ORIGIN admite varios origenes separados por comas.
$allowedOrigins = array_map('trim', explode(',', Entorno::obtener('CORS_ORIGIN', 'http://localhost:4200')));
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
    ['POST', '#^/login$#',                              AutenticacionControlador::class,           'iniciarSesion',      true],
    ['POST', '#^/register$#',                           AutenticacionControlador::class,           'registrar',          true],
    ['GET',  '#^/unsubscribe/(\d+)/([a-f0-9]{64})$#',   BajaControlador::class,    'gestionarBaja',      true],
    ['POST', '#^/unsubscribe/(\d+)/([a-f0-9]{64})$#',   BajaControlador::class,    'gestionarBaja',      true],

    // Seguimiento de apertura y clics (lo visita el cliente de correo/navegador, no el frontend)
    ['GET',  '#^/t/(\d+)/([a-f0-9]{64})\.gif$#',         SeguimientoControlador::class, 'pixel',          true],
    ['GET',  '#^/t/(\d+)/([a-f0-9]{64})/click$#',        SeguimientoControlador::class, 'clic',           true],

    // Sesion
    ['GET',  '#^/me$#',                                 AutenticacionControlador::class,           'obtenerPerfil',      false],
    ['PUT',  '#^/me$#',                                 AutenticacionControlador::class,           'actualizarPerfil',   false],
    ['PUT',  '#^/me/contrasena$#',                        AutenticacionControlador::class,           'cambiarContrasena',  false],
    ['POST', '#^/logout$#',                             AutenticacionControlador::class,           'cerrarSesion',       false],

    // IA
    ['POST', '#^/ai/suggest-asunto$#',                 IAControlador::class,             'sugerirAsuntos',     false],

    // Dashboard
    ['GET',  '#^/dashboard/stats$#',                    PanelControlador::class,      'estadisticas',       false],
    ['GET',  '#^/dashboard/activity$#',                 PanelControlador::class,      'actividad',          false],
    ['GET',  '#^/dashboard/summary$#',                  PanelControlador::class,      'resumen',            false],

    // Correo
    ['POST', '#^/send-mail$#',                          CorreoControlador::class,           'enviarIndividual',   false],
    ['POST', '#^/send-massive$#',                       CorreoControlador::class,           'enviarMasivo',       false],
    ['POST', '#^/mail/preview$#',                       CorreoControlador::class,           'previsualizar',      false],
    ['POST', '#^/mail/test$#',                          CorreoControlador::class,           'enviarPrueba',       false],
    ['GET',  '#^/process-scheduled$#',                  CorreoControlador::class,           'procesarProgramadas', false],
    ['GET',  '#^/history$#',                            CorreoControlador::class,           'obtenerHistorial',   false],
    ['GET',  '#^/history/(\d+)/deliveries$#',           CorreoControlador::class,           'obtenerEntregasCampana', false],
    ['POST', '#^/history/(\d+)/cancel$#',               CorreoControlador::class,           'cancelarCampana',    false],
    ['POST', '#^/history/(\d+)/retry-failed$#',         CorreoControlador::class,           'reintentarFallidas', false],

    // Contactos
    ['GET',    '#^/contactos$#',                         ContactoControlador::class,        'obtenerTodos',       false],
    ['POST',   '#^/contactos$#',                         ContactoControlador::class,        'crear',              false],
    ['PUT',    '#^/contactos/(\d+)$#',                   ContactoControlador::class,        'actualizar',         false],
    ['DELETE', '#^/contactos/(\d+)$#',                   ContactoControlador::class,        'eliminar',           false],
    ['PUT',    '#^/contactos/(\d+)/subscription$#',      ContactoControlador::class,        'establecerSuscripcion', false],
    ['PUT',    '#^/contactos/(\d+)/grupos$#',            ContactoControlador::class,        'establecerGrupos',   false],
    ['POST',   '#^/contactos/import$#',                  ContactoControlador::class,        'importar',           false],

    // Grupos de contactos
    ['GET',    '#^/grupos$#',                           GrupoControlador::class,          'obtenerTodos',       false],
    ['POST',   '#^/grupos$#',                           GrupoControlador::class,          'crear',              false],
    ['PUT',    '#^/grupos/(\d+)$#',                     GrupoControlador::class,          'actualizar',         false],
    ['DELETE', '#^/grupos/(\d+)$#',                     GrupoControlador::class,          'eliminar',           false],

    // Plantillas
    ['GET',    '#^/plantillas$#',                        PlantillaControlador::class,       'obtenerTodos',       false],
    ['POST',   '#^/plantillas$#',                        PlantillaControlador::class,       'crear',              false],
    ['PUT',    '#^/plantillas/(\d+)$#',                  PlantillaControlador::class,       'actualizar',         false],
    ['DELETE', '#^/plantillas/(\d+)$#',                  PlantillaControlador::class,       'eliminar',           false],
    ['POST',   '#^/upload-template-file$#',             SubidaPlantillaControlador::class, 'gestionarSubida',    false],
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
            (new AutenticacionMiddleware(new JwtServicio(Entorno::obtener('JWT_SECRET', ''))))->autenticar();
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
