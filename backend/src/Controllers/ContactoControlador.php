<?php

declare(strict_types=1);

namespace TempliMail\Controllers;

use TempliMail\Services\ContactoServicio;

class ContactoControlador extends BaseControlador
{
    public function obtenerTodos(): void
    {
        $this->responder(fn(): array => ['data' => ContactoServicio::obtenerTodos($this->idUsuario())]);
    }

    public function crear(): void
    {
        $this->responder(
            fn(): array => ['id' => ContactoServicio::crear($this->idUsuario(), $this->cuerpoPeticion())],
            201
        );
    }

    public function actualizar(int $id): void
    {
        $this->responder(function () use ($id): array {
            ContactoServicio::actualizar($this->idUsuario(), $id, $this->cuerpoPeticion());

            return [];
        });
    }

    public function eliminar(int $id): void
    {
        $this->responder(function () use ($id): array {
            ContactoServicio::eliminar($this->idUsuario(), $id);

            return [];
        });
    }

    /** PUT /contactos/{id}/subscription  {"subscribed": true|false} */
    public function establecerSuscripcion(int $id): void
    {
        $this->responder(function () use ($id): array {
            $subscribed = (bool) ($this->cuerpoPeticion()['subscribed'] ?? false);

            ContactoServicio::establecerSuscripcion($this->idUsuario(), $id, $subscribed);

            return [];
        });
    }

    /** PUT /contactos/{id}/groups  {"ids_grupo": [1, 2]} */
    public function establecerGrupos(int $id): void
    {
        $this->responder(function () use ($id): array {
            ContactoServicio::establecerGrupos($this->idUsuario(), $id, $this->cuerpoPeticion());

            return [];
        });
    }

    /** POST /contactos/import  {"contactos": [...], "grupo_id": 3?} */
    public function importar(): void
    {
        $this->responder(
            fn(): array => ContactoServicio::importar($this->idUsuario(), $this->cuerpoPeticion()),
            201
        );
    }
}
