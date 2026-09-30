<?php

declare(strict_types=1);

namespace TempliMail\Controllers;

use TempliMail\Exceptions\ExcepcionApi;
use TempliMail\Services\ServicioSubidaPlantilla;

class ControladorSubidaPlantilla extends ControladorBase
{
    public function handleUpload(): void
    {
        $this->respond(function (): array {
            if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
                throw new ExcepcionApi('No se recibió ningún archivo.');
            }

            return ['html' => ServicioSubidaPlantilla::process($_FILES['file'])];
        });
    }
}
