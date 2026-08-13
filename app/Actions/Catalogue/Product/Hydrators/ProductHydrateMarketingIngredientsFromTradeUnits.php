<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Aug 2025 20:17:27 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product\Hydrators;

use App\Models\Catalogue\Product;
use App\Models\Goods\TradeUnit;
use App\Models\Masters\MasterAsset;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

class ProductHydrateMarketingIngredientsFromTradeUnits implements ShouldBeUnique
{
    use AsAction;

    public function getJobUniqueId(MasterAsset|Product $product): string
    {
        return $product->id;
    }

    public function handle(MasterAsset|Product $product): void
    {
        $tradeUnits = $product->tradeUnits;

        if ($tradeUnits->count() == 1) {
            $this->updateFromSingleTradeUnit($tradeUnits->first(), $product);
        } else {
            $this->updateFromMultipleTradeUnits($tradeUnits, $product);
        }
    }

    private function updateFromSingleTradeUnit(TradeUnit $tradeUnit, MasterAsset|Product $product): void
    {
        $product->updateQuietly([
            'marketing_ingredients' => $tradeUnit->marketing_ingredients ?? '',
        ]);
    }

    private function updateFromMultipleTradeUnits(Collection $tradeUnits, MasterAsset|Product $product): void
    {
        $sourceTradeUnit = $tradeUnits->first(fn (TradeUnit $tradeUnit) => filled($tradeUnit->marketing_ingredients));

        $product->updateQuietly([
            'marketing_ingredients' => $sourceTradeUnit?->marketing_ingredients ?? '',
        ]);
    }
}
