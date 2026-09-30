<?php

declare(strict_types=1);

namespace TempliMail\Controllers;

use TempliMail\Services\ServicioPlantilla;

class ControladorPlantilla extends ControladorBase
{
    public function getAll(): void
    {
        $this->respond(fn(): array => ['data' => ServicioPlantilla::getAll($this->userId())]);
    }

    public function create(): void
    {
        $this->respond(function (): array {
            ServicioPlantilla::create($this->userId(), $this->body());

            return [];
        }, 201);
    }

    public function update(int $id): void
    {
        $this->respond(function () use ($id): array {
            ServicioPlantilla::update($this->userId(), $id, $this->body());

            return [];
        });
    }

    public function delete(int $id): void
    {
        $this->respond(function () use ($id): array {
            ServicioPlantilla::delete($this->userId(), $id);

            return [];
        });
    }
}
