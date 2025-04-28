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

class ProductHydrateGrossWeightFromTradeUnits implements ShouldBeUnique
{
    use AsAction;
    use WithWeightFromTradeUnits;

    public function getJobUniqueId(Product $product): string
    {
        return $product->id;
    }

    public function handle(Product $product): void
    {
        $product->updateQuietly(
            [
                'gross_weight' => $this->getWeightFromTradeUnits($product),
            ]
        );
    }


}
