<?php

/*
 * author Arya Permana - Kirin
 * created on 08-04-2025-11h-58m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Fulfilment\PalletDelivery;

use App\Actions\Comms\Email\SendPalletDeliveryDeletedNotification;
use App\Actions\Fulfilment\FulfilmentCustomer\Hydrators\FulfilmentCustomerHydratePalletDeliveries;
use App\Actions\Fulfilment\FulfilmentCustomer\Hydrators\FulfilmentCustomerHydratePallets;
use App\Actions\Fulfilment\FulfilmentTransaction\DeleteFulfilmentTransaction;
use App\Actions\Fulfilment\Pallet\DeletePallet;
use App\Actions\Fulfilment\Pallet\DeleteStoredPallet;
use App\Actions\Fulfilment\RecurringBillTransaction\DeleteRecurringBillTransaction;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithFulfilmentShopSupervisorAuthorisation;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Fulfilment\Pallet\PalletStatusEnum;
use App\Enums\Fulfilment\RecurringBill\RecurringBillStatusEnum;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\PalletDelivery;
use Illuminate\Support\Facades\Event;
use Lorisleiva\Actions\ActionRequest;
use OwenIt\Auditing\Events\AuditCustom;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DeleteBookedInPalletDelivery extends OrgAction
{
    use WithActionUpdate;
    use  WithFulfilmentShopSupervisorAuthorisation;

    private bool $action = false;
    private PalletDelivery $palletDelivery;

    /**
     * @throws \Throwable
     */
    public function handle(PalletDelivery $palletDelivery, array $modelData): FulfilmentCustomer
    {
        $fulfilmentCustomer = $palletDelivery->fulfilmentCustomer;

        $recurringBill = $palletDelivery->recurringBill;

        DB::transaction(function () use ($palletDelivery, $fulfilmentCustomer, $recurringBill, $modelData) {

            $palletDelivery = $this->update($palletDelivery, $modelData);

            foreach ($palletDelivery->pallets as $pallet) {
                if (in_array($pallet->state, [
                    PalletStatusEnum::IN_PROCESS,
                    PalletStatusEnum::RECEIVING,
                    PalletStatusEnum::NOT_RECEIVED
                ])) {
                    DeletePallet::run($pallet);  // this will NOT delete recurring bill transactions
                } else {
                    DeleteStoredPallet::make()->action(pallet: $pallet, modelData: [
                        'deleted_note' => 'Pallet Delivery deleted due to: ' . $modelData['deleted_note'],
                        'deleted_by'   => $modelData['deleted_by']
                    ]); // this will delete recurring bill transactions
                }
            }

            foreach ($palletDelivery->transactions as $transaction) {
                DeleteFulfilmentTransaction::make()->action($transaction);
                if ($recurringBill && $recurringBill->status == RecurringBillStatusEnum::CURRENT && $transaction->recurringBillTransaction) {
                    DeleteRecurringBillTransaction::make()->action($transaction->recurringBillTransaction);
                }
            }

            $palletDelivery->delete();

            $fulfilmentCustomer->customer->auditEvent     = 'delete';
            $fulfilmentCustomer->customer->isCustomEvent  = true;
            $fulfilmentCustomer->customer->auditCustomOld = [
                'delivery' => $palletDelivery->reference
            ];
            $fulfilmentCustomer->customer->auditCustomNew = [
                'delivery' => __("The delivery :ref has been deleted.", ['ref' => $palletDelivery->reference])
            ];
            Event::dispatch(new AuditCustom($fulfilmentCustomer->customer));
        });

        StoreDeletePalletDeliveryHistory::run($palletDelivery, $palletDelivery->fulfilmentCustomer->customer);
        SendPalletDeliveryDeletedNotification::dispatch($palletDelivery);


        $fulfilmentCustomer->refresh();
        FulfilmentCustomerHydratePalletDeliveries::dispatch($fulfilmentCustomer);
        FulfilmentCustomerHydratePallets::dispatch($fulfilmentCustomer);

        return $fulfilmentCustomer;
    }

    public function htmlResponse(FulfilmentCustomer $fulfilmentCustomer): RedirectResponse
    {
        return Redirect::route('grp.org.fulfilments.show.crm.customers.show.pallet_deliveries.index', [
            'organisation'       => $fulfilmentCustomer->organisation->slug,
            'fulfilment'         => $fulfilmentCustomer->fulfilment->slug,
            'fulfilmentCustomer' => $fulfilmentCustomer->slug
        ]);
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
    public function asController(PalletDelivery $palletDelivery, ActionRequest $request): FulfilmentCustomer
    {
        $this->palletDelivery = $palletDelivery;
        $this->initialisationFromFulfilment($palletDelivery->fulfilment, $request);

        return $this->handle($palletDelivery, $this->validatedData);
    }

    /**
     * @throws \Throwable
     */
    public function action(PalletDelivery $palletDelivery, $modelData): void
    {
        $this->action         = true;
        $this->palletDelivery = $palletDelivery;
        $this->initialisationFromFulfilment($palletDelivery->fulfilment, $modelData);

        $this->handle($palletDelivery, $this->validatedData);
    }
}
