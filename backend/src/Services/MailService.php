<?php

declare(strict_types=1);

namespace TempliMail\Services;

use DateTimeImmutable;
use DateTimeZone;
use TempliMail\Exceptions\ApiException;
use TempliMail\Models\AuthModel;
use TempliMail\Models\ContactModel;
use TempliMail\Models\EmailCampaignModel;
use TempliMail\Models\EmailDeliveryModel;
use TempliMail\Models\TemplateModel;
use TempliMail\Utils\Env;
use TempliMail\Utils\Mailer;
use TempliMail\Utils\TemplateRenderer;
use TempliMail\Utils\Unsubscribe;
use Throwable;

class MailService
{
    private const MAX_RECIPIENTS = 5000;
    private const BATCH_SIZE     = 50;

    // =================================================================
    // Envio individual (sincrono, queda registrado en el historial)
    // =================================================================

    public static function sendSingle(int $userId, array $data): void
    {
        $to      = trim((string) ($data['to'] ?? ''));
        $subject = TemplateRenderer::sanitizeSubject((string) ($data['subject'] ?? ''));
        $body    = (string) ($data['body'] ?? '');

        if ($to === '' || $subject === '' || trim(strip_tags($body)) === '') {
            throw new ApiException('Destinatario, asunto y mensaje son obligatorios');
        }

        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            throw new ApiException('El email del destinatario no es valido');
        }

        $campaignId = EmailCampaignModel::create($userId, null, null, 'single', $subject, $body, 'processing', null);
        EmailDeliveryModel::insertBatch($campaignId, [['id' => null, 'email' => $to]]);
        $delivery = EmailDeliveryModel::getPendingByCampaign($campaignId, 1)[0];

        try {
            Mailer::sendOnce($to, $subject, $body);
            EmailDeliveryModel::markSent((int) $delivery['id']);
        } catch (Throwable $e) {
            EmailDeliveryModel::markFailed((int) $delivery['id'], $e->getMessage());
            EmailCampaignModel::markCompleted($campaignId);
            error_log('sendSingle failed: ' . $e->getMessage());

            throw new ApiException('No se pudo enviar el correo. Revisa la configuración SMTP.', 502);
        }

