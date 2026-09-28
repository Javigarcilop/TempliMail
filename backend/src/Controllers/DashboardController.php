<?php

declare(strict_types=1);

namespace TempliMail\Controllers;

use TempliMail\Models\DashboardModel;

class DashboardController extends BaseController
{
    public function stats(): void
    {
        $this->respond(fn(): array => ['data' => DashboardModel::getStats($this->userId())]);
    }

    public function summary(): void
    {
        $this->respond(fn(): array => [
            'data' => [
                'top_template' => DashboardModel::getTopTemplate($this->userId()),
                'top_contact'  => DashboardModel::getTopContact($this->userId()),
            ],
        ]);
    }
}
