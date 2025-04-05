<?php

/*
 * author Arya Permana - Kirin
 * created on 27-03-2025-09h-31m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Fulfilment\FulfilmentCustomer;

use App\Actions\CRM\Customer\AddDeliveryAddressToCustomer;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Traits\WithModelAddressActions;
use App\Models\CRM\Customer;
use App\Models\CRM\WebUser;
use App\Rules\ValidAddress;
use Lorisleiva\Actions\ActionRequest;

class AddRetinaCustomerDeliveryAddress extends RetinaAction
{
    use WithActionUpdate;
    use WithModelAddressActions;

    public function handle(Customer $customer, array $modelData): Customer
    {
        AddDeliveryAddressToCustomer::make()->action($customer, $modelData);
        $customer->refresh();

        return $customer;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        if ($request->user() instanceof WebUser) {
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

    public function asController(ActionRequest $request): Customer
    {
        $customer = $request->user()->customer;

        $this->initialisation($request);

        return $this->handle($customer, $this->validatedData);
    }
}
