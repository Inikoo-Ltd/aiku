<?php

/*
 * Author Louis Perez
 * Created on 14-08-2026-15h-36m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Catalogue\Product\Json;

use App\Actions\IrisAction;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Http\Resources\Web\ProductOfVariantResource;
use App\Models\Catalogue\Product;
use Lorisleiva\Actions\ActionRequest;
use Illuminate\Http\Resources\Json\JsonResource;

class GetProductSiblings extends IrisAction
{
    public function handle(Product $product): array
    {
        $family = $product->family;

        $products = Product::where('products.family_id', $family->id)
                ->select([
                    'products.*',
                    'webpages.canonical_url',
                ])
                ->leftJoin('webpages', fn ($join) => $join->on('webpages.model_id', 'products.id')->where('webpages.model_type', class_basename(Product::class)))
                ->where('products.is_for_sale', true)
                ->where('products.state', '!=', ProductStateEnum::DISCONTINUED->value)
                ->orderBy('products.code')
                ->get();

        return [
            'products' =>
                $products
                ->map(
                    fn ($product) =>
                    ProductOfVariantResource::make($product)
                        ->toArray(request())
                )
                ->values()
                ->toArray(),
        ];
    }

    public function asController(Product $product, ActionRequest $request): array
    {
        $this->initialisation($request);

        return $this->handle($product);
    }

    public function jsonResponse(array $data): array|JsonResource
    {
        return $data;
    }
}
