<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\Maintenance\Catalogue;

use App\Actions\Helpers\TimeSeries\EnsureTimeSeries;
use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Models\Catalogue\Collection;

class RepairCollectionTimeSeries
{
    use WithHydrateCommand;

    public string $commandSignature = 'repair:collection-time-series {organisations?*} {--S|shop= : Shop slug}';

    public function __construct()
    {
        $this->model = Collection::class;
    }

    public function handle(Collection $collection): void
    {
        EnsureTimeSeries::run($collection);
    }
}
