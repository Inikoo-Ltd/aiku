<?php
/*
 * author Arya Permana - Kirin
 * created on 08-04-2025-11h-58m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Fulfilment\PalletDelivery;

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

class DeleteBookedInPalletDelivery extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    private bool $action = false;

    public function handle(PalletDelivery $palletDelivery, array $modelData = []): void
    {
        if(strtolower(trim($modelData['delete_confirmation'] ?? '')) === strtolower($palletDelivery->reference)) 
        {   
            $palletDelivery->pallets()->delete();

            foreach($palletDelivery->transactions as $transaction){
                $recurringBillTransaction = $transaction->recurringBillTransaction;
    
                $transaction->delete();
        
                if ($recurringBillTransaction) {
                    DeleteRecurringBillTransaction::make()->action($recurringBillTransaction);
                }
            }

            $this->update($palletDelivery, [
                'delete_comment' => Arr::get($modelData, 'delete_comment')
            ]);

            $fulfilmentCustomer = $palletDelivery->fulfilmentCustomer;

            $fulfilmentCustomer->customer->auditEvent    = 'delete';
            $fulfilmentCustomer->customer->isCustomEvent = true;

            $fulfilmentCustomer->customer->auditCustomOld = [
                'delivery' => $palletDelivery->reference
            ];

            $fulfilmentCustomer->customer->auditCustomNew = [
                'delivery' => __("The delivery has been deleted due to: $palletDelivery->delete_comment.")
            ];

            Event::dispatch(AuditCustom::class, [$fulfilmentCustomer->customer]);

            $fulfilmentCustomer = $palletDelivery->fulfilmentCustomer;

            $palletDelivery->delete();

            $fulfilmentCustomer->refresh();
            FulfilmentCustomerHydratePalletDeliveries::dispatch($fulfilmentCustomer);
            FulfilmentCustomerHydratePallets::dispatch($fulfilmentCustomer);
        } else {
            abort(419);
        }
    }

    public function rules(): array
    {
        return [
            'delete_comment' => ['sometimes', 'required'],
            'delete_confirmation' => ['required']
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->action) {
            return true;
        }

        return false;
    }

    public function asController(PalletDelivery $palletDelivery, ActionRequest $request): void
    {
        $this->initialisationFromFulfilment($palletDelivery->fulfilment, $request);

        $this->handle($palletDelivery, $this->validatedData);
    }

    public function action(PalletDelivery $palletDelivery, $modelData): void
    {
        $this->action = true;
        $this->initialisationFromFulfilment($palletDelivery->fulfilment, $modelData);

        $this->handle($palletDelivery, $this->validatedData);
    }
}
