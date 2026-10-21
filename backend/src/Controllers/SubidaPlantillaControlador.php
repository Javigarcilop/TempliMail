<?php

declare(strict_types=1);

namespace TempliMail\Controllers;

use TempliMail\Exceptions\ApiExcepcion;
use TempliMail\Services\SubidaPlantillaServicio;

class SubidaPlantillaControlador extends BaseControlador
{
    public function gestionarSubida(): void
    {
        $this->responder(function (): array {
            if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
                throw new ApiExcepcion('No se recibió ningún archivo.');
            }

            return ['html' => SubidaPlantillaServicio::procesar($_FILES['file'])];
        });
    }
}
