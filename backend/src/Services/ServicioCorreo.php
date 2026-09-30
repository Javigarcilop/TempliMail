<?php

declare(strict_types=1);

namespace TempliMail\Services;

use DateTimeImmutable;
use DateTimeZone;
use TempliMail\Exceptions\ExcepcionApi;
use TempliMail\Models\ModeloAutenticacion;
use TempliMail\Models\ModeloContacto;
use TempliMail\Models\ModeloCampanaCorreo;
use TempliMail\Models\ModeloEntregaCorreo;
use TempliMail\Models\ModeloPlantilla;
use TempliMail\Utils\Entorno;
use TempliMail\Utils\EnviadorCorreo;
use TempliMail\Utils\RenderizadorPlantilla;
use TempliMail\Utils\Baja;
use Throwable;

class ServicioCorreo
{
    private const MAX_RECIPIENTS = 5000;
    private const BATCH_SIZE     = 50;

    // =================================================================
    // Envio individual (sincrono, queda registrado en el historial)
    // =================================================================

    public static function sendSingle(int $userId, array $data): void
    {
        $to      = trim((string) ($data['destinatario'] ?? ''));
        $asunto = RenderizadorPlantilla::sanitizeSubject((string) ($data['asunto'] ?? ''));
        $body    = (string) ($data['cuerpo'] ?? '');

        if ($to === '' || $asunto === '' || trim(strip_tags($body)) === '') {
            throw new ExcepcionApi('Destinatario, asunto y mensaje son obligatorios');
        }

        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            throw new ExcepcionApi('El correo del destinatario no es valido');
        }

        $campaignId = ModeloCampanaCorreo::create($userId, null, null, 'single', $asunto, $body, 'processing', null);
        ModeloEntregaCorreo::insertBatch($campaignId, [['id' => null, 'correo' => $to]]);
        $delivery = ModeloEntregaCorreo::getPendingByCampaign($campaignId, 1)[0];

        try {
            EnviadorCorreo::sendOnce($to, $asunto, $body);
            ModeloEntregaCorreo::markSent((int) $delivery['id']);
        } catch (Throwable $e) {
            ModeloEntregaCorreo::markFailed((int) $delivery['id'], $e->getMessage());
            ModeloCampanaCorreo::markCompleted($campaignId);
            error_log('sendSingle failed: ' . $e->getMessage());

            throw new ExcepcionApi('No se pudo enviar el correo. Revisa la configuración SMTP.', 502);
        }

