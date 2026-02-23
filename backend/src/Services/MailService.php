<?php

namespace TempliMail\Services;

use TempliMail\Models\EmailCampaignModel;
use TempliMail\Models\EmailDeliveryModel;
use TempliMail\Models\TemplateModel;
use TempliMail\Utils\Mailer;
use Exception;

class MailService
{
    public static function sendSingle(int $userId, array $data): void
    {
        if (!isset($data['to'], $data['subject'], $data['body'])) {
            throw new Exception('Missing required data');
        }

        // Aquí podrías registrar envío individual en BD si quieres trazabilidad
        Mailer::send($data['to'], $data['subject'], $data['body']);
    }

    public static function sendMassive(int $userId, array $data): array
    {
        if (empty($data['contact_ids']) || empty($data['template_id'])) {
            throw new Exception('Incomplete data');
        }

        $template = TemplateModel::getById($userId, (int)$data['template_id']);

        if (!$template) {
            throw new Exception('Template not found');
        }

        $scheduled = !empty($data['scheduled_at']);
        $status = $scheduled ? 'scheduled' : 'processing';

        $campaignId = EmailCampaignModel::create(
            $userId,
            (int)$data['template_id'],
            $template['subject'],
            $template['content_html'],
            $status,
            $data['scheduled_at'] ?? null
        );

        EmailDeliveryModel::insertBatch(
            $campaignId,
            $data['contact_ids']
        );

        if (!$scheduled) {
            self::processCampaign($userId, $campaignId);
        }

        return ['scheduled' => $scheduled];
    }

    public static function processCampaign(int $userId, int $campaignId): void
{
    // 🔐 Validar ownership
    $campaign = EmailCampaignModel::getById($userId, $campaignId);

    if (!$campaign) {
        throw new Exception('Campaign not found');
    }

    // Marcar como processing (protegido por user_id)
    EmailCampaignModel::setProcessing($userId, $campaignId);

    $deliveries = EmailDeliveryModel::getPendingByCampaign($campaignId);

    foreach ($deliveries as $delivery) {
        try {
            Mailer::send(
                $delivery['email'],
                $campaign['subject'],
                $campaign['content_html']
            );

            EmailDeliveryModel::markSent($delivery['id']);

        } catch (Exception $e) {

            EmailDeliveryModel::markFailed(
                $delivery['id'],
                $e->getMessage()
            );
        }
    }

    // Marcar como completed (protegido por user_id)
    EmailCampaignModel::markCompleted($userId, $campaignId);
}
}