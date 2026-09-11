<?php

/*
 * Author Louis Perez
 * Created on 11-09-2026-11h-52m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Catalogue\Product\Hydrators;

use App\Actions\Traits\Hydrators\WithLabelInfoFromTradeUnits;
use App\Models\Catalogue\Product;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class ProductHydrateLabelInfoFromTradeUnits implements ShouldBeUnique
{
    use AsAction;
    use WithLabelInfoFromTradeUnits;

    public function getJobUniqueId(Product $product): string
    {
        return $product->id;
    }

    public function handle(Product $product): void
    {
        $masterProduct = $product->masterProduct;

        $labelInfo = $masterProduct && !$product->not_follow_master_trade_units
            ? $this->getLabelInfoFromMaster($masterProduct)
            : $this->getLabelInfoFromTradeUnits($product);

        $product->updateQuietly([
            'label_info' => $this->mergeLabelInfo($product, $labelInfo),
        ]);
    }
}
