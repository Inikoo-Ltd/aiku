<?php

/*
 * author Arya Permana - Kirin
 * created on 05-03-2025-14h-04m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Fulfilment\Pallet;

use App\Actions\Fulfilment\Fulfilment\Hydrators\FulfilmentHydratePallets;
use App\Actions\Fulfilment\FulfilmentCustomer\Hydrators\FulfilmentCustomerHydratePallets;
use App\Actions\Fulfilment\Pallet\Search\PalletRecordSearch;
use App\Actions\Fulfilment\RecurringBillTransaction\UpdateRecurringBillTransaction;
use App\Actions\Inventory\Warehouse\Hydrators\WarehouseHydratePallets;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydratePallets;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydratePallets;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Fulfilment\Pallet\PalletStateEnum;
use App\Enums\Fulfilment\Pallet\PalletStatusEnum;
use App\Models\Fulfilment\Pallet;
use App\Models\Fulfilment\RecurringBillTransaction;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class ReturnPallet extends OrgAction
{
    use WithActionUpdate;

    private Pallet $pallet;

    public function handle(Pallet $pallet): Pallet
    {
        $pallet = UpdatePallet::make()->action($pallet, [
            'state'  => PalletStateEnum::DISPATCHED,
            'status' => PalletStatusEnum::RETURNED,
            'dispatched_at' => now()
        ]);


        $recurringBillTransactionData = DB::table('recurring_bill_transactions')
            ->select('recurring_bill_transactions.id')
            ->where('item_type', 'Pallet')
            ->where('item_id', $pallet->id)
            ->where('recurring_bill_id', $pallet->current_recurring_bill_id)
            ->first();
        if ($recurringBillTransactionData) {

            $recurringBillTransaction = RecurringBillTransaction::find($recurringBillTransactionData->id);
            UpdateRecurringBillTransaction::make()->action($recurringBillTransaction, [
                'end_date' => now()
            ]);
        }


        GroupHydratePallets::dispatch($pallet->group);
        OrganisationHydratePallets::dispatch($pallet->organisation);
        FulfilmentCustomerHydratePallets::dispatch($pallet->fulfilmentCustomer);
        FulfilmentHydratePallets::dispatch($pallet->fulfilment);
        WarehouseHydratePallets::dispatch($pallet->warehouse);
        PalletRecordSearch::dispatch($pallet);

        return $pallet;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("fulfilment.{$this->fulfilment->id}.edit");
    }

    public function asController(Pallet $pallet, ActionRequest $request): Pallet
    {
        $this->pallet = $pallet;
        $this->initialisationFromFulfilment($pallet->fulfilment, $request);

        return $this->handle($pallet);
    }

    public function action(Pallet $pallet): Pallet
    {
        $this->pallet         = $pallet;
        $this->asAction       = true;
        $this->initialisationFromFulfilment($pallet->fulfilment, []);

        return $this->handle($pallet);
    }
}
