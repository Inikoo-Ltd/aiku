<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Aug 2025 20:17:27 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product\Hydrators;

use App\Actions\Web\Webpage\BreakWebpageCache;
use App\Enums\Web\Webpage\WebpageStateEnum;
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

        if ($tradeUnits->isEmpty()) {
            return;
        }

        $marketingIngredients = $this->marketingIngredients($tradeUnits);

        if ($marketingIngredients == ($product->marketing_ingredients ?? '')) {
            return;
        }

        $product->updateQuietly([
            'marketing_ingredients' => $marketingIngredients,
        ]);

        if ($product instanceof Product && $product->webpage && $product->webpage->state == WebpageStateEnum::LIVE) {
            BreakWebpageCache::dispatch($product->webpage)->delay(5);
        }
    }

    public function marketingIngredients(Collection $tradeUnits): string
    {
        $sourceTradeUnit = $tradeUnits->first(fn (TradeUnit $tradeUnit) => filled($tradeUnit->marketing_ingredients));

        return $sourceTradeUnit?->marketing_ingredients ?? '';
    }
}
