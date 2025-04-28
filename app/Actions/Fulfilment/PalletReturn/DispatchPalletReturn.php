<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 24 Jan 2024 16:14:16 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\PalletReturn;

use App\Actions\Dropshipping\Shopify\Fulfilment\DispatchFulfilmentOrderShopify;
use App\Actions\Fulfilment\Fulfilment\Hydrators\FulfilmentHydratePalletReturns;
use App\Actions\Fulfilment\FulfilmentCustomer\Hydrators\FulfilmentCustomerHydratePalletReturns;
use App\Actions\Fulfilment\Pallet\ReturnPallet;
use App\Actions\Fulfilment\PalletReturn\Hydrators\PalletReturnHydratePallets;
use App\Actions\Fulfilment\PalletReturn\Notifications\SendPalletReturnNotification;
use App\Actions\Fulfilment\PalletReturn\Search\PalletReturnRecordSearch;
use App\Actions\Fulfilment\RecurringBill\CalculateRecurringBillTotals;
use App\Actions\Inventory\Warehouse\Hydrators\WarehouseHydratePalletReturns;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydratePalletReturns;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydratePalletReturns;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Fulfilment\Pallet\PalletStatusEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnStateEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnTypeEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Http\Resources\Fulfilment\PalletReturnResource;
use App\Models\Fulfilment\Pallet;
use App\Models\Fulfilment\PalletReturn;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class DispatchPalletReturn extends OrgAction
{
    use WithActionUpdate;


    /**
     * @throws \Throwable
     */
    public function handle(PalletReturn $palletReturn, array $modelData): PalletReturn
    {
        $modelData['dispatched_at'] = now();
        $modelData['state']         = PalletReturnStateEnum::DISPATCHED;

        /** @var Pallet $pallet */
        $pallets = $palletReturn->pallets()
            ->whereNot('status', PalletStatusEnum::INCIDENT->value)
            ->get();

        if ($palletReturn->type == PalletReturnTypeEnum::PALLET) {
            $palletReturn = DB::transaction(function () use ($palletReturn, $pallets) {
                /** @var Pallet $pallet */
                foreach ($pallets as $pallet) {
                    $pallet = ReturnPallet::make()->action(
                        pallet: $pallet,
                        calculateParentTotals: false
                    );

                    $palletReturn->pallets()->syncWithoutDetaching([
                        $pallet->id => [
                            'state' => PalletReturnStateEnum::DISPATCHED
                        ]
                    ]);
                }

                return $palletReturn;
            });
        }
        $this->update($palletReturn, $modelData);

        if ($recurringBill = $palletReturn->recurringBill) {
            CalculateRecurringBillTotals::run($recurringBill);
        }

        if ($palletReturn->platform?->type === PlatformTypeEnum::SHOPIFY) {
            DispatchFulfilmentOrderShopify::run($palletReturn);
        }

        PalletReturnHydratePallets::dispatch($palletReturn);
        GroupHydratePalletReturns::dispatch($palletReturn->group);
        OrganisationHydratePalletReturns::dispatch($palletReturn->organisation);
        WarehouseHydratePalletReturns::dispatch($palletReturn->warehouse);
        FulfilmentCustomerHydratePalletReturns::dispatch($palletReturn->fulfilmentCustomer);
        FulfilmentHydratePalletReturns::dispatch($palletReturn->fulfilment);
        SendPalletReturnNotification::run($palletReturn);
        PalletReturnRecordSearch::dispatch($palletReturn);

        return $palletReturn;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("fulfilment-shop.{$this->fulfilment->id}.edit");
    }

    public function jsonResponse(PalletReturn $palletReturn): JsonResource
    {
        return new PalletReturnResource($palletReturn);
    }

    /**
     * @throws \Throwable
     */
    public function asController(PalletReturn $palletReturn, ActionRequest $request): PalletReturn
    {
        $this->initialisationFromFulfilment($palletReturn->fulfilment, $request);

        return $this->handle($palletReturn, $this->validatedData);
    }

    /**
     * @throws \Throwable
     */
    public function action(PalletReturn $palletReturn): PalletReturn
    {
        $this->asAction = true;
        $this->initialisationFromFulfilment($palletReturn->fulfilment, []);

        return $this->handle($palletReturn, $this->validatedData);
    }
}
