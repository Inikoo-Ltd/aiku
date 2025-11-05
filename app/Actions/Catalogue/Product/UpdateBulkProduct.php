<?php

/*
 * author Arya Permana - Kirin
 * created on 19-06-2025-15h-02m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Catalogue\Product;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueEditAuthorisation;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class UpdateBulkProduct extends OrgAction
{
    use WithActionUpdate;
    use WithCatalogueEditAuthorisation;

    public function handle(array $modelData): void
    {
        $rawProductDatas = Arr::get($modelData, 'products', []);
        foreach ($rawProductDatas as $productData) {
            $product = Product::find((int) Arr::get($productData, 'id'));
            UpdateProduct::make()->action($product, [
                'rrp'  => Arr::get($productData, 'rrp', $product->rrp),
                'price' => Arr::get($productData, 'price', $product->price),
                'unit' => Arr::get($productData, 'unit', $product->unit),
            ]);
        }
    }


    public function rules(): array
    {
        return [
            'products' => ['required', 'array'],
            'products.*.id' => ['required'],
            'products.*.rrp' => ['sometimes', 'numeric'],
            'products.*.price' => ['sometimes', 'numeric'],
            'products.*.unit' => ['sometimes', 'string'],
        ];
    }

    public function asController(Shop $shop, ActionRequest $request): void
    {
        $this->initialisationFromShop($shop, $request);

        $this->handle($this->validatedData);
    }
}
