<?php

/*
 * author Louis Perez
 * created on 23-12-2025-09h-55m
 * github: https://github.com/louis-perez
 * copyright 2025
*/

namespace App\Actions\Masters\MasterAsset\UI;

use App\Actions\GrpAction;
use App\Actions\Traits\Authorisations\WithMastersEditAuthorisation;
use App\Actions\Masters\MasterAsset\UpdateMasterAsset;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Http\Resources\Masters\MasterProductsResource;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;
use App\Models\SysAdmin\Group;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class UpdateMasterProductsBulkEdit extends GrpAction
{
    use WithMastersEditAuthorisation;

    private Group|MasterShop|MasterProductCategory $parent;

    public function handle(Group|MasterShop|MasterProductCategory $parent, Array $modelData): Collection
    {
        $modelData = Arr::keyBy($modelData['data'], 'id');
        $masterAssets = MasterAsset::whereIn('id', data_get($modelData, '*.id'))->get()->keyBy('id');
        data_forget($modelData, '*.id');
        
        foreach ($masterAssets as $id => $masterAsset) {    
            UpdateMasterAsset::dispatch($masterAsset, $modelData[$id]);
        }

        return $masterAssets;
    }

    public function rules(): Array
    {
        $rules = [
            'data'                          =>  ['required', 'array'],
            'data.*.id'                     =>  ['required', 'numeric'],
            'data.*.name'                   =>  ['sometimes', 'string'],
            'data.*.description'            =>  ['sometimes', 'string', 'nullable'],
            'data.*.is_for_sale'            =>  ['sometimes', 'boolean'],
            'data.*.price'                  =>  ['sometimes', 'numeric'],
            'data.*.units'                  =>  ['sometimes', 'numeric'],
            'data.*.unit'                   =>  ['sometimes', 'string'],
            'data.*.gross_weight'           =>  ['sometimes', 'numeric'],
            'data.*.master_family_id'       =>  ['sometimes', 'nullable'],
        ];

        return $rules;
    }

    public function jsonResponse(Collection $masterAssets): AnonymousResourceCollection
    {
        return MasterProductsResource::collection($masterAssets);
    }

    public function asController(ActionRequest $request)
    {
        $group        = group();
        $this->parent = $group;
        $this->initialisation($group, $request);

        return $this->handle($group, $this->validatedData);
    }

}
