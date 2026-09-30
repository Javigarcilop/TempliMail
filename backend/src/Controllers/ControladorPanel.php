<?php

declare(strict_types=1);

namespace TempliMail\Controllers;

use TempliMail\Models\ModeloPanel;

class ControladorPanel extends ControladorBase
{
    public function stats(): void
    {
        $this->respond(fn(): array => [
            'data' => ModeloPanel::getStats($this->userId()) + ModeloPanel::getDeliveryTotals($this->userId()),
        ]);
    }

    public function activity(): void
    {
        $this->respond(fn(): array => ['data' => ModeloPanel::getActivity($this->userId())]);
    }

    public function summary(): void
    {
        $this->respond(fn(): array => [
            'data' => [
                'plantilla_top' => ModeloPanel::getTopTemplate($this->userId()),
                'contacto_top'  => ModeloPanel::getTopContact($this->userId()),
            ],
        ]);
    }
}
