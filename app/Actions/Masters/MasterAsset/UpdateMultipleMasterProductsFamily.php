<?php
/*
 * author Louis Perez
 * created on 25-11-2025-16h-41m
 * github: https://github.com/louis-perez
 * copyright 2025
*/

namespace App\Actions\Masters\MasterAsset;

use App\Actions\GrpAction;
use App\Actions\Masters\MasterProductCategory\WithMasterProductCategoryAction;
use App\Actions\Masters\MasterAsset\UpdateMasterAsset;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterAsset;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateMultipleMasterProductsFamily extends GrpAction
{
    use WithActionUpdate;
    use WithMasterProductCategoryAction;

    public function handle(MasterProductCategory $masterFamily, array $modelData): void
    {
        foreach ($modelData['master_assets'] as $masterAssetId) {
            $masterAsset = MasterAsset::find($masterAssetId);
            UpdateMasterAsset::make()->action($masterAsset, [
                'master_family_id' => $masterFamily->id
            ]);
        }
    }


    public function rules(): array
    {
        return [
            'master_assets' => ['required', 'array'],
            'master_assets.*' => [
                'required',
                Rule::exists('master_assets', 'id')->where(function ($query) {
                    $query->where('master_shop_id', $this->masterFamily->master_shop_id);
                }),
            ],
        ];
    }

    public function asController(MasterProductCategory $masterFamily, ActionRequest $request): void
    {
        $this->masterProductCategory = $masterFamily;
        $this->initialisation($masterFamily->group, $request);

        $this->handle($masterFamily, $this->validatedData);
    }
}
