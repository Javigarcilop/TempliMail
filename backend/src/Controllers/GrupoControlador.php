<?php

declare(strict_types=1);

namespace TempliMail\Controllers;

use TempliMail\Services\GrupoServicio;

class GrupoControlador extends BaseControlador
{
    public function getAll(): void
    {
        $this->respond(fn(): array => ['data' => GrupoServicio::getAll($this->userId())]);
    }

    public function create(): void
    {
        $this->respond(
            fn(): array => ['id' => GrupoServicio::create($this->userId(), $this->body())],
            201
        );
    }

    public function update(int $id): void
    {
        $this->respond(function () use ($id): array {
            GrupoServicio::rename($this->userId(), $id, $this->body());

            return [];
        });
    }

    public function delete(int $id): void
    {
        $this->respond(function () use ($id): array {
            GrupoServicio::delete($this->userId(), $id);

            return [];
        });
    }
}
