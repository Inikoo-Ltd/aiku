<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 07 Apr 2025 13:23:25 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Catalogue\Product\ProductStatusEnum;
use App\Models\Catalogue\Product;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class ProductHydrateForSale implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(Product $product): string
    {
        return $product->id;
    }

    public function handle(Product $product): Product
    {
        $isForSale = in_array($product->status, [
                ProductStatusEnum::FOR_SALE,
                ProductStatusEnum::OUT_OF_STOCK
            ])
            && in_array($product->state, [
                ProductStateEnum::ACTIVE,
                ProductStateEnum::DISCONTINUING
            ]);

        $product->update(['is_for_sale' => $isForSale]);
        return $product;
    }

}
