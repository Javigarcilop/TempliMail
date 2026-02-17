<?php

namespace TempliMail\Services;

use TempliMail\Models\EmailCampaignModel;
use TempliMail\Models\EmailDeliveryModel;
use TempliMail\Models\TemplateModel;
use TempliMail\Utils\Mailer;
use Exception;

class MailService
{
    public static function sendSingle(array $data): void
    {
        if (!isset($data['to'], $data['subject'], $data['body'])) {
            throw new Exception('Missing required data');
        }

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
            self::processCampaign($campaignId);
        }

        return ['scheduled' => $scheduled];
    }

    public static function processCampaign(int $campaignId): void
    {
        EmailCampaignModel::setProcessing($campaignId);

        $deliveries = EmailDeliveryModel::getPendingByCampaign($campaignId);

        foreach ($deliveries as $delivery) {
            try {
                Mailer::send(
                    $delivery['email'],
                    'Subject',
                    'Body'
                );

                EmailDeliveryModel::markSent($delivery['id']);

            } catch (Exception $e) {

                EmailDeliveryModel::markFailed(
                    $delivery['id'],
                    $e->getMessage()
                );
            }
        }

        EmailCampaignModel::markCompleted($campaignId);
    }
}
