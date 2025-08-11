<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 01 Jun 2024 17:49:30 Central European Summer Time, Mijas Costa, Spain
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Asset;

use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateAssets;
use App\Actions\Masters\MasterAsset\Hydrators\MasterAssetHydrateAssets;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateAssets;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateAssets;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Billables\Rental\RentalStateEnum;
use App\Enums\Billables\Service\ServiceStateEnum;
use App\Enums\Billables\Shipping\ShippingStateEnum;
use App\Enums\Catalogue\Asset\AssetStateEnum;
use App\Enums\Catalogue\Charge\ChargeStateEnum;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Models\Billables\Rental;
use App\Models\Billables\Service;
use App\Models\Catalogue\Asset;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Subscription;
use Illuminate\Support\Arr;

class UpdateAssetFromModel extends OrgAction
{
    use WithActionUpdate;

    public function handle(Asset $asset, array $modelData = [], int $hydratorsDelay = 0): Asset
    {
        /** @var Product|Rental|Service|Subscription $model */
        $model = $asset->model;

        if ($model instanceof Product) {
            $status = false;
            if (in_array($model->state, [ProductStateEnum::ACTIVE, ProductStateEnum::DISCONTINUING])) {
                $status = true;
            }
        } else {
            $status = $model->status;
        }


        data_set($modelData, 'code', $model->code);
        data_set($modelData, 'name', $model->name);
        data_set($modelData, 'price', $model->price, overwrite: false);
        data_set($modelData, 'unit', $model->unit, overwrite: false);
        data_set($modelData, 'units', $model->units, overwrite: false);
        data_set($modelData, 'status', $status);
        data_set($modelData, 'current_historic_asset_id', $model->current_historic_asset_id);


        $modelData['state'] = match ($model->state) {
            RentalStateEnum::IN_PROCESS, ProductStateEnum::IN_PROCESS, ServiceStateEnum::IN_PROCESS, ChargeStateEnum::IN_PROCESS, ShippingStateEnum::IN_PROCESS =>
            AssetStateEnum::IN_PROCESS,
            RentalStateEnum::ACTIVE, ProductStateEnum::ACTIVE, ServiceStateEnum::ACTIVE, ChargeStateEnum::ACTIVE, ShippingStateEnum::ACTIVE, =>
            AssetStateEnum::ACTIVE,
            ProductStateEnum::DISCONTINUING =>
            AssetStateEnum::DISCONTINUING,
            RentalStateEnum::DISCONTINUED, ProductStateEnum::DISCONTINUED, ServiceStateEnum::DISCONTINUED, ChargeStateEnum::DISCONTINUED, ShippingStateEnum::DISCONTINUED
            => AssetStateEnum::DISCONTINUED,
        };

        $originalMasterAsset = null;
        if (Arr::has($modelData, 'master_asset_id')) {
            $originalMasterAsset = $asset->masterAsset;
        }

        $asset = $this->update($asset, $modelData);

        $changes = $asset->getChanges();


        if (Arr::hasAny($changes, ['state'])) {
            ShopHydrateAssets::dispatch($asset->shop)->delay($hydratorsDelay);
            OrganisationHydrateAssets::dispatch($asset->organisation)->delay($hydratorsDelay);
            GroupHydrateAssets::dispatch($asset->group)->delay($hydratorsDelay);
        }

        if (Arr::hasAny($changes, ['state', 'master_asset_id'])) {
            MasterAssetHydrateAssets::run($asset->masterAsset);
            if ($originalMasterAsset != null && $originalMasterAsset->id != $asset->master_asset_id) {
                MasterAssetHydrateAssets::run($originalMasterAsset);
            }
        }


        return $asset;
    }


}
