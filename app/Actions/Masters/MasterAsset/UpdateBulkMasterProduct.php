<?php

/*
 * author Arya Permana - Kirin
 * created on 19-06-2025-15h-02m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Masters\MasterAsset;

use App\Actions\GrpAction;
use App\Actions\Traits\Authorisations\WithMastersEditAuthorisation;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Masters\MasterAsset;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class UpdateBulkMasterProduct extends GrpAction
{
    use WithActionUpdate;
    use WithMastersEditAuthorisation;

    public function handle(array $modelData): void
    {
        $rawProductDatas = Arr::get($modelData, 'products', []);
        foreach ($rawProductDatas as $productData) {
            $product = MasterAsset::find((int) Arr::get($productData, 'id'));
            UpdateMasterAsset::make()->action($product, [
                'rrp'  => Arr::get($productData, 'rrp', $product->rrp),
                'price'=> Arr::get($productData, 'price', $product->price),
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

    public function asController(ActionRequest $request): void
    {
        $this->initialisation(group(), $request);

        $this->handle($this->validatedData);
    }
}
