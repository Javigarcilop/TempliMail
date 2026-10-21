<?php

declare(strict_types=1);

namespace TempliMail\Controllers;

use TempliMail\Services\GrupoServicio;

class GrupoControlador extends BaseControlador
{
    public function obtenerTodos(): void
    {
        $this->responder(fn(): array => ['data' => GrupoServicio::obtenerTodos($this->idUsuario())]);
    }

    public function crear(): void
    {
        $this->responder(
            fn(): array => ['id' => GrupoServicio::crear($this->idUsuario(), $this->cuerpoPeticion())],
            201
        );
    }

    public function actualizar(int $id): void
    {
        $this->responder(function () use ($id): array {
            GrupoServicio::renombrar($this->idUsuario(), $id, $this->cuerpoPeticion());

            return [];
        });
    }

    public function eliminar(int $id): void
    {
        $this->responder(function () use ($id): array {
            GrupoServicio::eliminar($this->idUsuario(), $id);

            return [];
        });
    }
}
