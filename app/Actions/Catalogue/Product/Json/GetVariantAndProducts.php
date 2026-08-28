<?php

/*
 * author Arya Permana - Kirin
 * created on 04-06-2025-16h-03m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Catalogue\Product\Json;

use App\Actions\IrisAction;
use App\Http\Resources\Web\ProductOfVariantResource;
use App\Models\Catalogue\Variant;
use Lorisleiva\Actions\ActionRequest;
use Illuminate\Http\Resources\Json\JsonResource;

class GetVariantAndProducts extends IrisAction
{
    public function handle(Variant $variant): array
    {
        $variant->loadMissing('allProduct');

        $data = $variant->data;
        $excludedProducts = collect(data_get($variant->data, 'products'))->reject(fn ($product) => isset($product['is_hide']) ? $product['is_hide'] : false);
        $products   = $variant->allProduct()->whereIn('id', $excludedProducts->keys())->get();

        data_set($data, 'products', $excludedProducts);

        return [
            'variant_data'  => $data,
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
