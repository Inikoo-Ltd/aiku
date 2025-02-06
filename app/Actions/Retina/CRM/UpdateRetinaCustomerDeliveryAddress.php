<?php

/*
 * author Arya Permana - Kirin
 * created on 17-01-2025-09h-19m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\CRM;

use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\CRM\Customer;
use Lorisleiva\Actions\ActionRequest;

class UpdateRetinaCustomerDeliveryAddress extends RetinaAction
{
    use WithActionUpdate;
    private bool $action = false;
    public function handle(Customer $customer, array $modelData): Customer
    {
        if (isset($modelData['delivery_address_id'])) {
            $customer->delivery_address_id = $modelData['delivery_address_id'];
            $customer->save();
        }
        return $customer;
    }

    public function rules(): array
    {
        $rules = [
            'delivery_address_id'         => ['sometimes', 'nullable', 'exists:addresses,id'],
        ];

        return $rules;
    }

    public function fromRetina(Customer $customer, ActionRequest $request): Customer
    {
        $customer = $request->user()->customer;

        $this->initialisation($request);

        return $this->handle($customer, $this->validatedData);
    }

    public function action(Customer $customer, array $modelData): Customer
    {
        $this->initialisationFulfilmentActions($customer->fulfilmentCustomer, $modelData); // TODO: Raul please do the permission for the web user($request);

        return $this->handle($customer, $this->validatedData);
    }
}
