<?php

declare(strict_types=1);

namespace TempliMail\Controllers;

use TempliMail\Services\ContactoServicio;

class ContactoControlador extends BaseControlador
{
    public function getAll(): void
    {
        $this->respond(fn(): array => ['data' => ContactoServicio::getAll($this->userId())]);
    }

    public function create(): void
    {
        $this->respond(
            fn(): array => ['id' => ContactoServicio::create($this->userId(), $this->body())],
            201
        );
    }

    public function update(int $id): void
    {
        $this->respond(function () use ($id): array {
            ContactoServicio::update($this->userId(), $id, $this->body());

            return [];
        });
    }

    public function delete(int $id): void
    {
        $this->respond(function () use ($id): array {
            ContactoServicio::delete($this->userId(), $id);

            return [];
        });
    }

    /** PUT /contactos/{id}/subscription  {"subscribed": true|false} */
    public function setSubscription(int $id): void
    {
        $this->respond(function () use ($id): array {
            $subscribed = (bool) ($this->body()['subscribed'] ?? false);

            ContactoServicio::setSubscribed($this->userId(), $id, $subscribed);

            return [];
        });
    }

    /** PUT /contactos/{id}/groups  {"ids_grupo": [1, 2]} */
    public function setGroups(int $id): void
    {
        $this->respond(function () use ($id): array {
            ContactoServicio::setGroups($this->userId(), $id, $this->body());

            return [];
        });
    }

    /** POST /contactos/import  {"contactos": [...], "grupo_id": 3?} */
    public function import(): void
    {
        $this->respond(
            fn(): array => ContactoServicio::import($this->userId(), $this->body()),
            201
        );
    }
}
