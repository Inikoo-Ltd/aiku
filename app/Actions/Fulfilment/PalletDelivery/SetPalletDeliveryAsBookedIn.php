<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 24 Jan 2024 16:14:16 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\PalletDelivery;

use App\Actions\Fulfilment\Fulfilment\Hydrators\FulfilmentHydratePalletDeliveries;
use App\Actions\Fulfilment\FulfilmentCustomer\Hydrators\FulfilmentCustomerHydratePalletDeliveries;
use App\Actions\Fulfilment\Pallet\UpdatePallet;
use App\Actions\Fulfilment\PalletDelivery\Hydrators\PalletDeliveryHydratePallets;
use App\Actions\Fulfilment\PalletDelivery\Notifications\SendPalletDeliveryNotification;
use App\Actions\Fulfilment\PalletDelivery\Search\PalletDeliveryRecordSearch;
use App\Actions\Inventory\Warehouse\Hydrators\WarehouseHydratePalletDeliveries;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydratePalletDeliveries;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydratePalletDeliveries;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Fulfilment\Pallet\PalletStateEnum;
use App\Enums\Fulfilment\Pallet\PalletStatusEnum;
use App\Enums\Fulfilment\PalletDelivery\PalletDeliveryStateEnum;
use App\Models\Fulfilment\PalletDelivery;
use Lorisleiva\Actions\ActionRequest;

class SetPalletDeliveryAsBookedIn extends OrgAction
{
    use WithActionUpdate;
    private PalletDelivery $palletDelivery;

    public function handle(PalletDelivery $palletDelivery): PalletDelivery
    {
        $modelData['booked_in_at'] = now();
        $modelData['state']        = PalletDeliveryStateEnum::BOOKED_IN;


        if ($palletDelivery->pallets) {
            foreach ($palletDelivery->pallets as $pallet) {
                if ($pallet->state == PalletStateEnum::BOOKED_IN) {
                    UpdatePallet::run($pallet, [
                        'state'      => PalletStateEnum::STORING,
                        'status'     => PalletStatusEnum::STORING,
                        'storing_at' => now()
                    ]);
                }
            }
        }

        $palletDelivery = $this->update($palletDelivery, $modelData);

        $palletDelivery = SetPalletDeliveryDate::run($palletDelivery);
        PalletDeliveryHydratePallets::dispatch($palletDelivery);
        GroupHydratePalletDeliveries::dispatch($palletDelivery->group);
        OrganisationHydratePalletDeliveries::dispatch($palletDelivery->organisation);
        WarehouseHydratePalletDeliveries::dispatch($palletDelivery->warehouse);
        FulfilmentCustomerHydratePalletDeliveries::dispatch($palletDelivery->fulfilmentCustomer);
        FulfilmentHydratePalletDeliveries::dispatch($palletDelivery->fulfilment);
        SendPalletDeliveryNotification::dispatch($palletDelivery);
        PalletDeliveryRecordSearch::dispatch($palletDelivery);

        return $palletDelivery;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->palletDelivery->state != PalletDeliveryStateEnum::BOOKING_IN) {
            return false;
        }

        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("fulfilment-shop.{$this->fulfilment->id}.edit");
    }

    public function prepareForValidation(): void
    {
        if ($this->palletDelivery->pallets()->whereNotIn('state', [PalletStateEnum::BOOKED_IN,PalletStateEnum::NOT_RECEIVED])->count() > 0) {
            abort(400, 'One or more pallets are not in a state that can be booked in.');
        }
    }

    public function asController(PalletDelivery $palletDelivery, ActionRequest $request): PalletDelivery
    {
        $this->palletDelivery = $palletDelivery;
        $this->initialisationFromFulfilment($palletDelivery->fulfilment, $request);
        return $this->handle($palletDelivery);
    }

    public function action(PalletDelivery $palletDelivery): PalletDelivery
    {
        $this->asAction       = true;
        $this->palletDelivery = $palletDelivery;
        $this->initialisationFromFulfilment($palletDelivery->fulfilment, []);
        return $this->handle($palletDelivery);
    }


}
