<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 01 Jun 2024 17:49:30 Central European Summer Time, Mijas Costa, Spain
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Asset;

use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateAssets;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateAssets;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateAssets;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Catalogue\Asset\AssetStateEnum;
use App\Enums\Catalogue\Charge\ChargeStateEnum;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Catalogue\Service\ServiceStateEnum;
use App\Enums\Catalogue\Shipping\ShippingStateEnum;
use App\Enums\Fulfilment\Rental\RentalStateEnum;
use App\Models\Catalogue\Asset;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Service;
use App\Models\Catalogue\Subscription;
use App\Models\Fulfilment\Rental;
use Illuminate\Support\Arr;

class UpdateAsset extends OrgAction
{
    use WithActionUpdate;

    public function handle(Asset $asset, array $modelData = [], int $hydratorsDelay =0): Asset
    {
        /** @var Product|Rental|Service|Subscription $model */
        $model = $asset->model;


        data_set($modelData, 'code', $model->code);
        data_set($modelData, 'name', $model->name);
        data_set($modelData, 'price', $model->price, overwrite: false);
        data_set($modelData, 'unit', $model->unit, overwrite: false);
        data_set($modelData, 'units', $model->units, overwrite: false);
        data_set($modelData, 'status', $model->status);
        data_set($modelData, 'current_historic_asset_id', $model->current_historic_asset_id);


        $modelData['state'] = match ($model->state) {
            RentalStateEnum::IN_PROCESS, ProductStateEnum::IN_PROCESS, ServiceStateEnum::IN_PROCESS, ChargeStateEnum::IN_PROCESS, ShippingStateEnum::IN_PROCESS=>
            AssetStateEnum::IN_PROCESS,
            RentalStateEnum::ACTIVE, ProductStateEnum::ACTIVE, ServiceStateEnum::ACTIVE, ChargeStateEnum::ACTIVE, ShippingStateEnum::ACTIVE,=>
            AssetStateEnum::ACTIVE,
            ProductStateEnum::DISCONTINUING =>
            AssetStateEnum::DISCONTINUING,
            RentalStateEnum::DISCONTINUED, ProductStateEnum::DISCONTINUED, ServiceStateEnum::DISCONTINUED, ChargeStateEnum::DISCONTINUED, ShippingStateEnum::DISCONTINUED
            => AssetStateEnum::DISCONTINUED,
        };

        $asset = $this->update($asset, $modelData);

        $changed = $asset->getChanges();


        if (Arr::hasAny($changed, ['state'])) {
            ShopHydrateAssets::dispatch($asset->shop)->delay($hydratorsDelay);
            OrganisationHydrateAssets::dispatch($asset->organisation)->delay($hydratorsDelay);
            GroupHydrateAssets::dispatch($asset->group)->delay($hydratorsDelay);
        }


        return $asset;
    }


}
