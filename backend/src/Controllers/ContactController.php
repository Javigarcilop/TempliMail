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
        $this->respond(
            fn(): array => ['id' => ContactService::create($this->userId(), $this->body())],
            201
        );
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

    /** PUT /contacts/{id}/groups  {"group_ids": [1, 2]} */
    public function setGroups(int $id): void
    {
        $this->respond(function () use ($id): array {
            ContactService::setGroups($this->userId(), $id, $this->body());

            return [];
        });
    }

    /** POST /contacts/import  {"contacts": [...], "group_id": 3?} */
    public function import(): void
    {
        $this->respond(
            fn(): array => ContactService::import($this->userId(), $this->body()),
            201
        );
    }
}
