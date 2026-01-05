<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\Masters\MasterAsset;

use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Models\Masters\MasterAsset;

class SeedMasterAssetTimeSeries
{
    use WithHydrateCommand;


    public string $commandSignature = 'seed:master-asset-time-series {--frequency=all : The frequency for time series (all, daily, weekly, monthly, quarterly, yearly)}';

    public function __construct()
    {
        $this->model = MasterAsset::class;
    }

    public function handle(MasterAsset $masterAsset): void
    {
        $from = $masterAsset->created_at->toDate();
        if ($masterAsset->status) {
            $to = now()->toDate();
        } elseif ($masterAsset->discontinued_at) {
            $to = $masterAsset->discontinued_at->toDate();
        } else {
            $to = now()->toDate();
        }


    }

}
