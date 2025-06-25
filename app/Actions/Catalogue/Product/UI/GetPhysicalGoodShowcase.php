<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 24 May 2023 20:59:08 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product\UI;

use App\Actions\Traits\WithDashboard;
use App\Http\Resources\Catalogue\ProductResource;
use App\Models\Catalogue\Product;
use Lorisleiva\Actions\Concerns\AsObject;

class GetPhysicalGoodShowcase
{
    use AsObject;
    use WithDashboard;

    public function handle(Product $product): array
    {
        $sales = $product->asset->salesIntervals;
        $stats = $this->getAllIntervalPercentage($sales, 'sales');
        return [
            'uploadImageRoute' => [
                'name'       => 'grp.models.product.images.store',
                'parameters' => [
                    'product'      => $product->id
                ]
            ],

            'deleteImageRoute' => [
                'name'       => 'grp.models.org.product.images.delete',
                'parameters' => [
                    'organisation' => $product->organisation_id,
                    'product'      => $product->id
                ]
            ],
            'product' => ProductResource::make($product),
            'stats' => $stats,
        ];
    }
}
