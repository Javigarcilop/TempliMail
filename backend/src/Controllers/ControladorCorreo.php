<?php

declare(strict_types=1);

namespace TempliMail\Controllers;

use TempliMail\Services\ServicioCorreo;

class ControladorCorreo extends ControladorBase
{
    public function sendSingle(): void
    {
        $this->respond(function (): array {
            ServicioCorreo::sendSingle($this->userId(), $this->body());

            return [];
        });
    }

    /** POST /mail/preview */
    public function preview(): void
    {
        $this->respond(fn(): array => ['data' => ServicioCorreo::preview($this->userId(), $this->body())]);
    }

    /** POST /mail/test  (envia una copia al correo del propio usuario) */
    public function sendTest(): void
    {
        $this->respond(fn(): array => ['enviado_a' => ServicioCorreo::sendTest($this->userId(), $this->body())]);
    }

    /** Encola una campana (inmediata o programada). */
    public function sendMassive(): void
    {
        $this->respond(
            fn(): array => ServicioCorreo::sendMassive($this->userId(), $this->body()),
            201
        );
    }

    public function getHistory(): void
    {
        $this->respond(fn(): array => ['data' => ServicioCorreo::getHistory($this->userId())]);
    }

    public function getCampaignDeliveries(int $campaignId): void
    {
        $this->respond(fn(): array => [
            'data' => ServicioCorreo::getDeliveries($this->userId(), $campaignId),
        ]);
    }

    public function cancelCampaign(int $campaignId): void
    {
        $this->respond(function () use ($campaignId): array {
            ServicioCorreo::cancelCampaign($this->userId(), $campaignId);

            return [];
        });
    }

    public function retryFailed(int $campaignId): void
    {
        $this->respond(fn(): array => [
            'reencolados' => ServicioCorreo::retryFailed($this->userId(), $campaignId),
        ]);
    }

    /**
     * Procesa a mano las campanas vencidas del usuario. Normalmente lo hace el
     * worker; este endpoint sirve de respaldo si el worker no esta en marcha.
     */
    public function processScheduled(): void
    {
        $this->respond(fn(): array => [
            'procesadas' => ServicioCorreo::processDueForUser($this->userId()),
        ]);
    }
}
