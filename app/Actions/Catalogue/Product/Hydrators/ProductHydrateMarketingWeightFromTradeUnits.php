<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 09 Sept 2024 17:15:49 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product\Hydrators;

use App\Actions\Traits\Hydrators\WithWeightFromTradeUnits;
use App\Models\Catalogue\Product;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class ProductHydrateMarketingWeightFromTradeUnits implements ShouldBeUnique
{
    use AsAction;
    use WithWeightFromTradeUnits;

    public function getJobUniqueId(Product $product): string
    {
        return $product->id;
    }

    public function handle(Product $product): void
    {
        if (!$product->is_single_trade_unit) {
            $this->hydrateFromSeveralTradeUnits($product);

            return;
        }

        $tradeUnit       = $product->tradeUnits()->whereNotNull('marketing_weight')->orderBy('marketing_weight', 'desc')->first();
        $marketingWeight = $tradeUnit?->marketing_weight;


        $product->updateQuietly(
            [
                'marketing_weight' => $marketingWeight,
            ]
        );
    }

    /**
     * Products made of several trade units used to be skipped entirely, which left every
     * multi-component bundle with no marketing weight and so no weight at all on the sales
     * channels that read it.
     *
     * Only an empty weight is filled in: thousands of multi trade unit products carry a weight
     * set elsewhere, and this hydrator has never owned those values.
     */
    private function hydrateFromSeveralTradeUnits(Product $product): void
    {
        if ($product->marketing_weight !== null) {
            return;
        }

        $marketingWeight = $this->getMarketingWeightFromTradeUnits($product);

        if ($marketingWeight === null) {
            return;
        }

        $product->updateQuietly(
            [
                'marketing_weight' => $marketingWeight,
            ]
        );
    }


}
