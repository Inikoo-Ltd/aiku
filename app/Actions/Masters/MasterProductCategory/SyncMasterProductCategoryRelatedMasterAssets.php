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
        $masterAssetIds = array_unique(Arr::get($modelData, 'master_asset_ids', []));

        $relatedMasterAssets = [];
        $position = 0;
        foreach ($masterAssetIds as $masterAssetId) {
            $position++;
            $relatedMasterAssets[$masterAssetId] = [
                'position' => $position
            ];
        }

        $masterProductCategory->relatedMasterAssets()->sync($relatedMasterAssets);

        SyncShopRelatedProductsFromMasterCategory::dispatch($masterProductCategory);


        return $masterProductCategory;
    }

    public function rules(): array
    {

        return [
            'master_asset_ids' => ['sometimes', 'array'],
            'master_asset_ids.*' => ['integer', Rule::exists('master_assets', 'id')->where('master_shop_id', $this->masterShopId)],
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
