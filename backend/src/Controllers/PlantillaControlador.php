<?php

declare(strict_types=1);

namespace TempliMail\Controllers;

use TempliMail\Services\PlantillaServicio;

class PlantillaControlador extends BaseControlador
{
    public function getAll(): void
    {
        $this->respond(fn(): array => ['data' => PlantillaServicio::getAll($this->userId())]);
    }

    public function create(): void
    {
        $this->respond(function (): array {
            PlantillaServicio::create($this->userId(), $this->body());

            return [];
        }, 201);
    }

    public function update(int $id): void
    {
        $this->respond(function () use ($id): array {
            PlantillaServicio::update($this->userId(), $id, $this->body());

            return [];
        });
    }

    public function delete(int $id): void
    {
        $this->respond(function () use ($id): array {
            PlantillaServicio::delete($this->userId(), $id);

            return [];
        });
    }
}
