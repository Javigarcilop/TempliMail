<?php

declare(strict_types=1);

namespace TempliMail\Controllers;

use TempliMail\Models\PanelModelo;

class PanelControlador extends BaseControlador
{
    public function estadisticas(): void
    {
        $this->responder(fn(): array => [
            'data' => PanelModelo::obtenerEstadisticas($this->idUsuario()) + PanelModelo::obtenerTotalesEntregas($this->idUsuario()),
        ]);
    }

    public function actividad(): void
    {
        $this->responder(fn(): array => ['data' => PanelModelo::obtenerActividad($this->idUsuario())]);
    }

    public function resumen(): void
    {
        $this->responder(fn(): array => [
            'data' => [
                'plantilla_top' => PanelModelo::obtenerPlantillaTop($this->idUsuario()),
                'contacto_top'  => PanelModelo::obtenerContactoTop($this->idUsuario()),
            ],
        ]);
    }
}
