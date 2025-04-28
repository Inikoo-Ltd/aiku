<?php

/*
 * author Arya Permana - Kirin
 * created on 17-01-2025-09h-21m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\CRM;

use App\Actions\CRM\Customer\DeleteCustomerDeliveryAddress;
use App\Actions\RetinaAction;
use App\Models\CRM\Customer;
use App\Models\Helpers\Address;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

class DeleteRetinaCustomerDeliveryAddress extends RetinaAction
{
    protected Address $address;
    public function handle(Customer $customer, Address $address): Customer
    {
        return DeleteCustomerDeliveryAddress::run($customer, $address);
    }

    public function afterValidator(Validator $validator): void
    {

        if (DB::table('model_has_addresses')->where('address_id', $this->address->id)->where('model_type', '!=', 'Customer')->exists()) {
            abort(419);
        }

    }

    public function asController(Customer $customer, Address $address, ActionRequest $request): void
    {
        $this->address = $address;
        $customer      = $request->user()->customer;

        $this->initialisation($request);

        $this->handle($customer, $address);
    }

    public function action(Customer $customer, Address $address): Customer
    {
        $this->asAction = true;
        $this->address = $address;
        $this->initialisationFulfilmentActions($customer->fulfilmentCustomer, []);

        return $this->handle($customer, $address);
    }
}
