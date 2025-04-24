<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 24 Jan 2024 16:14:16 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\PalletDelivery;

use App\Actions\Fulfilment\Fulfilment\Hydrators\FulfilmentHydratePalletDeliveries;
use App\Actions\Fulfilment\FulfilmentCustomer\Hydrators\FulfilmentCustomerHydratePalletDeliveries;
use App\Actions\Fulfilment\PalletDelivery\Notifications\SendPalletDeliveryNotification;
use App\Actions\Fulfilment\PalletDelivery\Search\PalletDeliveryRecordSearch;
use App\Actions\Fulfilment\StoredItem\UpdateStoredItem;
use App\Actions\Inventory\Warehouse\Hydrators\WarehouseHydratePalletDeliveries;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydratePalletDeliveries;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydratePalletDeliveries;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Fulfilment\PalletDelivery\PalletDeliveryStateEnum;
use App\Enums\Fulfilment\StoredItem\StoredItemStateEnum;
use App\Http\Resources\Fulfilment\PalletDeliveryResource;
use App\Models\CRM\WebUser;
use App\Models\Fulfilment\PalletDelivery;
use Illuminate\Http\Resources\Json\JsonResource;

class SubmitPalletDelivery extends OrgAction
{
    use WithActionUpdate;

    public function handle(PalletDelivery $palletDelivery): PalletDelivery
    {
        $modelData['submitted_at'] = now();
        $modelData['state']        = PalletDeliveryStateEnum::SUBMITTED;

        $numberPallets       = $palletDelivery->pallets()->count();
        $numberStoredPallets = $palletDelivery->fulfilmentCustomer->pallets()->where('state', PalletDeliveryStateEnum::BOOKED_IN->value)->count();

        $palletLimits = $palletDelivery->fulfilmentCustomer?->rentalAgreement?->pallets_limit ?? 0;
        $totalPallets = $numberPallets + $numberStoredPallets;

        $palletDelivery = $this->update($palletDelivery, $modelData);

        foreach ($palletDelivery->pallets as $pallet) {
            foreach ($pallet->storedItems as $storedItem) {
                UpdateStoredItem::run($storedItem, [
                    'state' => StoredItemStateEnum::SUBMITTED->value
                ]);
            }
        }

        if ($totalPallets < $palletLimits && !(request()->user() instanceof WebUser)) {
            $palletDelivery = ConfirmPalletDelivery::run($palletDelivery);
        }
        $palletDelivery = SetPalletDeliveryDate::run($palletDelivery);
        SendPalletDeliveryNotification::dispatch($palletDelivery);

        GroupHydratePalletDeliveries::dispatch($palletDelivery->group);
        OrganisationHydratePalletDeliveries::dispatch($palletDelivery->organisation);
        WarehouseHydratePalletDeliveries::dispatch($palletDelivery->warehouse);
        FulfilmentCustomerHydratePalletDeliveries::dispatch($palletDelivery->fulfilmentCustomer);
        FulfilmentHydratePalletDeliveries::dispatch($palletDelivery->fulfilment);

        PalletDeliveryRecordSearch::dispatch($palletDelivery);
        return $palletDelivery;
    }



    public function jsonResponse(PalletDelivery $palletDelivery): JsonResource
    {
        return new PalletDeliveryResource($palletDelivery);
    }


}
