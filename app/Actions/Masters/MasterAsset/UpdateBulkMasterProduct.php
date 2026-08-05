<?php

/*
 * author Arya Permana - Kirin
 * created on 19-06-2025-15h-02m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Masters\MasterAsset;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithMastersEditAuthorisation;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Masters\MasterAsset;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class UpdateBulkMasterProduct extends OrgAction
{
    use WithActionUpdate;
    use WithMastersEditAuthorisation;

    public function handle(array $modelData): void
    {
        $rawProductDatas = Arr::get($modelData, 'products', []);
        foreach ($rawProductDatas as $productData) {
            $product = MasterAsset::find((int) Arr::get($productData, 'id'));
            /** Only the sent fields: a field filled back from the model re-submits its own value,
             *  and a null description would then fail the update's `required` rule. */
            UpdateMasterAsset::make()->action($product, Arr::except($productData, 'id'));
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
            'products.*.name' => ['sometimes', 'string', 'max:250'],
            'products.*.description' => ['sometimes', 'string', 'max:1500'],
            'products.*.description_extra' => ['sometimes', 'string', 'max:65500']
        ];
    }

    public function asController(ActionRequest $request): void
    {
        $this->initialisationFromGroup(group(), $request);

        $this->handle($this->validatedData);
    }
}
