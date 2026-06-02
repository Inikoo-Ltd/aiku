<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 05 May 2026 11:22:07 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\ProductCategory\RelatedChild\RelatedProducts;

use App\Actions\OrgAction;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class SyncProductCategoryRelatedProducts extends OrgAction
{
    public function handle(ProductCategory $productCategory, array $modelData): ProductCategory
    {
        $productIds = array_unique(Arr::get($modelData, 'product_ids', []));

        $relatedProducts = [];
        $position        = 0;
        foreach ($productIds as $productId) {
            $position++;
            $relatedProducts[$productId] = [
                'position' => $position
            ];
        }


        $productCategory->relatedProducts()->sync($relatedProducts);

        return $productCategory;
    }

    public function rules(): array
    {
        return [
            'product_ids'   => ['sometimes', 'array'],
            'product_ids.*' => [
                'integer',
                Rule::exists('products', 'id')->where('shop_id', $this->shop->id)
            ],
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
