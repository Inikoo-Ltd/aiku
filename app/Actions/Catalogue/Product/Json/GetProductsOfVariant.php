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

class GetProductsOfVariant extends IrisAction
{
    public function handle(Variant $variant): array
    {
        return [
            'products' =>
                // TODO HIDE allProduct, use allProductForSale
                $variant->allProduct
                // $variant->allProductForSale
                ->map(
                    fn ($product) =>
                    ProductOfVariantResource::make($product)
                        ->toArray(request())
                )
                ->values()
                ->toArray(),
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
