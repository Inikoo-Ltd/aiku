<?php

/*
 * author Arya Permana - Kirin
 * created on 17-01-2025-09h-30m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\SysAdmin;

use App\Actions\CRM\Customer\AddDeliveryAddressToCustomer;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Traits\WithModelAddressActions;
use App\Http\Resources\Fulfilment\FulfilmentCustomerResource;
use App\Models\CRM\WebUser;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Rules\ValidAddress;
use Lorisleiva\Actions\ActionRequest;

class AddRetinaDeliveryAddressToFulfilmentCustomer extends RetinaAction
{
    use WithActionUpdate;
    use WithModelAddressActions;

    private bool $action = false;

    public function handle(FulfilmentCustomer $fulfilmentCustomer, array $modelData): FulfilmentCustomer
    {
        AddDeliveryAddressToCustomer::make()->action($fulfilmentCustomer->customer, $modelData);
        $fulfilmentCustomer->refresh();

        return $fulfilmentCustomer;
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
            'delivery_address' => ['required', new ValidAddress],
        ];
    }

    public function asController(FulfilmentCustomer $fulfilmentCustomer, ActionRequest $request): FulfilmentCustomer
    {
        $fulfilmentCustomer = $request->user()->customer->fulfilmentCustomer;

        $this->initialisation($request);

        return $this->handle($fulfilmentCustomer, $this->validatedData);
    }

    public function action(FulfilmentCustomer $fulfilmentCustomer, array $modelData): FulfilmentCustomer
    {
        $this->action = true;
        $this->initialisationFulfilmentActions($fulfilmentCustomer, $modelData); // TODO: Raul please do the permission for the web user($request);

        return $this->handle($fulfilmentCustomer, $this->validatedData);
    }

    public function jsonResponse(FulfilmentCustomer $fulfilmentCustomer): FulfilmentCustomerResource
    {
        return new FulfilmentCustomerResource($fulfilmentCustomer);
    }
}
