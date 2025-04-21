<?php

/*
 * author Arya Permana - Kirin
 * created on 17-01-2025-09h-26m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\CRM;

use App\Actions\Dropshipping\CustomerClient\StoreCustomerClient;
use App\Actions\RetinaAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithModelAddressActions;
use App\Models\CRM\Customer;
use App\Models\CRM\WebUser;
use App\Models\Dropshipping\CustomerClient;
use App\Models\Dropshipping\Platform;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class StoreRetinaCustomerClient extends RetinaAction
{
    use WithModelAddressActions;
    use WithNoStrictRules;

    protected Customer $customer;


    public function handle(Customer $customer, array $modelData): CustomerClient
    {
        return StoreCustomerClient::run($customer, $modelData);
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
        return StoreCustomerClient::make()->getBaseRules($this->customer);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::route('retina.dropshipping.client.index');
    }

    /**
     * @throws \Throwable
     */
    public function asController(ActionRequest $request): CustomerClient
    {
        $this->initialisation($request);
        return $this->handle($this->customer, $this->validatedData);
    }

    public function inPlatform(Platform $platform, ActionRequest $request): CustomerClient
    {
        $this->set('platform_id', $platform->id);
        $this->initialisation($request);
        return $this->handle($this->customer, $this->validatedData);
    }

    public function action(Customer $customer, array $modelData): CustomerClient
    {
        $this->asAction = true;
        $this->initialisationFulfilmentActions($customer->fulfilmentCustomer, $modelData);
        return $this->handle($customer, $this->validatedData);
    }


}
