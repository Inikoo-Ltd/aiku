<?php

/*
 * author Arya Permana - Kirin
 * created on 17-01-2025-09h-26m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Fulfilment\Client;

use App\Actions\Dropshipping\CustomerClient\StoreCustomerClient;
use App\Actions\RetinaAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithModelAddressActions;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\CustomerClient;
use App\Models\Dropshipping\CustomerSalesChannel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class StoreRetinaFulfilmentCustomerClient extends RetinaAction
{
    use WithModelAddressActions;
    use WithNoStrictRules;

    protected Customer $customer;
    /**
     * @var \App\Models\Dropshipping\CustomerSalesChannel
     */
    private CustomerSalesChannel $scope;


    public function handle(CustomerSalesChannel $customerSalesChannel, array $modelData): CustomerClient
    {
        return StoreCustomerClient::make()->action($customerSalesChannel, $modelData);
    }

    public function authorize(ActionRequest $request): bool
    {
        $customerSalesChannel = $request->route('customerSalesChannel');
        if ($customerSalesChannel->customer_id !== $this->customer->id) {
            return false;
        }

        return true;
    }

    public function rules(): array
    {
        return StoreCustomerClient::make()->getBaseRules($this->customer);
    }

    public function htmlResponse(CustomerClient $customerClient): RedirectResponse
    {
        return Redirect::route('retina.fulfilment.dropshipping.customer_sales_channels.client.index', [
            'customerSalesChannel' => $this->scope->slug
        ]);
    }

    /**
     * @throws \Throwable
     */
    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): CustomerClient
    {
        $this->scope = $customerSalesChannel;
        $this->initialisation($request);

        return $this->handle($customerSalesChannel, $this->validatedData);
    }


}
