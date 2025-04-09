<?php

/*
 * author Arya Permana - Kirin
 * created on 07-04-2025-14h-47m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\SysAdmin;

use App\Actions\CRM\Customer\AddDeliveryAddressToCustomer;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Traits\WithModelAddressActions;
use App\Http\Resources\Fulfilment\FulfilmentCustomerResource;
use App\Models\CRM\Customer;
use App\Models\CRM\WebUser;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Rules\ValidAddress;
use Lorisleiva\Actions\ActionRequest;

class AddRetinaDeliveryAddressToCustomer extends RetinaAction
{
    use WithActionUpdate;
    use WithModelAddressActions;

    private bool $action = false;
    public function handle(Customer $customer, array $modelData): Customer
    {
        AddDeliveryAddressToCustomer::make()->action($customer, $modelData);
        $customer->refresh();
        return $customer;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->action) {
            return true;
        }

        if ($request->user() instanceof WebUser) {
            // TODO: Raul please do the permission for the web user
            return true;
        }

        return false;
    }

    public function rules(): array
    {
        return [
            'delivery_address'         => ['required', new ValidAddress()],
        ];
    }

    public function asController(Customer $customer, ActionRequest $request): Customer
    {
        $this->initialisation($request);
        return $this->handle($this->customer, $this->validatedData);
    }

    public function action(Customer $customer, array $modelData): Customer
    {
        $this->asAction = true;
        $this->initialisationFulfilmentActions($customer->fulfilmentCustomer, $modelData);

        return $this->handle($customer, $this->validatedData);
    }


    public function jsonResponse(FulfilmentCustomer $fulfilmentCustomer): FulfilmentCustomerResource
    {
        return new FulfilmentCustomerResource($fulfilmentCustomer);
    }
}
