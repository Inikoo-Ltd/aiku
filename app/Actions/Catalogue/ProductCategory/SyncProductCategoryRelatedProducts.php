<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 05 May 2026 11:22:07 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\ProductCategory;

use App\Actions\OrgAction;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class SyncProductCategoryRelatedProducts extends OrgAction
{
    public function handle(ProductCategory $productCategory, array $modelData): ProductCategory
    {
        $productIds = collect(Arr::get($modelData, 'product_ids', []));

        $productIds = $productIds
            ->mapWithKeys(function ($productId) {
                return [
                    data_get($productId, 'id') => [
                        'product_id' => data_get($productId, 'id'),
                        'position'   => data_get($productId, 'position'),
                    ]
                ];
            })
            ->unique();

        $productCategory->relatedProducts()->sync($productIds->all());

        foreach ($productCategory->relatedProducts as $product) {
            $key = $product->pivot->id;
            DB::table('product_category_has_related_products')
                ->where('id', $key)
                ->update(['position' => $productIds->get($product->id)['position']]);
        }

        return $productCategory;
    }

    public function rules(): array
    {
        return [
            'product_ids'            => ['sometimes', 'array'],
            'product_ids.*.id'       => ['integer', Rule::exists('products', 'id')->where('shop_id', $this->shop->id)],
            'product_ids.*.position' => ['integer'],
        ];
    }

    public function action(ProductCategory $productCategory, array $modelData): ProductCategory
    {
        $this->asAction = true;
        $this->initialisationFromShop($productCategory->shop, $modelData);

        return $this->handle($productCategory, $this->validatedData);
    }

    public function asController(ProductCategory $productCategory, ActionRequest $request): ProductCategory
    {
        $this->initialisationFromShop($productCategory->shop, $request);

        return $this->handle($productCategory, $this->validatedData);
    }
}
