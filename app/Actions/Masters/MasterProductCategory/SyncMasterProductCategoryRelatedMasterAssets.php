<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 05 May 2026 11:22:07 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterProductCategory;

use App\Actions\OrgAction;
use App\Models\Masters\MasterProductCategory;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class SyncMasterProductCategoryRelatedMasterAssets extends OrgAction
{
    private int $masterShopId;

    public function handle(MasterProductCategory $masterProductCategory, array $modelData): MasterProductCategory
    {
        $masterAssetIds = collect(Arr::get($modelData, 'master_asset_ids', []));

        if (data_get($masterAssetIds, '*.position')) {
            $masterAssetIds = $masterAssetIds->sortBy(fn ($masterAssetId) => data_get($masterAssetId, 'position'));
        }

        $masterAssetIds = $masterAssetIds
            ->map(function ($masterAssetId) {
                return [
                    'master_asset_id'   => data_get($masterAssetId, 'id'),
                ];
            })
            ->unique()
            ->values();

        $masterProductCategory->relatedMasterAssets()->sync($masterAssetIds->all());

        SyncShopRelatedProductsFromMasterCategory::dispatch($masterProductCategory);

        return $masterProductCategory;
    }

    public function rules(): array
    {
        return [
            'master_asset_ids'   => ['required', 'array'],
            'master_asset_ids.*.id' => ['integer', Rule::exists('master_assets', 'id')->where('master_shop_id', $this->masterShopId)],
            'master_asset_ids.*.position' => ['integer'],
        ];
    }

    public function action(MasterProductCategory $masterProductCategory, array $modelData): MasterProductCategory
    {
        $this->masterShopId = $masterProductCategory->master_shop_id;
        $this->asAction     = true;
        $this->initialisationFromGroup($masterProductCategory->group, $modelData);

        return $this->handle($masterProductCategory, $this->validatedData);
    }

    public function asController(MasterProductCategory $masterProductCategory, ActionRequest $request): MasterProductCategory
    {
        $this->masterShopId = $masterProductCategory->master_shop_id;
        $this->initialisationFromGroup($masterProductCategory->group, $request);

        return $this->handle($masterProductCategory, $this->validatedData);
    }

}
