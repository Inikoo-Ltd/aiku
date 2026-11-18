<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 24 Jan 2024 16:14:16 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\PalletDelivery;

use App\Actions\Fulfilment\Fulfilment\Hydrators\FulfilmentHydratePalletDeliveries;
use App\Actions\Fulfilment\FulfilmentCustomer\Hydrators\FulfilmentCustomerHydratePalletDeliveries;
use App\Actions\Fulfilment\FulfilmentCustomer\Hydrators\FulfilmentCustomerHydratePallets;
use App\Actions\Fulfilment\FulfilmentCustomer\Hydrators\FulfilmentCustomerHydrateStoredItemAudits;
use App\Actions\Fulfilment\FulfilmentCustomer\Hydrators\FulfilmentCustomerHydrateStoredItems;
use App\Actions\Fulfilment\Pallet\SetPalletRental;
use App\Actions\Fulfilment\Pallet\UpdatePallet;
use App\Actions\Fulfilment\PalletDelivery\Notifications\SendPalletDeliveryNotification;
use App\Actions\Fulfilment\RecurringBill\CalculateRecurringBillTotals;
use App\Actions\Fulfilment\RecurringBill\Hydrators\RecurringBillHydrateTransactions;
use App\Actions\Fulfilment\RecurringBill\StoreRecurringBill;
use App\Actions\Fulfilment\RecurringBillTransaction\StoreRecurringBillTransaction;
use App\Actions\Fulfilment\StoredItem\UpdateStoredItem;
use App\Actions\Fulfilment\StoredItemAuditDelta\StoreStoredItemAuditDeltaFromDelivery;
use App\Actions\Inventory\Warehouse\Hydrators\WarehouseHydratePalletDeliveries;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydratePalletDeliveries;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydratePalletDeliveries;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Fulfilment\Pallet\PalletStateEnum;
use App\Enums\Fulfilment\Pallet\PalletStatusEnum;
use App\Enums\Fulfilment\PalletDelivery\PalletDeliveryStateEnum;
use App\Enums\Fulfilment\StoredItem\StoredItemStateEnum;
use App\Http\Resources\Fulfilment\PalletDeliveryResource;
use App\Models\Billables\Rental;
use App\Models\Fulfilment\Pallet;
use App\Models\Fulfilment\PalletDelivery;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class ReceivePalletDelivery extends OrgAction
{
    use WithActionUpdate;

    private PalletDelivery $palletDelivery;
    protected ?int $userId = null;

    public function handle(PalletDelivery $palletDelivery, array $modelData = []): PalletDelivery
    {
        $this->ensureEveryPalletHasRental($palletDelivery);

        $palletDelivery = DB::transaction(fn () => $this->receive($palletDelivery, $modelData));

        GroupHydratePalletDeliveries::dispatch($palletDelivery->group);
        OrganisationHydratePalletDeliveries::dispatch($palletDelivery->organisation);
        WarehouseHydratePalletDeliveries::dispatch($palletDelivery->warehouse);
        FulfilmentCustomerHydratePalletDeliveries::dispatch($palletDelivery->fulfilmentCustomer);
        FulfilmentHydratePalletDeliveries::dispatch($palletDelivery->fulfilment);

        FulfilmentCustomerHydratePallets::dispatch($palletDelivery->fulfilmentCustomer);
        FulfilmentCustomerHydrateStoredItems::dispatch($palletDelivery->fulfilmentCustomer);
        FulfilmentCustomerHydrateStoredItemAudits::dispatch($palletDelivery->fulfilmentCustomer);

        SendPalletDeliveryNotification::dispatch($palletDelivery);

        return $palletDelivery;
    }

    private function ensureEveryPalletHasRental(PalletDelivery $palletDelivery): void
    {
        $autoAssignedTypes = $this->organisation->rentals()
            ->where('auto_assign_asset', class_basename(Pallet::class))
            ->pluck('auto_assign_asset_type')
            ->all();

        $palletsWithoutRental = $palletDelivery->pallets()->get()
            ->filter(fn (Pallet $pallet) => !$pallet->rental_id && !in_array($pallet->type->value, $autoAssignedTypes))
            ->map(fn (Pallet $pallet) => $pallet->reference ?? $pallet->customer_reference ?? '#'.$pallet->id);

        if ($palletsWithoutRental->isNotEmpty()) {
            throw ValidationException::withMessages([
                'pallets' => __('These pallets have no rental and there is no automatic rental for their type: :pallets. Set a rental on each pallet before receiving the delivery.', [
                    'pallets' => $palletsWithoutRental->implode(', ')
                ])
            ]);
        }
    }

    private function receive(PalletDelivery $palletDelivery, array $modelData): PalletDelivery
    {
        $modelData['received_at'] = now();
        $modelData['state']       = PalletDeliveryStateEnum::RECEIVED;
        if ($palletDelivery->pallets) {
            foreach ($palletDelivery->pallets as $pallet) {
                UpdatePallet::run($pallet, [
                    'state'       => PalletStateEnum::RECEIVED,
                    'status'      => PalletStatusEnum::RECEIVING,
                    'received_at' => now()
                ]);

                $pallet->generateSlug();
                $pallet->save();

                /** @var Rental $rental */
                $rental = $this->organisation->rentals()
                    ->where('auto_assign_asset', class_basename(Pallet::class))
                    ->where('auto_assign_asset_type', $pallet->type->value)
                    ->first();

                if ($rental) {
                    SetPalletRental::run($pallet, [
                        'rental_id' => $rental->id
                    ]);
                }

                foreach ($pallet->storedItems as $storedItem) {
                    UpdateStoredItem::run($storedItem, [
                        'state' => StoredItemStateEnum::ACTIVE->value
                    ]);
                }

                foreach ($pallet->palletStoredItems as $palletStoredItem) {
                    StoreStoredItemAuditDeltaFromDelivery::run(
                        $palletDelivery,
                        $palletStoredItem,
                        [
                            'user_id' => $this->userId
                        ]
                    );
                }
            }
        }

        $palletDelivery = $this->update($palletDelivery, $modelData);


        $currentRecurringBill = $palletDelivery->fulfilmentCustomer->currentRecurringBill;
        if (!$currentRecurringBill) {
            $currentRecurringBill = StoreRecurringBill::make()->action(
                rentalAgreement: $palletDelivery->fulfilmentCustomer->rentalAgreement,
                modelData: [
                    'start_date' => now(),
                ],
            );
            $palletDelivery->fulfilmentCustomer->update(
                [
                    'current_recurring_bill_id' => $currentRecurringBill->id
                ]
            );
        }
        $this->update($palletDelivery, [
            'recurring_bill_id' => $currentRecurringBill->id
        ]);

        foreach ($palletDelivery->transactions as $transaction) {
            StoreRecurringBillTransaction::make()->action(
                $currentRecurringBill,
                $transaction,
                [
                    'start_date'                => now(),
                    'quantity'                  => $transaction->quantity,
                    'pallet_delivery_id'        => $palletDelivery->id,
                    'fulfilment_transaction_id' => $transaction->id
                ]
            );
        }
        $palletDelivery->refresh();
        $palletsInDelivery = $palletDelivery->pallets()
            ->where('state', PalletStateEnum::RECEIVED)
            ->get();

        foreach ($palletsInDelivery as $pallet) {
            $startDate = now();
            StoreRecurringBillTransaction::make()->action(
                recurringBill: $currentRecurringBill,
                item: $pallet,
                modelData: [
                    'start_date' => $startDate,
                    'end_date'   => $currentRecurringBill->end_date
                ],
                skipHydrators: true
            );
        }
        $palletDelivery = SetPalletDeliveryDate::run($palletDelivery);
        CalculateRecurringBillTotals::run($currentRecurringBill);
        RecurringBillHydrateTransactions::run($currentRecurringBill);

        return $palletDelivery;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->palletDelivery->state != PalletDeliveryStateEnum::CONFIRMED) {
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
        $this->userId         = $request->user()->id;
        $this->initialisationFromFulfilment($palletDelivery->fulfilment, $request);

        return $this->handle($palletDelivery, $this->validatedData);
    }

    public function action(PalletDelivery $palletDelivery, ?int $userId = null): PalletDelivery
    {
        $this->asAction       = true;
        $this->palletDelivery = $palletDelivery;
        $this->userId         = $userId;

        $this->initialisation($palletDelivery->organisation, []);

        return $this->handle($palletDelivery);
    }
}
