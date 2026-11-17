<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 24 Jan 2024 16:14:16 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\Pallet;

use App\Actions\Fulfilment\PalletDelivery\AutomaticallySetPalletDeliveryStateAsNotReceivedIfAllPalletsNotReceived;
use App\Actions\Fulfilment\PalletDelivery\Hydrators\PalletDeliveryHydratePallets;
use App\Actions\Fulfilment\PalletDelivery\SetPalletDeliveryAutoServices;
use App\Actions\Fulfilment\RecurringBillTransaction\DeleteRecurringBillTransaction;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Fulfilment\Pallet\PalletStateEnum;
use App\Enums\Fulfilment\Pallet\PalletStatusEnum;
use App\Enums\Fulfilment\RecurringBill\RecurringBillStatusEnum;
use App\Http\Resources\Fulfilment\PalletResource;
use App\Models\Fulfilment\Pallet;
use App\Models\Fulfilment\RecurringBillTransaction;
use Illuminate\Support\Facades\DB;
use App\Enums\Fulfilment\PalletDelivery\PalletDeliveryStateEnum;
use App\Actions\Inventory\Location\Hydrators\LocationHydratePallets;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class SetPalletAsNotReceived extends OrgAction
{
    use WithActionUpdate;


    /**
     * @var false|mixed
     */
    private mixed $debug;

    /**
     * @throws \Throwable
     */
    public function handle(Pallet $pallet): Pallet
    {
        $oldLocation = $pallet->location;
        data_set($modelData, 'state', PalletStateEnum::NOT_RECEIVED);
        data_set($modelData, 'status', PalletStatusEnum::NOT_RECEIVED);
        data_set($modelData, 'location_id', null);
        data_set($modelData, 'booked_in_at', null);
        data_set($modelData, 'set_as_not_received_at', now());

        $pallet = UpdatePallet::run($pallet, $modelData);
        AutomaticallySetPalletDeliveryStateAsNotReceivedIfAllPalletsNotReceived::run($pallet->palletDelivery);

        SetPalletDeliveryAutoServices::run($pallet->palletDelivery);

        $recurringBillTransactionData = DB::table('recurring_bill_transactions')->select('recurring_bill_transactions.id')
            ->leftJoin('recurring_bills', 'recurring_bill_transactions.recurring_bill_id', 'recurring_bills.id')
            ->where('item_id', $pallet->id)
            ->where('item_type', 'Pallet')
            ->where('recurring_bills.status', RecurringBillStatusEnum::CURRENT->value)
            ->first();


        if ($recurringBillTransactionData) {
            DeleteRecurringBillTransaction::make()->action(RecurringBillTransaction::find($recurringBillTransactionData->id));
        }

        PalletDeliveryHydratePallets::dispatch($pallet->palletDelivery);
        if ($oldLocation) {
            LocationHydratePallets::dispatch($oldLocation);
        }

        return $pallet;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("fulfilment.{$this->warehouse->id}.edit");
    }


    /**
     * @throws \Throwable
     */
    public function asController(Pallet $pallet, ActionRequest $request): Pallet
    {
        $this->initialisationFromWarehouse($pallet->warehouse, $request);

        if (!in_array($pallet->palletDelivery?->state, [PalletDeliveryStateEnum::RECEIVED, PalletDeliveryStateEnum::BOOKING_IN], true)
            || !in_array($pallet->state, [PalletStateEnum::RECEIVED, PalletStateEnum::BOOKING_IN], true)
            || $pallet->location_id !== null) {
            throw ValidationException::withMessages(['pallet' => __('This pallet can not be set as not received now.')]);
        }

        return $this->handle($pallet);
    }


    /**
     * @throws \Throwable
     */
    public function action(Pallet $pallet, $debug = false): Pallet
    {
        $this->debug    = $debug;
        $this->asAction = true;
        $this->initialisationFromWarehouse($pallet->warehouse, []);

        return $this->handle($pallet);
    }

    public function jsonResponse(Pallet $pallet, ActionRequest $request): PalletResource
    {

        return new PalletResource($pallet);
    }
}