        ModeloCampanaCorreo::markCompleted($campaignId);
    }

    // =================================================================
    // Vista previa y correo de prueba
    // =================================================================

    /** @return array{asunto:string,cuerpo:string} */
    public static function preview(int $userId, array $data): array
    {
        [$asunto, $body] = self::previewContent($userId, $data);

        return ['asunto' => $asunto, 'cuerpo' => $body];
    }

    /** Envia una copia de prueba al correo del propio usuario (no consume historial). */
    public static function sendTest(int $userId, array $data): string
    {
        [$asunto, $body] = self::previewContent($userId, $data);

        $user = ModeloAutenticacion::findById($userId);

        if (!$user) {
            throw ExcepcionApi::notFound('Usuario no encontrado');
        }

        try {
            EnviadorCorreo::sendOnce($user['correo'], '[PRUEBA] ' . $asunto, $body);
        } catch (Throwable $e) {
            error_log('sendTest failed: ' . $e->getMessage());
            throw new ExcepcionApi('No se pudo enviar el correo de prueba. Revisa la configuración SMTP.', 502);
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
    public static function sendMassive(int $userId, array $data): array
    {
        $contactIds = $data['ids_contacto'] ?? null;
        $templateId = (int) ($data['plantilla_id'] ?? 0);

        if (!is_array($contactIds) || $contactIds === [] || $templateId <= 0) {
            throw new ExcepcionApi('Selecciona una plantilla y al menos un contacto');
        }

        if (count($contactIds) > self::MAX_RECIPIENTS) {
            throw new ExcepcionApi('Maximo ' . self::MAX_RECIPIENTS . ' destinatarios por campaña');
        }

        $template = ModeloPlantilla::getById($userId, $templateId);

        if (!$template) {
            throw ExcepcionApi::notFound('Plantilla no encontrada');
        }

        // Solo contactos del usuario y suscritos (evita enviar a contactos ajenos o dados de baja)
        $recipients = ModeloContacto::getSendableByIds($userId, $contactIds);

        if ($recipients === []) {
            throw new ExcepcionApi('Ninguno de los contactos seleccionados puede recibir correos');
        }

        $scheduledAt = self::parseScheduledAt($data['programado_en'] ?? null);

        $nombre = trim((string) ($data['nombre'] ?? ''));
        $nombre = $nombre !== '' ? mb_substr($nombre, 0, 255) : null;

        $campaignId = ModeloCampanaCorreo::create(
            $userId,
            $templateId,
            $nombre,
            'mass',
            $template['asunto'],
            $template['contenido_html'],
            'scheduled',
            $scheduledAt
        );

        ModeloEntregaCorreo::insertBatch($campaignId, $recipients);

        return [
            'campana_id'    => $campaignId,
            'programado'    => $scheduledAt !== null,
            'destinatarios' => count($recipients),
            'excluidos'     => count(array_unique(array_map('intval', $contactIds))) - count($recipients),
        ];
    }

    public static function cancelCampaign(int $userId, int $campaignId): void
    {
        ModeloCampanaCorreo::cancel($userId, $campaignId);
    }

    public static function retryFailed(int $userId, int $campaignId): int
    {
        return ModeloCampanaCorreo::retryFailed($userId, $campaignId);
    }

    public static function getHistory(int $userId): array
    {
        return ModeloCampanaCorreo::getAllByUser($userId);
    }

    public static function getDeliveries(int $userId, int $campaignId): array
    {
        return ModeloEntregaCorreo::getByCampaign($campaignId, $userId);
    }

    // =================================================================
    // Procesamiento (lo ejecuta el worker; tambien /process-scheduled)
    // =================================================================

    /** Procesa las campanas vencidas del usuario. Devuelve cuantas envio. */
    public static function processDueForUser(int $userId): int
    {
        $processed = 0;

        foreach (ModeloCampanaCorreo::getDue(50) as $campaign) {
            if ((int) $campaign['usuario_id'] === $userId && self::processCampaign((int) $campaign['id'])) {
                $processed++;
            }
        }

        return $processed;
    }

    /** Procesa todas las campanas vencidas (worker). Devuelve cuantas envio. */
    public static function processDue(): int
    {
        $processed = 0;

        foreach (ModeloCampanaCorreo::getDue(10) as $campaign) {
            if (self::processCampaign((int) $campaign['id'])) {
                $processed++;
            }
        }

        return $processed;
    }

    /**
     * Envia una campana. La "reclama" antes de empezar, asi que si dos procesos
     * coinciden solo uno la envia. Devuelve false si no pudo reclamarla.
     */
    public static function processCampaign(int $campaignId): bool
    {
        if (!ModeloCampanaCorreo::claim($campaignId)) {
            return false;
        }

        $campaign = ModeloCampanaCorreo::findById($campaignId);

        if (!$campaign) {
            return false;
        }

        $maxAttempts = max(1, Entorno::int('MAIL_MAX_ATTEMPTS', 3));
        $throttleUs  = max(0, Entorno::int('MAIL_THROTTLE_MS', 200)) * 1000;
        $mailer      = new EnviadorCorreo(true);

        try {
            while ($deliveries = ModeloEntregaCorreo::getPendingByCampaign($campaignId, self::BATCH_SIZE)) {
                foreach ($deliveries as $delivery) {
                    self::deliver($mailer, $campaign, $delivery, $maxAttempts);

                    ModeloCampanaCorreo::heartbeat($campaignId);

                    if ($throttleUs > 0) {
                        usleep($throttleUs);
                    }
                }
            }

            ModeloCampanaCorreo::markCompleted($campaignId);
        } finally {
            $mailer->close();
        }

        return true;
    }

    // =================================================================
    // Internos
    // =================================================================

    private static function deliver(EnviadorCorreo $mailer, array $campaign, array $delivery, int $maxAttempts): void
    {
        $deliveryId = (int) $delivery['id'];
        $contactId  = $delivery['contacto_id'] !== null ? (int) $delivery['contacto_id'] : null;

        if ($contactId === null && $campaign['tipo'] === 'mass') {
            ModeloEntregaCorreo::markSkipped($deliveryId, 'El contacto ya no existe');
            return;
        }

        if ($delivery['baja_en'] !== null) {
            ModeloEntregaCorreo::markSkipped($deliveryId, 'Contacto dado de baja');
            return;
        }

        $vars = [
            'nombre'      => $delivery['nombre'],
            'apellidos'       => $delivery['apellidos'],
            'empresa'         => $delivery['empresa'],
            'cargo'        => $delivery['cargo'],
            'correo'           => $delivery['correo_destinatario'],
            'enlace_baja' => $contactId !== null ? Baja::url($contactId) : '',
        ];

        $asunto = RenderizadorPlantilla::sanitizeSubject(
            RenderizadorPlantilla::render($campaign['asunto'], $vars, false)
        );
        $body = self::withFooter((string) $campaign['contenido_html']);
        $body = RenderizadorPlantilla::render($body, $vars, true);

        $headers = $contactId !== null
            ? [
                'List-Unsubscribe'      => '<' . $vars['enlace_baja'] . '>',
                'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
            ]
            : [];

        $lastError = '';

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $mailer->send($delivery['correo_destinatario'], $asunto, $body, $headers);
                ModeloEntregaCorreo::markSent($deliveryId, $attempt);
                return;
            } catch (Throwable $e) {
                $lastError = $e->getMessage();

                if ($attempt < $maxAttempts) {
                    sleep($attempt * 2); // espera progresiva: 2 s, 4 s...
                }
            }
        }

        ModeloEntregaCorreo::markFailed($deliveryId, $lastError, $maxAttempts);
    }

    /** Anade el pie con el enlace de baja si el contenido no lo incluye ya. */
    private static function withFooter(string $html): string
    {
        return str_contains($html, '{{unsubscribe_url') || str_contains($html, '{{enlace_baja')
            ? $html
            : $html . Baja::FOOTER;
    }

    /**
     * Prepara asunto y cuerpo de una vista previa / prueba con los datos de un
     * contacto real (si se indica) o de ejemplo.
     *
     * @return array{0:string,1:string}
     */
    private static function previewContent(int $userId, array $data): array
    {
        $asunto = (string) ($data['asunto'] ?? '');
        $html    = (string) ($data['contenido_html'] ?? '');

        if (trim($asunto) === '' && trim(strip_tags($html)) === '') {
            throw new ExcepcionApi('No hay contenido que previsualizar');
        }

        $vars = RenderizadorPlantilla::SAMPLE;

        if (!empty($data['contacto_id'])) {
            $contact = ModeloContacto::getById($userId, (int) $data['contacto_id']);

            if ($contact) {
                $vars = array_intersect_key($contact, RenderizadorPlantilla::VARIABLES);
            }
        }

        $vars['enlace_baja'] = '#';

        return [
            RenderizadorPlantilla::sanitizeSubject(RenderizadorPlantilla::render($asunto, $vars, false)),
            RenderizadorPlantilla::render(self::withFooter($html), $vars, true),
        ];
    }

    /**
     * Convierte la fecha recibida (ISO 8601, normalmente con zona horaria) a UTC.
     * Devuelve null si no hay fecha o si ya ha pasado (se envia en cuanto se pueda).
     */
    private static function parseScheduledAt(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            $date = new DateTimeImmutable((string) $value, new DateTimeZone('UTC'));
        } catch (Throwable) {
            throw new ExcepcionApi('La fecha de programación no es válida');
        }

        $utc = $date->setTimezone(new DateTimeZone('UTC'));

        if ($utc <= new DateTimeImmutable('now', new DateTimeZone('UTC'))) {
            return null;
        }

        return $utc->format('Y-m-d H:i:s');
    }
}
