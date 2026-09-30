<?php

declare(strict_types=1);

namespace TempliMail\Controllers;

use TempliMail\Services\ServicioGrupo;

class ControladorGrupo extends ControladorBase
{
    public function getAll(): void
    {
        $this->respond(fn(): array => ['data' => ServicioGrupo::getAll($this->userId())]);
    }

    public function create(): void
    {
        $this->respond(
            fn(): array => ['id' => ServicioGrupo::create($this->userId(), $this->body())],
            201
        );
    }

    public function update(int $id): void
    {
        $this->respond(function () use ($id): array {
            ServicioGrupo::rename($this->userId(), $id, $this->body());

            return [];
        });
    }

    public function delete(int $id): void
    {
        $this->respond(function () use ($id): array {
            ServicioGrupo::delete($this->userId(), $id);

            return [];
        });
    }
}
