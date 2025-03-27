<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 28 Apr 2024 19:15:04 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\PalletDelivery;

use App\Actions\Fulfilment\Fulfilment\Hydrators\FulfilmentHydratePalletDeliveries;
use App\Actions\Fulfilment\FulfilmentCustomer\Hydrators\FulfilmentCustomerHydratePalletDeliveries;
use App\Actions\Fulfilment\Pallet\UpdatePallet;
use App\Actions\Fulfilment\PalletDelivery\Search\PalletDeliveryRecordSearch;
use App\Actions\Inventory\Warehouse\Hydrators\WarehouseHydratePalletDeliveries;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydratePalletDeliveries;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydratePalletDeliveries;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Fulfilment\Pallet\PalletStateEnum;
use App\Enums\Fulfilment\Pallet\PalletStatusEnum;
use App\Enums\Fulfilment\PalletDelivery\PalletDeliveryStateEnum;
use App\Http\Resources\Fulfilment\PalletDeliveryResource;
use App\Models\Fulfilment\PalletDelivery;
use Illuminate\Http\Resources\Json\JsonResource;
use Lorisleiva\Actions\ActionRequest;

class StartBookingPalletDelivery extends OrgAction
{
    use WithActionUpdate;
    private PalletDelivery $palletDelivery;

    public function handle(PalletDelivery $palletDelivery, array $modelData = []): PalletDelivery
    {
        $modelData['booking_in_at'] = now();
        $modelData['state']         = PalletDeliveryStateEnum::BOOKING_IN;
        if($palletDelivery->pallets) {
            foreach ($palletDelivery->pallets as $pallet) {
                UpdatePallet::run($pallet, [
                    'state'         => PalletStateEnum::BOOKING_IN,
                    'status'        => PalletStatusEnum::RECEIVING,
                    'booking_in_at' => now()
                ]);
            }
        }

        $palletDelivery = $this->update($palletDelivery, $modelData);
        $palletDelivery = SetPalletDeliveryDate::run($palletDelivery);
        GroupHydratePalletDeliveries::dispatch($palletDelivery->group);
        OrganisationHydratePalletDeliveries::dispatch($palletDelivery->organisation);
        WarehouseHydratePalletDeliveries::dispatch($palletDelivery->warehouse);
        FulfilmentCustomerHydratePalletDeliveries::dispatch($palletDelivery->fulfilmentCustomer);
        FulfilmentHydratePalletDeliveries::dispatch($palletDelivery->fulfilment);

        PalletDeliveryRecordSearch::dispatch($palletDelivery);
        return $palletDelivery;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->palletDelivery->state != PalletDeliveryStateEnum::RECEIVED) {
            return false;
        }

        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("fulfilment-shop.{$this->fulfilment->id}.edit");
    }

    public function jsonResponse(PalletDelivery $palletDelivery): JsonResource
    {
        return new PalletDeliveryResource($palletDelivery);
    }

    public function asController(PalletDelivery $palletDelivery, ActionRequest $request): PalletDelivery
    {
        $this->palletDelivery = $palletDelivery;
        $this->initialisationFromFulfilment($palletDelivery->fulfilment, $request);

        return $this->handle($palletDelivery, $this->validatedData);
    }

    public function action(PalletDelivery $palletDelivery): PalletDelivery
    {
        $this->asAction       = true;
        $this->palletDelivery = $palletDelivery;

        $this->initialisation($palletDelivery->organisation, []);
        return $this->handle($palletDelivery);
    }
}
