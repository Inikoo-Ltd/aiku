<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 07 Oct 2026 17:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product\Json;

use App\Actions\OrgAction;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\ActionRequest;

class GetDiscontinuingProductsInFamily extends OrgAction
{
    /**
     * Discontinued products that still have stock, cheapest first: what a clearance gift gives away.
     *
     * @return Collection<int, Product>
     */
    public function handle(ProductCategory $family): Collection
    {
        return Product::where('family_id', $family->id)
            ->where('state', ProductStateEnum::DISCONTINUING)
            ->where('available_quantity', '>', 0)
            ->orderBy('price')
            ->orderBy('id')
            ->get();
    }

    public function jsonResponse(Collection $products): array
    {
        return $products->map(fn (Product $product) => [
            'id'                 => $product->id,
            'code'               => $product->code,
            'name'               => $product->name,
            'label'              => $product->code.' '.$product->name.' ('.(int)$product->available_quantity.')',
            'price'              => $product->price,
            'available_quantity' => (int)$product->available_quantity,
        ])->all();
    }

    public function asController(ProductCategory $productCategory, ActionRequest $request): Collection
    {
        $this->initialisationFromShop($productCategory->shop, $request);

        return $this->handle($productCategory);
    }
}
