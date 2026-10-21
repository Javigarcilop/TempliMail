<?php

declare(strict_types=1);

namespace TempliMail\Controllers;

use TempliMail\Models\PanelModelo;

class PanelControlador extends BaseControlador
{
    public function stats(): void
    {
        $this->respond(fn(): array => [
            'data' => PanelModelo::getStats($this->userId()) + PanelModelo::getDeliveryTotals($this->userId()),
        ]);
    }

    public function activity(): void
    {
        $this->respond(fn(): array => ['data' => PanelModelo::getActivity($this->userId())]);
    }

    public function summary(): void
    {
        $this->respond(fn(): array => [
            'data' => [
                'plantilla_top' => PanelModelo::getTopTemplate($this->userId()),
                'contacto_top'  => PanelModelo::getTopContact($this->userId()),
            ],
        ]);
    }
}
