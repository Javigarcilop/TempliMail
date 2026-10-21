<?php

declare(strict_types=1);

namespace TempliMail\Controllers;

use TempliMail\Services\CorreoServicio;

class CorreoControlador extends BaseControlador
{
    public function enviarIndividual(): void
    {
        $this->responder(function (): array {
            CorreoServicio::enviarIndividual($this->idUsuario(), $this->cuerpoPeticion());

            return [];
        });
    }

    /** POST /mail/preview */
    public function previsualizar(): void
    {
        $this->responder(fn(): array => ['data' => CorreoServicio::previsualizar($this->idUsuario(), $this->cuerpoPeticion())]);
    }

    /** POST /mail/test  (envia una copia al correo del propio usuario) */
    public function enviarPrueba(): void
    {
        $this->responder(fn(): array => ['enviado_a' => CorreoServicio::enviarPrueba($this->idUsuario(), $this->cuerpoPeticion())]);
    }

    /** Encola una campana (inmediata o programada). */
    public function enviarMasivo(): void
    {
        $this->responder(
            fn(): array => CorreoServicio::enviarMasivo($this->idUsuario(), $this->cuerpoPeticion()),
            201
        );
    }

    public function obtenerHistorial(): void
    {
        $this->responder(fn(): array => ['data' => CorreoServicio::obtenerHistorial($this->idUsuario())]);
    }

    public function obtenerEntregasCampana(int $campaignId): void
    {
        $this->responder(fn(): array => [
            'data' => CorreoServicio::obtenerEntregas($this->idUsuario(), $campaignId),
        ]);
    }

    public function cancelarCampana(int $campaignId): void
    {
        $this->responder(function () use ($campaignId): array {
            CorreoServicio::cancelarCampana($this->idUsuario(), $campaignId);

            return [];
        });
    }

    public function reintentarFallidas(int $campaignId): void
    {
        $this->responder(fn(): array => [
            'reencolados' => CorreoServicio::reintentarFallidas($this->idUsuario(), $campaignId),
        ]);
    }

    /**
     * Procesa a mano las campanas vencidas del usuario. Normalmente lo hace el
     * worker; este endpoint sirve de respaldo si el worker no esta en marcha.
     */
    public function procesarProgramadas(): void
    {
        $this->responder(fn(): array => [
            'procesadas' => CorreoServicio::procesarPendientesUsuario($this->idUsuario()),
        ]);
    }
}
