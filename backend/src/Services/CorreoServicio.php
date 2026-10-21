<?php

declare(strict_types=1);

namespace TempliMail\Services;

use DateTimeImmutable;
use DateTimeZone;
use TempliMail\Exceptions\ApiExcepcion;
use TempliMail\Models\AutenticacionModelo;
use TempliMail\Models\ContactoModelo;
use TempliMail\Models\CampanaCorreoModelo;
use TempliMail\Models\EntregaCorreoModelo;
use TempliMail\Models\PlantillaModelo;
use TempliMail\Utils\Entorno;
use TempliMail\Utils\EnviadorCorreo;
use TempliMail\Utils\RenderizadorPlantilla;
use TempliMail\Utils\Baja;
use TempliMail\Utils\Seguimiento;
use Throwable;

class CorreoServicio
{
    private const MAX_RECIPIENTS = 5000;
    private const BATCH_SIZE     = 50;

    // =================================================================
    // Envio individual (sincrono, queda registrado en el historial)
    // =================================================================

    public static function enviarIndividual(int $userId, array $data): void
    {
        $to      = trim((string) ($data['destinatario'] ?? ''));
        $asunto = RenderizadorPlantilla::sanearAsunto((string) ($data['asunto'] ?? ''));
        $body    = (string) ($data['cuerpo'] ?? '');

        if ($to === '' || $asunto === '' || trim(strip_tags($body)) === '') {
            throw new ApiExcepcion('Destinatario, asunto y mensaje son obligatorios');
        }

        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            throw new ApiExcepcion('El correo del destinatario no es valido');
        }

        $campaignId = CampanaCorreoModelo::crear($userId, null, null, 'single', $asunto, $body, 'processing', null);
        EntregaCorreoModelo::insertarLote($campaignId, [['id' => null, 'correo' => $to]]);
        $delivery = EntregaCorreoModelo::obtenerPendientesPorCampana($campaignId, 1)[0];

        try {
            $trackedBody = self::aplicarSeguimiento($body, (int) $delivery['id'], null);
            EnviadorCorreo::enviarUnaVez($to, $asunto, $trackedBody);
            EntregaCorreoModelo::marcarEnviada((int) $delivery['id']);
        } catch (Throwable $e) {
            EntregaCorreoModelo::marcarFallida((int) $delivery['id'], $e->getMessage());
            CampanaCorreoModelo::marcarCompletada($campaignId);
            error_log('enviarIndividual failed: ' . $e->getMessage());

            throw new ApiExcepcion('No se pudo enviar el correo. Revisa la configuración SMTP.', 502);
        }

