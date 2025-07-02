<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 14 Jun 2025 17:52:55 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product;

use App\Actions\Catalogue\Product\Hydrators\ProductHydrateBarcodeFromTradeUnit;
use App\Actions\Catalogue\Product\Hydrators\ProductHydrateGrossWeightFromTradeUnits;
use App\Actions\Goods\TradeUnit\Hydrators\TradeUnitsHydrateCustomerExclusiveProducts;
use App\Actions\Goods\TradeUnit\Hydrators\TradeUnitsHydrateProducts;
use App\Models\Catalogue\Product;
use App\Models\Goods\TradeUnit;
use Lorisleiva\Actions\Concerns\AsAction;

class AttachTradeUnitToProduct
{
    use AsAction;

    public function handle(Product $product, TradeUnit $tradeUnit, array $modelData): Product
    {
        $product->tradeUnits()->attach(
            $tradeUnit,
            $modelData
        );

        ProductHydrateGrossWeightFromTradeUnits::dispatch($product);
        ProductHydrateBarcodeFromTradeUnit::dispatch($product);

        if ($product->exclusive_for_customer_id) {
            TradeUnitsHydrateCustomerExclusiveProducts::dispatch($tradeUnit);
        } else {
            TradeUnitsHydrateProducts::dispatch($tradeUnit);
        }


        return $product;
    }
}
