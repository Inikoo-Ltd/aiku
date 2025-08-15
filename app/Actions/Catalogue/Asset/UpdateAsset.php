<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 07 Aug 2025 22:28:39 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Asset;

use App\Actions\Masters\MasterAsset\Hydrators\MasterAssetHydrateAssets;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Catalogue\Asset;
use Illuminate\Support\Arr;

class UpdateAsset extends OrgAction
{
    use WithActionUpdate;

    public function handle(Asset $asset, array $modelData = []): Asset
    {

        $originalMasterAsset = null;
        if (Arr::has($modelData, 'master_asset_id')) {
            $originalMasterAsset = $asset->masterAsset;
        }

        $asset = $this->update($asset, $modelData);

        $changes = $asset->getChanges();



        if (Arr::hasAny($changes, ['state', 'master_asset_id'])) {
            if ($asset->masterAsset) {
                MasterAssetHydrateAssets::run($asset->masterAsset);
            }
            if ($originalMasterAsset != null && $originalMasterAsset->id != $asset->master_asset_id) {
                MasterAssetHydrateAssets::run($originalMasterAsset);
            }
        }


        return $asset;
    }

    public function rules(): array
    {
        return [
            'master_asset_id' => ['nullable','integer'],
        ];
    }

    public function action(Asset $asset, array $modelData): Asset
    {
        $this->asAction = true;
        $this->initialisationFromShop($asset->shop, $modelData);
        return $this->handle($asset, $this->validatedData);
    }


}
