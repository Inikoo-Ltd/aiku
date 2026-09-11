<?php

/*
 * Author Louis Perez
 * Created on 11-09-2026-11h-52m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Masters\MasterAsset\Hydrators;

use App\Actions\Catalogue\Product\Hydrators\ProductHydrateLabelInfoFromTradeUnits;
use App\Actions\Traits\Hydrators\WithLabelInfoFromTradeUnits;
use App\Models\Masters\MasterAsset;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class MasterAssetHydrateLabelInfoFromTradeUnits implements ShouldBeUnique
{
    use AsAction;
    use WithLabelInfoFromTradeUnits;

    public function getJobUniqueId(MasterAsset $masterAsset): string
    {
        return $masterAsset->id;
    }

    public function handle(MasterAsset $masterAsset): void
    {
        $masterAsset->updateQuietly([
            'label_info' => $this->mergeLabelInfo($masterAsset, $this->getLabelInfoFromTradeUnits($masterAsset)),
        ]);

        foreach ($masterAsset->products()->where('not_follow_master_trade_units', false)->get() as $product) {
            ProductHydrateLabelInfoFromTradeUnits::run($product);
        }
    }
}
