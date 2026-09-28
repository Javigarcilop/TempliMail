<?php

declare(strict_types=1);

namespace TempliMail\Controllers;

use TempliMail\Services\TemplateService;

class TemplateController extends BaseController
{
    public function getAll(): void
    {
        $this->respond(fn(): array => ['data' => TemplateService::getAll($this->userId())]);
    }

    public function create(): void
    {
        $this->respond(function (): array {
            TemplateService::create($this->userId(), $this->body());

            return [];
        }, 201);
    }

    public function update(int $id): void
    {
        $this->respond(function () use ($id): array {
            TemplateService::update($this->userId(), $id, $this->body());

            return [];
        });
    }

    public function delete(int $id): void
    {
        $this->respond(function () use ($id): array {
            TemplateService::delete($this->userId(), $id);

            return [];
        });
    }
}
