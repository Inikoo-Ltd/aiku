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
use App\Actions\Inventory\Warehouse\Hydrators\WarehouseHydratePallets;
use App\Actions\Inventory\WarehouseArea\Hydrators\WarehouseAreaHydratePallets;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Fulfilment\Pallet\PalletStateEnum;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\Pallet;
use App\Models\Fulfilment\RecurringBillTransaction;
use Illuminate\Support\Facades\Event;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;
use OwenIt\Auditing\Events\AuditCustom;

class DeleteStoredPallet extends OrgAction
{
    use WithActionUpdate;

    private Pallet $pallet;

    public function handle(Pallet $pallet, array $modelData): FulfilmentCustomer
    {
        $fulfilmentCustomer = $pallet->fulfilmentCustomer;

        DB::transaction(function () use ($pallet, $fulfilmentCustomer) {
            if ($pallet->currentRecurringBill) {
                $transaction = RecurringBillTransaction::where('item_type', 'Pallet')->where('item_id', $pallet->id)->first();
                if ($transaction) {
                    DeleteRecurringBillTransaction::make()->action($transaction);
                    CalculateRecurringBillTotals::make()->action($pallet->currentRecurringBill);
                }
                $pallet->currentRecurringBill->auditEvent    = 'delete';
                $pallet->currentRecurringBill->isCustomEvent = true;
                $pallet->currentRecurringBill->auditCustomOld = [
                    'pallet' => $pallet->reference
                ];
                $pallet->currentRecurringBill->auditCustomNew = [
                    'pallet' => __("The pallet :ref has been deleted.", ['ref' => $pallet->reference])
                ];
                Event::dispatch(AuditCustom::class, [$pallet->currentRecurringBill]);
            }

            $fulfilmentCustomer->customer->auditEvent    = 'delete';
            $fulfilmentCustomer->customer->isCustomEvent = true;
            $fulfilmentCustomer->customer->auditCustomOld = [
                'pallet' => $pallet->reference
            ];
            $fulfilmentCustomer->customer->auditCustomNew = [
                'pallet' => __("The pallet :ref has been deleted.", ['ref' => $pallet->reference])
            ];
            Event::dispatch(AuditCustom::class, [$fulfilmentCustomer->customer]);

            SendPalletDeletedNotification::dispatch($pallet);

            $pallet->delete();
        });

        $fulfilmentCustomer->refresh();
        FulfilmentCustomerHydratePallets::dispatch($fulfilmentCustomer);
        FulfilmentHydratePallets::dispatch($fulfilmentCustomer->fulfilment);
        WarehouseHydratePallets::dispatch($fulfilmentCustomer->warehouse);
        if ($pallet->location && $pallet->location->warehouseArea) {
            WarehouseAreaHydratePallets::dispatch($pallet->location->warehouseArea);
        }
        PalletRecordSearch::dispatch($pallet);

        return $fulfilmentCustomer;
    }

    public function rules(): array
    {
        return [
            // 'deleted_note' => ['required', 'string', 'max:4000'],
            'delete_confirmation'   => ['sometimes'],
        ];
    }

    public function afterValidator()
    {
        if (strtolower(trim($this->get('delete_confirmation'))) != strtolower($this->pallet->reference) && $this->pallet->state == PalletStateEnum::STORING) {
            abort(419);
        }
    }

    public function asController(Pallet $pallet, ActionRequest $request): FulfilmentCustomer
    {
        $this->pallet = $pallet;
        $this->initialisationFromFulfilment($pallet->fulfilment, $request);

        return $this->handle($pallet, $this->validatedData);
    }

    public function htmlResponse(FulfilmentCustomer $fulfilmentCustomer): RedirectResponse
    {
        return Redirect::route('grp.org.fulfilments.show.crm.customers.show.pallets.index', [
            'organisation' => $fulfilmentCustomer->organisation->slug,
            'fulfilment' => $fulfilmentCustomer->fulfilment->slug,
            'fulfilmentCustomer' => $fulfilmentCustomer->slug
        ]);
    }
}
