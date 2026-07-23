<?php

/*
 * author Louis Perez
 * created on 23-12-2025-09h-55m
 * github: https://github.com/louis-perez
 * copyright 2025
*/

namespace App\Actions\Masters\MasterAsset\UI;

use App\Actions\Traits\Authorisations\WithMastersEditAuthorisation;
use App\Actions\Masters\MasterAsset\UpdateMasterAsset;
use App\Actions\OrgAction;
use App\Http\Resources\Masters\MasterProductsResource;
use App\Models\Masters\MasterAsset;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class UpdateMasterProductsBulkEdit extends OrgAction
{
    use WithMastersEditAuthorisation;

    public function handle(array $modelData): Collection
    {
        $modelData = Arr::keyBy($modelData['data'], 'id');
        $masterAssets = MasterAsset::whereIn('id', data_get($modelData, '*.id'))->get()->keyBy('id');
        data_forget($modelData, '*.id');

        foreach ($masterAssets as $id => $masterAsset) {
            UpdateMasterAsset::dispatch($masterAsset, $modelData[$id]);
        }

        return $masterAssets;
    }

    public function rules(): array
    {
        return [
            'data'                          =>  ['required', 'array'],
            'data.*.id'                     =>  ['required', 'numeric'],
            'data.*.name'                   =>  ['sometimes', 'string'],
            'data.*.description'            =>  ['sometimes', 'string', 'nullable'],
            'data.*.is_for_sale'            =>  ['sometimes', 'boolean'],
            'data.*.price'                  =>  ['sometimes', 'numeric'],
            'data.*.rrp'                    =>  ['sometimes', 'numeric'],
            'data.*.units'                  =>  ['sometimes', 'numeric'],
            'data.*.unit'                   =>  ['sometimes', 'required', 'string'],
            'data.*.gross_weight'           =>  ['sometimes', 'numeric'],
            'data.*.master_family_id'       =>  ['sometimes', 'nullable'],
            // Master Prices
            'data.*.master_prices'                => ['sometimes', 'array'],
            'data.*.master_prices.*.value'        => ['sometimes', 'numeric', 'gt:0'],
            'data.*.master_prices.*.independent'  => ['sometimes', 'boolean'],
            // Master RRPs | This is per unit btw
            'data.*.master_rrps'                   => ['sometimes', 'array'],
            'data.*.master_rrps.*.value'           => ['sometimes', 'numeric', 'gt:0'],
            'data.*.master_rrps.*.independent'     => ['sometimes', 'boolean'],
        ];
    }

    public function getValidationMessages()
    {
        return [
            'data.*.name.string'                =>  __('Product Name cannot be empty'),
            'data.*.price.numeric'              =>  __('Product Price must be a number and cannot be empty'),
            'data.*.rrp.numeric'                =>  __('Product RRP must be a number and cannot be empty'),
            'data.*.units.numeric'              =>  __('Product Units must be a number and cannot be empty'),
            'data.*.unit.required'              =>  __('Product Unit cannot be empty'),
            'data.*.unit.string'                =>  __('Product Unit cannot be empty'),
            'data.*.gross_weight.numeric'       =>  __('Product Gross Weight must be a number and cannot be empty'),
        ];
    }

    public function jsonResponse(Collection $masterAssets): AnonymousResourceCollection
    {
        return MasterProductsResource::collection($masterAssets);
    }

    public function asController(ActionRequest $request)
    {
        $group        = group();
        $this->initialisationFromGroup($group, $request);

        return $this->handle($this->validatedData);
    }

}
