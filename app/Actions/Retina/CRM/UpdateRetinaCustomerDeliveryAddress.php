<?php

/*
 * author Arya Permana - Kirin
 * created on 17-01-2025-09h-19m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\CRM;

use App\Actions\CRM\Customer\UpdateCustomerDeliveryAddress;
use App\Actions\RetinaAction;
use App\Models\CRM\Customer;
use Lorisleiva\Actions\ActionRequest;

class UpdateRetinaCustomerDeliveryAddress extends RetinaAction
{
    public function handle(Customer $customer, array $modelData): Customer
    {
        return UpdateCustomerDeliveryAddress::run($customer, $modelData);

    }

    public function rules(): array
    {
        return [
            'delivery_address_id'         => ['sometimes', 'nullable', 'exists:addresses,id'],
        ];
    }

    public function asController(Customer $customer, ActionRequest $request): void
    {
        $this->initialisation($request);

        $this->handle($this->customer, $this->validatedData);
    }

    public function action(Customer $customer, array $modelData): Customer
    {
        $this->asAction = true;
        $this->initialisationFulfilmentActions($customer->fulfilmentCustomer, $modelData);

        return $this->handle($customer, $this->validatedData);
    }
}
