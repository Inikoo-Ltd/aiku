<?php

/*
 * author Arya Permana - Kirin
 * created on 10-04-2025-09h-44m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Fulfilment\PalletReturn;

use App\Actions\Comms\Email\SendPalletReturnDeletedNotification;
use App\Actions\Dropshipping\Shopify\Order\CancelFulfilmentRequestToShopify;
use App\Actions\Fulfilment\FulfilmentCustomer\Hydrators\FulfilmentCustomerHydratePalletReturns;
use App\Actions\Fulfilment\FulfilmentCustomer\Hydrators\FulfilmentCustomerHydratePallets;
use App\Actions\Fulfilment\FulfilmentTransaction\DeleteFulfilmentTransaction;
use App\Actions\Fulfilment\Pallet\UpdatePallet;
use App\Actions\Fulfilment\RecurringBillTransaction\DeleteRecurringBillTransaction;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Fulfilment\Pallet\PalletStateEnum;
use App\Enums\Fulfilment\Pallet\PalletStatusEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnTypeEnum;
use App\Enums\Fulfilment\RecurringBill\RecurringBillStatusEnum;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\PalletReturn;
use Illuminate\Support\Facades\Event;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use OwenIt\Auditing\Events\AuditCustom;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DeleteDispatchedPalletReturn extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    private bool $action = false;
    private FulfilmentCustomer $fulfilmentCustomer;


    public function handle(PalletReturn $palletReturn, array $modelData = []): void
    {

        $fulfilmentCustomer = $palletReturn->fulfilmentCustomer;
        $recurringBill = $palletReturn->recurringBill;

        DB::transaction(function () use ($palletReturn, $fulfilmentCustomer, $recurringBill, $modelData) {
            if ($palletReturn->type == PalletReturnTypeEnum::PALLET) {
                $palletIds = $palletReturn->pallets->pluck('id')->toArray();
                foreach ($palletReturn->pallets as $pallet) {
                    UpdatePallet::run($pallet, [
                        'state'                   => PalletStateEnum::STORING,
                        'status'                  => PalletStatusEnum::STORING,
                        'pallet_return_id'        => null,
                        'requested_for_return_at' => null
                    ]);
                }
                $palletReturn->pallets()->detach($palletIds);
            } elseif ($palletReturn->type == PalletReturnTypeEnum::STORED_ITEM) {
                $storedItemIds = $palletReturn->storedItems->pluck('id')->toArray();
                $palletReturn->storedItems()->detach($storedItemIds);
            }

            foreach ($palletReturn->transactions as $transaction) {

                DeleteFulfilmentTransaction::make()->action($transaction);
                if ($recurringBill && $recurringBill->status == RecurringBillStatusEnum::CURRENT && $transaction->recurringBillTransaction) {
                    DeleteRecurringBillTransaction::make()->action($transaction->recurringBillTransaction); //delete recurring bill transaction
                }
            }

            $this->update($palletReturn, $modelData);



            $fulfilmentCustomer->customer->auditEvent    = 'delete';
            $fulfilmentCustomer->customer->isCustomEvent = true;

            $fulfilmentCustomer->customer->auditCustomOld = [
                'return' => $palletReturn->reference
            ];

            $fulfilmentCustomer->customer->auditCustomNew = [
                'return' => __("The return has been deleted due to: $palletReturn->delete_comment.")
            ];

            Event::dispatch(new AuditCustom($fulfilmentCustomer->customer));

            if ($palletReturn->fulfilmentCustomer->customer->shopifyUser !== null) {
                CancelFulfilmentRequestToShopify::dispatch($palletReturn);
            }
            $palletReturn->delete();
        });

        StoreDeletePalletReturnHistory::run($palletReturn, $fulfilmentCustomer->customer);
        SendPalletReturnDeletedNotification::dispatch($palletReturn);

        $fulfilmentCustomer->refresh();
        FulfilmentCustomerHydratePalletReturns::dispatch($fulfilmentCustomer);
        FulfilmentCustomerHydratePallets::dispatch($fulfilmentCustomer);
    }

    public function rules(): array
    {
        return [
            'deleted_note' => ['required', 'string', 'max:4000'],
            'deleted_by'   => ['nullable', 'integer', Rule::exists('users', 'id')->where('group_id', $this->group->id)],
        ];
    }

    public function prepareForValidation(ActionRequest $request)
    {
        $this->set('deleted_by', $request->user()->id);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::route('grp.org.fulfilments.show.crm.customers.show.pallet_returns.index', [
            'organisation'       => $this->organisation->slug,
            'fulfilment'         => $this->fulfilment->slug,
            'fulfilmentCustomer' => $this->fulfilmentCustomer->slug
        ]);
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->action) {
            return true;
        }

        return $request->user()->authTo("fulfilment-shop.{$this->fulfilment->id}.edit");
    }

    public function asController(PalletReturn $palletReturn, ActionRequest $request): void
    {
        $this->fulfilmentCustomer = $palletReturn->fulfilmentCustomer;
        $this->initialisationFromFulfilment($palletReturn->fulfilment, $request);

        $this->handle($palletReturn, $this->validatedData);
    }

    public function action(PalletReturn $palletReturn, $modelData): void
    {
        $this->action             = true;
        $this->fulfilmentCustomer = $palletReturn->fulfilmentCustomer;
        $this->initialisationFromFulfilment($palletReturn->fulfilment, $modelData);

        $this->handle($palletReturn, $this->validatedData);
    }
}
