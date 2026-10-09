<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 07 Oct 2026 17:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product\Json;

use App\Actions\OrgAction;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\ActionRequest;

class GetDiscontinuingProductsInFamily extends OrgAction
{
    /**
     * Discontinued products that still have stock, cheapest first: what a clearance gift gives away.
     * Discontinuing is usually set on the SKOs, not the product, so a product whose SKOs are all discontinuing counts too.
     *
     * @return Collection<int, Product>
     */
    public function handle(ProductCategory $family): Collection
    {
        $discontinuingStates = [OrgStockStateEnum::DISCONTINUING, OrgStockStateEnum::DISCONTINUED];

        return Product::where('family_id', $family->id)
            ->whereIn('state', [ProductStateEnum::ACTIVE, ProductStateEnum::DISCONTINUING])
            ->where('is_for_sale', true)
            ->whereNull('exclusive_for_customer_id')
            ->where('is_on_demand', false)
            ->where(function (Builder $query) use ($discontinuingStates) {
                $query->where('state', ProductStateEnum::DISCONTINUING)
                    ->orWhere(function (Builder $query) use ($discontinuingStates) {
                        $query->whereHas('orgStocks')
                            ->whereDoesntHave('orgStocks', fn (Builder $orgStocks) => $orgStocks->whereNotIn('org_stocks.state', $discontinuingStates));
                    });
            })
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