        EmailCampaignModel::markCompleted($campaignId);
    }

    // =================================================================
    // Vista previa y correo de prueba
    // =================================================================

    /** @return array{subject:string,body:string} */
    public static function preview(int $userId, array $data): array
    {
        [$subject, $body] = self::previewContent($userId, $data);

        return ['subject' => $subject, 'body' => $body];
    }

    /** Envia una copia de prueba al email del propio usuario (no consume historial). */
    public static function sendTest(int $userId, array $data): string
    {
        [$subject, $body] = self::previewContent($userId, $data);

        $user = AuthModel::findById($userId);

        if (!$user) {
            throw ApiException::notFound('Usuario no encontrado');
        }

        try {
            Mailer::sendOnce($user['email'], '[PRUEBA] ' . $subject, $body);
        } catch (Throwable $e) {
            error_log('sendTest failed: ' . $e->getMessage());
            throw new ApiException('No se pudo enviar el correo de prueba. Revisa la configuración SMTP.', 502);
        }

        return $user['email'];
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
        $contactIds = $data['contact_ids'] ?? null;
        $templateId = (int) ($data['template_id'] ?? 0);

        if (!is_array($contactIds) || $contactIds === [] || $templateId <= 0) {
            throw new ApiException('Selecciona una plantilla y al menos un contacto');
        }

        if (count($contactIds) > self::MAX_RECIPIENTS) {
            throw new ApiException('Maximo ' . self::MAX_RECIPIENTS . ' destinatarios por campaña');
        }

        $template = TemplateModel::getById($userId, $templateId);

        if (!$template) {
            throw ApiException::notFound('Plantilla no encontrada');
        }

        // Solo contactos del usuario y suscritos (evita enviar a contactos ajenos o dados de baja)
        $recipients = ContactModel::getSendableByIds($userId, $contactIds);

        if ($recipients === []) {
            throw new ApiException('Ninguno de los contactos seleccionados puede recibir correos');
        }

        $scheduledAt = self::parseScheduledAt($data['scheduled_at'] ?? null);

        $name = trim((string) ($data['name'] ?? ''));
        $name = $name !== '' ? mb_substr($name, 0, 255) : null;

        $campaignId = EmailCampaignModel::create(
            $userId,
            $templateId,
            $name,
            'mass',
            $template['subject'],
            $template['content_html'],
            'scheduled',
            $scheduledAt
        );

        EmailDeliveryModel::insertBatch($campaignId, $recipients);

        return [
            'campaign_id' => $campaignId,
            'scheduled'   => $scheduledAt !== null,
            'recipients'  => count($recipients),
            'excluded'    => count(array_unique(array_map('intval', $contactIds))) - count($recipients),
        ];
    }

    public static function cancelCampaign(int $userId, int $campaignId): void
    {
        EmailCampaignModel::cancel($userId, $campaignId);
    }

    public static function retryFailed(int $userId, int $campaignId): int
    {
        return EmailCampaignModel::retryFailed($userId, $campaignId);
    }

    public static function getHistory(int $userId): array
    {
        return EmailCampaignModel::getAllByUser($userId);
    }

    public static function getDeliveries(int $userId, int $campaignId): array
    {
        return EmailDeliveryModel::getByCampaign($campaignId, $userId);
    }

    // =================================================================
    // Procesamiento (lo ejecuta el worker; tambien /process-scheduled)
    // =================================================================

    /** Procesa las campanas vencidas del usuario. Devuelve cuantas envio. */
    public static function processDueForUser(int $userId): int
    {
        $processed = 0;

        foreach (EmailCampaignModel::getDue(50) as $campaign) {
            if ((int) $campaign['user_id'] === $userId && self::processCampaign((int) $campaign['id'])) {
                $processed++;
            }
        }

        return $processed;
    }

    /** Procesa todas las campanas vencidas (worker). Devuelve cuantas envio. */
    public static function processDue(): int
    {
        $processed = 0;

        foreach (EmailCampaignModel::getDue(10) as $campaign) {
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
        if (!EmailCampaignModel::claim($campaignId)) {
            return false;
        }

        $campaign = EmailCampaignModel::findById($campaignId);

        if (!$campaign) {
            return false;
        }

        $maxAttempts = max(1, Env::int('MAIL_MAX_ATTEMPTS', 3));
        $throttleUs  = max(0, Env::int('MAIL_THROTTLE_MS', 200)) * 1000;
        $mailer      = new Mailer(true);

        try {
            while ($deliveries = EmailDeliveryModel::getPendingByCampaign($campaignId, self::BATCH_SIZE)) {
                foreach ($deliveries as $delivery) {
                    self::deliver($mailer, $campaign, $delivery, $maxAttempts);

                    EmailCampaignModel::heartbeat($campaignId);

                    if ($throttleUs > 0) {
                        usleep($throttleUs);
                    }
                }
            }

            EmailCampaignModel::markCompleted($campaignId);
        } finally {
            $mailer->close();
        }

        return true;
    }

    // =================================================================
    // Internos
    // =================================================================

    private static function deliver(Mailer $mailer, array $campaign, array $delivery, int $maxAttempts): void
    {
        $deliveryId = (int) $delivery['id'];
        $contactId  = $delivery['contact_id'] !== null ? (int) $delivery['contact_id'] : null;

        if ($contactId === null && $campaign['type'] === 'mass') {
            EmailDeliveryModel::markSkipped($deliveryId, 'El contacto ya no existe');
            return;
        }

        if ($delivery['unsubscribed_at'] !== null) {
            EmailDeliveryModel::markSkipped($deliveryId, 'Contacto dado de baja');
            return;
        }

        $vars = [
            'first_name'      => $delivery['first_name'],
            'last_name'       => $delivery['last_name'],
            'company'         => $delivery['company'],
            'position'        => $delivery['position'],
            'email'           => $delivery['recipient_email'],
            'unsubscribe_url' => $contactId !== null ? Unsubscribe::url($contactId) : '',
        ];

        $subject = TemplateRenderer::sanitizeSubject(
            TemplateRenderer::render($campaign['subject'], $vars, false)
        );
        $body = self::withFooter((string) $campaign['content_html']);
        $body = TemplateRenderer::render($body, $vars, true);

        $headers = $contactId !== null
            ? [
                'List-Unsubscribe'      => '<' . $vars['unsubscribe_url'] . '>',
                'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
            ]
            : [];

        $lastError = '';

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $mailer->send($delivery['recipient_email'], $subject, $body, $headers);
                EmailDeliveryModel::markSent($deliveryId, $attempt);
                return;
            } catch (Throwable $e) {
                $lastError = $e->getMessage();

                if ($attempt < $maxAttempts) {
                    sleep($attempt * 2); // espera progresiva: 2 s, 4 s...
                }
            }
        }

        EmailDeliveryModel::markFailed($deliveryId, $lastError, $maxAttempts);
    }

    /** Anade el pie con el enlace de baja si el contenido no lo incluye ya. */
    private static function withFooter(string $html): string
    {
        return str_contains($html, '{{unsubscribe_url')
            ? $html
            : $html . Unsubscribe::FOOTER;
    }

    /**
     * Prepara asunto y cuerpo de una vista previa / prueba con los datos de un
     * contacto real (si se indica) o de ejemplo.
     *
     * @return array{0:string,1:string}
     */
    private static function previewContent(int $userId, array $data): array
    {
        $subject = (string) ($data['subject'] ?? '');
        $html    = (string) ($data['content_html'] ?? '');

        if (trim($subject) === '' && trim(strip_tags($html)) === '') {
            throw new ApiException('No hay contenido que previsualizar');
        }

        $vars = TemplateRenderer::SAMPLE;

        if (!empty($data['contact_id'])) {
            $contact = ContactModel::getById($userId, (int) $data['contact_id']);

            if ($contact) {
                $vars = array_intersect_key($contact, TemplateRenderer::VARIABLES);
            }
        }

        $vars['unsubscribe_url'] = '#';

        return [
            TemplateRenderer::sanitizeSubject(TemplateRenderer::render($subject, $vars, false)),
            TemplateRenderer::render(self::withFooter($html), $vars, true),
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
            throw new ApiException('La fecha de programación no es válida');
        }

        $utc = $date->setTimezone(new DateTimeZone('UTC'));

        if ($utc <= new DateTimeImmutable('now', new DateTimeZone('UTC'))) {
            return null;
        }

        return $utc->format('Y-m-d H:i:s');
    }
}
