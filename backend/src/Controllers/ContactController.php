<?php

declare(strict_types=1);

namespace TempliMail\Controllers;

use TempliMail\Services\ContactService;

class ContactController extends BaseController
{
    public function getAll(): void
    {
        $this->respond(fn(): array => ['data' => ContactService::getAll($this->userId())]);
    }

    public function create(): void
    {
        $this->respond(function (): array {
            ContactService::create($this->userId(), $this->body());

            return [];
        }, 201);
    }

    public function update(int $id): void
    {
        $this->respond(function () use ($id): array {
            ContactService::update($this->userId(), $id, $this->body());

            return [];
        });
    }

    public function delete(int $id): void
    {
        $this->respond(function () use ($id): array {
            ContactService::delete($this->userId(), $id);

            return [];
        });
    }

    /** PUT /contacts/{id}/subscription  {"subscribed": true|false} */
    public function setSubscription(int $id): void
    {
        $this->respond(function () use ($id): array {
            $subscribed = (bool) ($this->body()['subscribed'] ?? false);

            ContactService::setSubscribed($this->userId(), $id, $subscribed);

            return [];
        });
    }
}
