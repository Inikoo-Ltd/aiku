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
use App\Actions\Fulfilment\RecurringBillTransaction\DeleteRecurringBillTransaction;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Fulfilment\PalletDelivery\PalletDeliveryStateEnum;
use App\Models\CRM\Customer;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\PalletDelivery;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Event;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use OwenIt\Auditing\Events\AuditCustom;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class DeleteBookedInPalletDelivery extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    private bool $action = false;
    private PalletDelivery $palletDelivery;

    public function handle(PalletDelivery $palletDelivery, array $modelData = []): FulfilmentCustomer
    {
        $fulfilmentCustomer = $palletDelivery->fulfilmentCustomer;

            DB::transaction(function () use ($palletDelivery, $fulfilmentCustomer) {
                $palletDelivery->pallets()->delete();

                foreach($palletDelivery->transactions as $transaction){
                    $recurringBillTransaction = $transaction->recurringBillTransaction;
        
                    $transaction->delete();
            
                    if ($recurringBillTransaction) {
                        DeleteRecurringBillTransaction::make()->action($recurringBillTransaction);
                    }
                }

                $fulfilmentCustomer->customer->auditEvent    = 'delete';
                $fulfilmentCustomer->customer->isCustomEvent = true;
                $fulfilmentCustomer->customer->auditCustomOld = [
                    'delivery' => $palletDelivery->reference
                ];
                $fulfilmentCustomer->customer->auditCustomNew = [
                    'delivery' => __("The delivery :ref has been deleted.", ['ref' => $palletDelivery->reference])
                ];
                Event::dispatch(AuditCustom::class, [$fulfilmentCustomer->customer]);


                if($palletDelivery->recurringBill)
                {
                    $palletDelivery->recurringBill->auditEvent    = 'delete';
                    $palletDelivery->recurringBill->isCustomEvent = true;
                    $palletDelivery->recurringBill->auditCustomOld = [
                        'delivery' => $palletDelivery->reference
                    ];
                    $palletDelivery->recurringBill->auditCustomNew = [
                        'delivery' => __("The delivery :ref has been deleted.", ['ref' => $palletDelivery->reference])
                    ];
                    Event::dispatch(AuditCustom::class, [$palletDelivery->recurringBill]);
                }

                SendPalletDeliveryDeletedNotification::dispatch($palletDelivery);
                $palletDelivery->delete();
            });

        $fulfilmentCustomer->refresh();
        FulfilmentCustomerHydratePalletDeliveries::dispatch($fulfilmentCustomer);
        FulfilmentCustomerHydratePallets::dispatch($fulfilmentCustomer);

        return $fulfilmentCustomer;
    }

    public function htmlResponse(FulfilmentCustomer $fulfilmentCustomer): RedirectResponse
    {
        return Redirect::route('grp.org.fulfilments.show.crm.customers.show.pallet_deliveries.index', [
            'organisation' => $fulfilmentCustomer->organisation->slug,
            'fulfilment' => $fulfilmentCustomer->fulfilment->slug,
            'fulfilmentCustomer' => $fulfilmentCustomer->slug
        ]);
    }

    public function rules(): array
    {
        return [
            'delete_confirmation' => ['sometimes']
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->action) {
            return true;
        }

        return true;
    }

    public function afterValidator()
    {
        if(strtolower(trim($this->get('delete_confirmation'))) != strtolower($this->palletDelivery->reference)) {
            abort(419);
        }
    }

    public function asController(PalletDelivery $palletDelivery, ActionRequest $request): FulfilmentCustomer
    {
        $this->palletDelivery = $palletDelivery;
        $this->initialisationFromFulfilment($palletDelivery->fulfilment, $request);

        return $this->handle($palletDelivery, $this->validatedData);
    }

    public function action(PalletDelivery $palletDelivery, $modelData): void
    {
        $this->action = true;
        $this->palletDelivery = $palletDelivery;
        $this->initialisationFromFulfilment($palletDelivery->fulfilment, $modelData);

        $this->handle($palletDelivery, $this->validatedData);
    }
}
