<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\Maintenance\Web;

use App\Actions\Helpers\TimeSeries\EnsureTimeSeries;
use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Models\Web\Website;

class RepairWebsiteTimeSeries
{
    use WithHydrateCommand;

    public string $commandSignature = 'website:repair-time-series {organisations?*} {--S|shop= : Shop slug}';

    public function __construct()
    {
        $this->model = Website::class;
    }

    public function handle(Website $website): void
    {
        EnsureTimeSeries::run($website);
    }
}
