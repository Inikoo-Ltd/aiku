<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Mar 2025 22:23:59 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment;

use App\Actions\Fulfilment\FulfilmentTransaction\DeleteFulfilmentTransaction;
use App\Actions\Fulfilment\FulfilmentTransaction\StoreFulfilmentTransaction;
use App\Actions\Fulfilment\FulfilmentTransaction\UpdateFulfilmentTransaction;
use App\Models\Billables\Service;
use App\Models\Catalogue\Asset;
use App\Models\Fulfilment\FulfilmentTransaction;
use App\Models\Fulfilment\PalletDelivery;
use App\Models\Fulfilment\PalletReturn;

trait WithSetAutoServices
{
    /**
     * @throws \Throwable
     */
    public function processAutoServices(PalletDelivery|PalletReturn $model, $autoServices, $palletTypes, $debug = false): PalletReturn|PalletDelivery
    {
        $appliedServices = [];

        /** @var Service $service */
        foreach ($autoServices as $service) {
            $appliedServices[$service->asset_id] = $palletTypes[$service->auto_assign_subject_type] ?? 0;
        }

        foreach ($appliedServices as $assetId => $quantity) {
            /** @var FulfilmentTransaction $transaction */
            $transaction = $model->transactions()->where('asset_id', $assetId)->first();

            if ($transaction and $quantity == 0) {
                DeleteFulfilmentTransaction::make()->action($transaction, );
            } elseif ($quantity > 0 and $transaction) {
                UpdateFulfilmentTransaction::make()->action($transaction, ['quantity' => $quantity]);
            } elseif ($quantity > 0) {
                $asset = Asset::find($assetId);
                StoreFulfilmentTransaction::make()->action(
                    $model,
                    [
                        'is_auto_assign'    => true,
                        'historic_asset_id' => $asset->current_historic_asset_id,
                        'quantity'          => $quantity
                    ]
                );
            }
        }


        return $model;
    }

    public function processStoredItemAutoServices(PalletReturn $model, $autoServices, $totalStoredItems, $debug = false): PalletReturn
    {

        /** @var Service $service */
        foreach ($autoServices as $service) {
            /** @var FulfilmentTransaction $transaction */
            $transaction = $model->transactions()->where('asset_id', $service->asset_id)->first();

            if ($transaction and $totalStoredItems == 0) {
                DeleteFulfilmentTransaction::make()->action($transaction);
            } elseif ($totalStoredItems > 0 and $transaction) {
                UpdateFulfilmentTransaction::make()->action($transaction, ['quantity' => $totalStoredItems]);
            } elseif ($totalStoredItems > 0) {
                $asset = Asset::find($service->asset_id);
                StoreFulfilmentTransaction::make()->action(
                    $model,
                    [
                        'is_auto_assign'    => true,
                        'historic_asset_id' => $asset->current_historic_asset_id,
                        'quantity'          => $totalStoredItems
                    ]
                );
            }
        }

        return $model;
    }

}
