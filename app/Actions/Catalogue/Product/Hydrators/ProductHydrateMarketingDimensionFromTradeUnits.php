<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 07 Jul 2025 21:01:58 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product\Hydrators;

use App\Models\Catalogue\Product;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class ProductHydrateMarketingDimensionFromTradeUnits implements ShouldBeUnique
{
    use AsAction;

    public function getJobUniqueId(Product $product): string
    {
        return $product->id;
    }

    public function handle(Product $product): void
    {
        $tradeUnits = $product->tradeUnits;

        if ($tradeUnits->count() == 1) {
            $this->updateFromSingleTradeUnit($tradeUnits->first(), $product);
        } else {
            $this->updateFromMultipleTradeUnits($tradeUnits, $product);
        }
    }

    private function updateFromSingleTradeUnit($tradeUnit, Product $product): void
    {
        if ($tradeUnit->marketing_dimensions) {
            $product->updateQuietly([
                'marketing_dimensions' => $tradeUnit->marketing_dimensions,
            ]);
        }
    }

    private function updateFromMultipleTradeUnits($tradeUnits, Product $product): void
    {
        // For multiple trade units, we'll use the dimensions from the first trade unit that has them
        foreach ($tradeUnits as $tradeUnit) {
            if ($tradeUnit->marketing_dimensions) {
                $product->updateQuietly([
                    'marketing_dimensions' => $tradeUnit->marketing_dimensions,
                ]);
                return;
            }
        }
    }
}
