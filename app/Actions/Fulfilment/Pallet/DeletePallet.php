<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 24 Jan 2024 16:14:16 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\Pallet;

use App\Actions\Fulfilment\Fulfilment\Hydrators\FulfilmentHydratePallets;
use App\Actions\Fulfilment\FulfilmentCustomer\Hydrators\FulfilmentCustomerHydratePallets;
use App\Actions\Fulfilment\Pallet\Search\PalletRecordSearch;
use App\Actions\Fulfilment\PalletDelivery\Hydrators\PalletDeliveryHydratePallets;
use App\Actions\Fulfilment\PalletDelivery\Hydrators\PalletDeliveryHydrateTransactions;
use App\Actions\Fulfilment\PalletDelivery\SetPalletDeliveryAutoServices;
use App\Actions\Inventory\Warehouse\Hydrators\WarehouseHydratePallets;
use App\Actions\Inventory\WarehouseArea\Hydrators\WarehouseAreaHydratePallets;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithFulfilmentShopEditAuthorisation;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Fulfilment\Pallet\PalletStatusEnum;
use App\Http\Resources\Fulfilment\PalletResource;
use App\Models\Fulfilment\Pallet;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

class DeletePallet extends OrgAction
{
    use WithActionUpdate;
    use WithFulfilmentShopEditAuthorisation;



    private Pallet $pallet;

    public function handle(Pallet $pallet): Pallet
    {
        $pallet->refresh();

        $this->update($pallet, ['customer_reference' => null]);
        $pallet->delete();


        FulfilmentCustomerHydratePallets::dispatch($pallet->fulfilmentCustomer);
        FulfilmentHydratePallets::dispatch($pallet->fulfilment);
        WarehouseHydratePallets::dispatch($pallet->warehouse);
        if ($pallet->location && $pallet->location->warehouseArea) {
            WarehouseAreaHydratePallets::dispatch($pallet->location->warehouseArea);
        }

        PalletDeliveryHydratePallets::run($pallet->palletDelivery);
        SetPalletDeliveryAutoServices::run($pallet->palletDelivery, true);
        PalletDeliveryHydrateTransactions::run($pallet->palletDelivery);
        PalletRecordSearch::dispatch($pallet);
        return $pallet;
    }


    public function afterValidator(Validator $validator): void
    {

        if ($this->pallet->status != PalletStatusEnum::IN_PROCESS) {
            $validator->errors()->add('state', 'Only pallets in process can be deleted');
        }

    }


    public function asController(Pallet $pallet, ActionRequest $request): Pallet
    {
        $this->pallet = $pallet;
        $this->initialisationFromFulfilment($pallet->fulfilment, $request);

        return $this->handle($pallet);
    }

    public function action(Pallet $pallet, array $modelData, int $hydratorsDelay = 0): Pallet
    {
        $this->pallet = $pallet;
        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFromFulfilment($pallet->fulfilment, $modelData);

        return $this->handle($pallet);
    }

    public function jsonResponse(Pallet $pallet): PalletResource
    {
        return new PalletResource($pallet);
    }
}
