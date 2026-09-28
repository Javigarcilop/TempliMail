<?php

declare(strict_types=1);

namespace TempliMail\Controllers;

use TempliMail\Services\GroupService;

class GroupController extends BaseController
{
    public function getAll(): void
    {
        $this->respond(fn(): array => ['data' => GroupService::getAll($this->userId())]);
    }

    public function create(): void
    {
        $this->respond(
            fn(): array => ['id' => GroupService::create($this->userId(), $this->body())],
            201
        );
    }

    public function update(int $id): void
    {
        $this->respond(function () use ($id): array {
            GroupService::rename($this->userId(), $id, $this->body());

            return [];
        });
    }

    public function delete(int $id): void
    {
        $this->respond(function () use ($id): array {
            GroupService::delete($this->userId(), $id);

            return [];
        });
    }
}
