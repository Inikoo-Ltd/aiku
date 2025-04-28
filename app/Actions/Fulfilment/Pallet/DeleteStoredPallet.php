<?php

/*
 * author Arya Permana - Kirin
 * created on 08-04-2025-16h-00m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Fulfilment\Pallet;

use App\Actions\Comms\Email\SendPalletDeletedNotification;
use App\Actions\Fulfilment\Fulfilment\Hydrators\FulfilmentHydratePallets;
use App\Actions\Fulfilment\FulfilmentCustomer\Hydrators\FulfilmentCustomerHydratePallets;
use App\Actions\Fulfilment\Pallet\Search\PalletRecordSearch;
use App\Actions\Fulfilment\RecurringBill\CalculateRecurringBillTotals;
use App\Actions\Fulfilment\RecurringBillTransaction\DeleteRecurringBillTransaction;
use App\Actions\Inventory\Location\Hydrators\LocationHydratePallets;
use App\Actions\Inventory\Warehouse\Hydrators\WarehouseHydratePallets;
use App\Actions\Inventory\WarehouseArea\Hydrators\WarehouseAreaHydratePallets;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithFulfilmentShopSupervisorAuthorisation;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Fulfilment\Pallet;
use App\Models\Fulfilment\RecurringBillTransaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class DeleteStoredPallet extends OrgAction
{
    use WithActionUpdate;
    use WithFulfilmentShopSupervisorAuthorisation;

    private Pallet $pallet;

    /**
     * @throws \Throwable
     */
    public function handle(Pallet $pallet, array $modelData, $quiet = false): Pallet
    {
        $recurringBillTransactionDeleted = DB::transaction(function () use ($pallet, $modelData) {
            $recurringBillTransactionDeleted = false;
            if ($pallet->currentRecurringBill) {
                $transaction = RecurringBillTransaction::where('item_type', 'Pallet')->where('item_id', $pallet->id)->first();
                if ($transaction) {
                    DeleteRecurringBillTransaction::make()->action($transaction);
                    CalculateRecurringBillTotals::make()->action($pallet->currentRecurringBill);
                    $recurringBillTransactionDeleted = true;
                }
            }

            $pallet = $this->update($pallet, $modelData);
            $pallet->delete();

            return $recurringBillTransactionDeleted;
        });

        $fulfilmentCustomer = $pallet->fulfilmentCustomer;
        $fulfilmentCustomer->refresh();


        if ($recurringBillTransactionDeleted) {
            StoreDeletePalletHistory::run($pallet, $pallet->currentRecurringBill);
        }
        StoreDeletePalletHistory::run($pallet, $pallet->fulfilmentCustomer->customer);
        if (!$quiet) {
            SendPalletDeletedNotification::dispatch($pallet);
        }


        FulfilmentCustomerHydratePallets::dispatch($fulfilmentCustomer);
        FulfilmentHydratePallets::dispatch($fulfilmentCustomer->fulfilment);
        foreach ($fulfilmentCustomer->fulfilment->warehouses as $warehouse) {
            WarehouseHydratePallets::dispatch($warehouse);
        }

        if ($pallet->location) {
            LocationHydratePallets::dispatch($pallet->location);
            if ($pallet->location->warehouseArea) {
                WarehouseAreaHydratePallets::dispatch($pallet->location->warehouseArea);
            }
        }
        PalletRecordSearch::dispatch($pallet);


        return $pallet;
    }

    public function rules(): array
    {
        return [
            'deleted_note' => ['required', 'string', 'max:4000'],
            'deleted_by'   => ['nullable', 'integer', Rule::exists('users', 'id')->where('group_id', $this->group->id)],
        ];
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        $this->set('deleted_by', $request->user()->id);
    }

    /**
     * @throws \Throwable
     */
    public function action(Pallet $pallet, array $modelData, bool $quiet = true): Pallet
    {
        $this->pallet   = $pallet;
        $this->asAction = true;
        $this->initialisationFromFulfilment($pallet->fulfilment, $modelData);

        return $this->handle($pallet, $modelData, $quiet);
    }

    /**
     * @throws \Throwable
     */
    public function asController(Pallet $pallet, ActionRequest $request): Pallet
    {
        $this->pallet = $pallet;
        $this->initialisationFromFulfilment($pallet->fulfilment, $request);

        return $this->handle(pallet: $pallet, modelData: $this->validatedData);
    }

    public function htmlResponse(Pallet $pallet): RedirectResponse
    {
        $fulfilmentCustomer = $pallet->fulfilmentCustomer;

        return Redirect::route('grp.org.fulfilments.show.crm.customers.show.pallets.index', [
            'organisation'       => $fulfilmentCustomer->organisation->slug,
            'fulfilment'         => $fulfilmentCustomer->fulfilment->slug,
            'fulfilmentCustomer' => $fulfilmentCustomer->slug
        ]);
    }

}
