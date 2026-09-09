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

        if ($tradeUnits->count() != 1) {
            return;
        }

        $tradeUnit = $tradeUnits->first();

        if ($tradeUnit->marketing_dimensions) {
            $product->updateQuietly([
                'marketing_dimensions' => $tradeUnit->marketing_dimensions,
            ]);
        }
    }

    /**
     * The dimensions of one component are not the dimensions of the bundle that contains it,
     * so a product built from several trade units keeps whatever was set by hand and is
     * never given a component's measurements.
     */
    public function cameFromOneOfSeveralTradeUnits(Product $product): bool
    {
        if ($product->tradeUnits->count() < 2 || blank($product->marketing_dimensions)) {
            return false;
        }

        foreach ($product->tradeUnits as $tradeUnit) {
            if ($tradeUnit->marketing_dimensions == $product->marketing_dimensions) {
                return true;
            }
        }

        return false;
    }
}
