<?php

declare(strict_types=1);

namespace TempliMail\Controllers;

use TempliMail\Services\MailService;

class MailController extends BaseController
{
    public function sendSingle(): void
    {
        $this->respond(function (): array {
            MailService::sendSingle($this->userId(), $this->body());

            return [];
        });
    }

    /** POST /mail/preview */
    public function preview(): void
    {
        $this->respond(fn(): array => ['data' => MailService::preview($this->userId(), $this->body())]);
    }

    /** POST /mail/test  (envia una copia al email del propio usuario) */
    public function sendTest(): void
    {
        $this->respond(fn(): array => ['sent_to' => MailService::sendTest($this->userId(), $this->body())]);
    }

    /** Encola una campana (inmediata o programada). */
    public function sendMassive(): void
    {
        $this->respond(
            fn(): array => MailService::sendMassive($this->userId(), $this->body()),
            201
        );
    }

    public function getHistory(): void
    {
        $this->respond(fn(): array => ['data' => MailService::getHistory($this->userId())]);
    }

    public function getCampaignDeliveries(int $campaignId): void
    {
        $this->respond(fn(): array => [
            'data' => MailService::getDeliveries($this->userId(), $campaignId),
        ]);
    }

    public function cancelCampaign(int $campaignId): void
    {
        $this->respond(function () use ($campaignId): array {
            MailService::cancelCampaign($this->userId(), $campaignId);

            return [];
        });
    }

    public function retryFailed(int $campaignId): void
    {
        $this->respond(fn(): array => [
            'requeued' => MailService::retryFailed($this->userId(), $campaignId),
        ]);
    }

    /**
     * Procesa a mano las campanas vencidas del usuario. Normalmente lo hace el
     * worker; este endpoint sirve de respaldo si el worker no esta en marcha.
     */
    public function processScheduled(): void
    {
        $this->respond(fn(): array => [
            'processed' => MailService::processDueForUser($this->userId()),
        ]);
    }
}
