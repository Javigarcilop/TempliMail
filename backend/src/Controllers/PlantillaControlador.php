<?php

declare(strict_types=1);

namespace TempliMail\Controllers;

use TempliMail\Services\PlantillaServicio;

class PlantillaControlador extends BaseControlador
{
    public function obtenerTodos(): void
    {
        $this->responder(fn(): array => ['data' => PlantillaServicio::obtenerTodos($this->idUsuario())]);
    }

    public function crear(): void
    {
        $this->responder(function (): array {
            PlantillaServicio::crear($this->idUsuario(), $this->cuerpoPeticion());

            return [];
        }, 201);
    }

    public function actualizar(int $id): void
    {
        $this->responder(function () use ($id): array {
            PlantillaServicio::actualizar($this->idUsuario(), $id, $this->cuerpoPeticion());

            return [];
        });
    }

    public function eliminar(int $id): void
    {
        $this->responder(function () use ($id): array {
            PlantillaServicio::eliminar($this->idUsuario(), $id);

            return [];
        });
    }
}