        CampanaCorreoModelo::marcarCompletada($campaignId);
    }

    // =================================================================
    // Vista previa y correo de prueba
    // =================================================================

    /** @return array{asunto:string,cuerpo:string} */
    public static function previsualizar(int $userId, array $data): array
    {
        [$asunto, $body] = self::prepararVistaPrevia($userId, $data);

        return ['asunto' => $asunto, 'cuerpo' => $body];
    }

    /** Envia una copia de prueba al correo del propio usuario (no consume historial). */
    public static function enviarPrueba(int $userId, array $data): string
    {
        [$asunto, $body] = self::prepararVistaPrevia($userId, $data);

        $user = AutenticacionModelo::buscarPorId($userId);

        if (!$user) {
            throw ApiExcepcion::noEncontrado('Usuario no encontrado');
        }

        try {
            EnviadorCorreo::enviarUnaVez($user['correo'], '[PRUEBA] ' . $asunto, $body);
        } catch (Throwable $e) {
            error_log('enviarPrueba failed: ' . $e->getMessage());
            throw new ApiExcepcion('No se pudo enviar el correo de prueba. Revisa la configuración SMTP.', 502);
        }

        return $user['correo'];
    }

    // =================================================================
    // Campanas masivas
    // =================================================================

    /**
     * Encola una campana. La envia el worker (en cuanto puede si no hay fecha,
     * o a la hora indicada si esta programada).
     */
    public static function enviarMasivo(int $userId, array $data): array
    {
        $contactIds = $data['ids_contacto'] ?? null;
        $templateId = (int) ($data['plantilla_id'] ?? 0);

        if (!is_array($contactIds) || $contactIds === [] || $templateId <= 0) {
            throw new ApiExcepcion('Selecciona una plantilla y al menos un contacto');
        }

        if (count($contactIds) > self::MAX_RECIPIENTS) {
            throw new ApiExcepcion('Maximo ' . self::MAX_RECIPIENTS . ' destinatarios por campaña');
        }

        $template = PlantillaModelo::obtenerPorId($userId, $templateId);

        if (!$template) {
            throw ApiExcepcion::noEncontrado('Plantilla no encontrada');
        }

        // Solo contactos del usuario y suscritos (evita enviar a contactos ajenos o dados de baja)
        $recipients = ContactoModelo::obtenerEnviablesPorIds($userId, $contactIds);

        if ($recipients === []) {
            throw new ApiExcepcion('Ninguno de los contactos seleccionados puede recibir correos');
        }

        $scheduledAt = self::normalizarFechaProgramada($data['programado_en'] ?? null);

        $nombre = trim((string) ($data['nombre'] ?? ''));
        $nombre = $nombre !== '' ? mb_substr($nombre, 0, 255) : null;

        $campaignId = CampanaCorreoModelo::crear(
            $userId,
            $templateId,
            $nombre,
            'mass',
            $template['asunto'],
            $template['contenido_html'],
            'scheduled',
            $scheduledAt
        );

        EntregaCorreoModelo::insertarLote($campaignId, $recipients);

        return [
            'campana_id'    => $campaignId,
            'programado'    => $scheduledAt !== null,
            'destinatarios' => count($recipients),
            'excluidos'     => count(array_unique(array_map('intval', $contactIds))) - count($recipients),
        ];
    }

    public static function cancelarCampana(int $userId, int $campaignId): void
    {
        CampanaCorreoModelo::cancelar($userId, $campaignId);
    }

    public static function reintentarFallidas(int $userId, int $campaignId): int
    {
        return CampanaCorreoModelo::reintentarFallidas($userId, $campaignId);
    }

    public static function obtenerHistorial(int $userId): array
    {
        return CampanaCorreoModelo::obtenerTodosPorUsuario($userId);
    }

    public static function obtenerEntregas(int $userId, int $campaignId): array
    {
        return EntregaCorreoModelo::obtenerPorCampana($campaignId, $userId);
    }

    // =================================================================
    // Procesamiento (lo ejecuta el worker; tambien /process-scheduled)
    // =================================================================

    /** Procesa las campanas vencidas del usuario. Devuelve cuantas envio. */
    public static function procesarPendientesUsuario(int $userId): int
    {
        $processed = 0;

        foreach (CampanaCorreoModelo::obtenerPendientesEnvio(50) as $campaign) {
            if ((int) $campaign['usuario_id'] === $userId && self::procesarCampana((int) $campaign['id'])) {
                $processed++;
            }
        }

        return $processed;
    }

    /** Procesa todas las campanas vencidas (worker). Devuelve cuantas envio. */
    public static function procesarPendientes(): int
    {
        $processed = 0;

        foreach (CampanaCorreoModelo::obtenerPendientesEnvio(10) as $campaign) {
            if (self::procesarCampana((int) $campaign['id'])) {
                $processed++;
            }
        }

        return $processed;
    }

    /**
     * Envia una campana. La "reclama" antes de empezar, asi que si dos procesos
     * coinciden solo uno la envia. Devuelve false si no pudo reclamarla.
     */
    public static function procesarCampana(int $campaignId): bool
    {
        if (!CampanaCorreoModelo::reclamar($campaignId)) {
            return false;
        }

        $campaign = CampanaCorreoModelo::buscarPorId($campaignId);

        if (!$campaign) {
            return false;
        }

        $maxAttempts = max(1, Entorno::entero('MAIL_MAX_ATTEMPTS', 3));
        $throttleUs  = max(0, Entorno::entero('MAIL_THROTTLE_MS', 200)) * 1000;
        $mailer      = new EnviadorCorreo(true);

        try {
            while ($deliveries = EntregaCorreoModelo::obtenerPendientesPorCampana($campaignId, self::BATCH_SIZE)) {
                foreach ($deliveries as $delivery) {
                    self::entregar($mailer, $campaign, $delivery, $maxAttempts);

                    CampanaCorreoModelo::latido($campaignId);

                    if ($throttleUs > 0) {
                        usleep($throttleUs);
                    }
                }
            }

            CampanaCorreoModelo::marcarCompletada($campaignId);
        } finally {
            $mailer->cerrar();
        }

        return true;
    }

    // =================================================================
    // Internos
    // =================================================================

    private static function entregar(EnviadorCorreo $mailer, array $campaign, array $delivery, int $maxAttempts): void
    {
        $deliveryId = (int) $delivery['id'];
        $contactId  = $delivery['contacto_id'] !== null ? (int) $delivery['contacto_id'] : null;

        if ($contactId === null && $campaign['tipo'] === 'mass') {
            EntregaCorreoModelo::marcarOmitida($deliveryId, 'El contacto ya no existe');
            return;
        }

        if ($delivery['baja_en'] !== null) {
            EntregaCorreoModelo::marcarOmitida($deliveryId, 'Contacto dado de baja');
            return;
        }

        $vars = [
            'nombre'      => $delivery['nombre'],
            'apellidos'       => $delivery['apellidos'],
            'empresa'         => $delivery['empresa'],
            'cargo'        => $delivery['cargo'],
            'correo'           => $delivery['correo_destinatario'],
            'enlace_baja' => $contactId !== null ? Baja::generarUrl($contactId) : '',
        ];

        $asunto = RenderizadorPlantilla::sanearAsunto(
            RenderizadorPlantilla::renderizar($campaign['asunto'], $vars, false)
        );
        $body = self::conPieBaja((string) $campaign['contenido_html']);
        $body = RenderizadorPlantilla::renderizar($body, $vars, true);
        $body = self::aplicarSeguimiento($body, $deliveryId, $contactId !== null ? $vars['enlace_baja'] : null);

        $headers = $contactId !== null
            ? [
                'List-Unsubscribe'      => '<' . $vars['enlace_baja'] . '>',
                'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
            ]
            : [];

        $lastError = '';

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $mailer->enviar($delivery['correo_destinatario'], $asunto, $body, $headers);
                EntregaCorreoModelo::marcarEnviada($deliveryId, $attempt);
                return;
            } catch (Throwable $e) {
                $lastError = $e->getMessage();

                if ($attempt < $maxAttempts) {
                    sleep($attempt * 2); // espera progresiva: 2 s, 4 s...
                }
            }
        }

        EntregaCorreoModelo::marcarFallida($deliveryId, $lastError, $maxAttempts);
    }

    /** Anade el pie con el enlace de baja si el contenido no lo incluye ya. */
    private static function conPieBaja(string $html): string
    {
        return str_contains($html, '{{unsubscribe_url') || str_contains($html, '{{enlace_baja')
            ? $html
            : $html . Baja::FOOTER;
    }

    /**
     * Incrusta el seguimiento de apertura (pixel) y de clics en el HTML ya
     * renderizado (variables resueltas). Solo reescribe enlaces http(s)
     * absolutos: es un reemplazo por expresion regular, no un parser HTML
     * completo, pero basta para el HTML que genera el editor (TinyMCE).
     *
     * @param ?string $skipUrl el enlace de baja no se reescribe (se deja ir directo)
     */
    private static function aplicarSeguimiento(string $html, int $deliveryId, ?string $skipUrl): string
    {
        $html = (string) preg_replace_callback(
            '/\bhref\s*=\s*(["\'])(https?:\/\/[^"\']+)\1/i',
            static function (array $m) use ($deliveryId, $skipUrl): string {
                $url = html_entity_decode($m[2], ENT_QUOTES, 'UTF-8');

                if ($skipUrl !== null && $url === $skipUrl) {
                    return $m[0];
                }

                $tracked = htmlspecialchars(Seguimiento::generarUrlClic($deliveryId, $url), ENT_QUOTES, 'UTF-8');

                return 'href=' . $m[1] . $tracked . $m[1];
            },
            $html
        );

        $pixel = '<img src="' . htmlspecialchars(Seguimiento::generarUrlPixel($deliveryId), ENT_QUOTES, 'UTF-8')
            . '" width="1" height="1" alt="" style="display:none" />';

        return str_contains($html, '</body>')
            ? str_replace('</body>', $pixel . '</body>', $html)
            : $html . $pixel;
    }

    /**
     * Prepara asunto y cuerpo de una vista previa / prueba con los datos de un
     * contacto real (si se indica) o de ejemplo.
     *
     * @return array{0:string,1:string}
     */
    private static function prepararVistaPrevia(int $userId, array $data): array
    {
        $asunto = (string) ($data['asunto'] ?? '');
        $html    = (string) ($data['contenido_html'] ?? '');

        if (trim($asunto) === '' && trim(strip_tags($html)) === '') {
            throw new ApiExcepcion('No hay contenido que previsualizar');
        }

        $vars = RenderizadorPlantilla::SAMPLE;

        if (!empty($data['contacto_id'])) {
            $contact = ContactoModelo::obtenerPorId($userId, (int) $data['contacto_id']);

            if ($contact) {
                $vars = array_intersect_key($contact, RenderizadorPlantilla::VARIABLES);
            }
        }

        $vars['enlace_baja'] = '#';

        return [
            RenderizadorPlantilla::sanearAsunto(RenderizadorPlantilla::renderizar($asunto, $vars, false)),
            RenderizadorPlantilla::renderizar(self::conPieBaja($html), $vars, true),
        ];
    }

    /**
     * Convierte la fecha recibida (ISO 8601, normalmente con zona horaria) a UTC.
     * Devuelve null si no hay fecha o si ya ha pasado (se envia en cuanto se pueda).
     */
    private static function normalizarFechaProgramada(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            $date = new DateTimeImmutable((string) $value, new DateTimeZone('UTC'));
        } catch (Throwable) {
            throw new ApiExcepcion('La fecha de programación no es válida');
        }

        $utc = $date->setTimezone(new DateTimeZone('UTC'));

        if ($utc <= new DateTimeImmutable('now', new DateTimeZone('UTC'))) {
            return null;
        }

        return $utc->format('Y-m-d H:i:s');
    }
}
