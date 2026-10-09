<?php

/*
 * author Arya Permana - Kirin
 * created on 04-06-2025-16h-03m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Catalogue\Product\Json;

use App\Actions\Catalogue\Variant\LocaliseVariantData;
use App\Actions\IrisAction;
use App\Http\Resources\Web\ProductOfVariantResource;
use App\Models\Catalogue\Variant;
use Lorisleiva\Actions\ActionRequest;
use Illuminate\Http\Resources\Json\JsonResource;

class GetVariantAndProducts extends IrisAction
{
    use WithStepDiscountColumn;
    use WithVariantColumns;

    public function handle(Variant $variant): array
    {
        $data             = $variant->data;
        $visibleProducts  = collect(data_get($variant->data, 'products'))->reject(fn ($product) => isset($product['is_hide']) ? $product['is_hide'] : false);
        $products         = $variant->allProductForSale()
            ->select('products.*', $this->getStepDiscountColumn(), $this->getVariantAxisLabelColumn(), $this->getVariantTitleColumn())
            ->whereIn('id', $visibleProducts->keys())
            ->get();
        $visibleProducts  = $visibleProducts->only($products->pluck('id')->all());

        data_set($data, 'products', $visibleProducts);

        return [
            'variant_data'  => LocaliseVariantData::run($data, $variant->option_translations),
            'products'      => ProductOfVariantResource::collection(
                $products
            )->resolve(),
        ];
    }

    public function asController(Variant $variant, ActionRequest $request): array
    {
        $this->initialisation($request);

        return $this->handle($variant);
    }

    public function jsonResponse(array $data): array|JsonResource
    {
        return $data;
    }
}
