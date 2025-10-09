<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 08 Apr 2024 11:05:48 Central Indonesia Time, Bali Office , Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\HistoricAsset;

use App\Actions\Catalogue\Asset\Hydrators\AssetHydrateHistoricAssets;
use App\Models\Billables\Charge;
use App\Models\Billables\Rental;
use App\Models\Billables\Service;
use App\Models\Billables\ShippingZone;
use App\Models\Catalogue\HistoricAsset;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Subscription;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreHistoricAsset
{
    use AsAction;

    public function handle(Product|Rental|Service|Subscription|Charge|ShippingZone $assetModel, array $modelData = [], int $hydratorsDelay = 0): HistoricAsset
    {
        $historicAssetData = [
            'source_id' => Arr::get($modelData, 'source_id'),
        ];

        data_set($historicAssetData, 'code', $assetModel->code);
        data_set($historicAssetData, 'name', $assetModel->name);


        if ($assetModel instanceof ShippingZone) {
            data_set($historicAssetData, 'price', null);
            data_set($historicAssetData, 'units', 1);
            data_set($historicAssetData, 'unit', 'shipping');
        } else {
            data_set($historicAssetData, 'price', $assetModel->price);
            data_set($historicAssetData, 'units', $assetModel->units);
            data_set($historicAssetData, 'unit', $assetModel->unit);
        }




        if (Arr::get($modelData, 'created_at')) {
            $historicAssetData['created_at'] = Arr::get($modelData, 'created_at');
        } else {
            $historicAssetData['created_at'] = $assetModel->created_at;
        }
        if (Arr::get($modelData, 'deleted_at')) {
            $historicAssetData['deleted_at'] = Arr::get($modelData, 'deleted_at');
        }
        if (Arr::exists($modelData, 'status')) {
            $historicAssetData['status'] = Arr::exists($modelData, 'status');
        } else {
            $historicAssetData['status'] = true;
        }


        data_set($historicAssetData, 'organisation_id', $assetModel->organisation_id);
        data_set($historicAssetData, 'group_id', $assetModel->group_id);
        data_set($historicAssetData, 'asset_id', $assetModel->asset_id);


        /** @var HistoricAsset $historicAsset */
        $historicAsset = $assetModel->historicAssets()->create($historicAssetData);
        $historicAsset->stats()->create();


        AssetHydrateHistoricAssets::dispatch($assetModel->asset)->delay($hydratorsDelay);

        return $historicAsset;
    }
}
