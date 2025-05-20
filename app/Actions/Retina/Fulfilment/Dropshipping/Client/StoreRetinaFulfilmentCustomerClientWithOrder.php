<?php
/*
 * author Arya Permana - Kirin
 * created on 20-05-2025-15h-18m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Fulfilment\Dropshipping\Client;

use App\Actions\Dropshipping\CustomerClient\StoreCustomerClient;
use App\Actions\Retina\Fulfilment\PalletReturn\StoreRetinaPlatformPalletReturn;
use App\Actions\RetinaAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithModelAddressActions;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\CustomerClient;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Fulfilment\PalletReturn;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class StoreRetinaFulfilmentCustomerClientWithOrder extends RetinaAction
{
    use WithModelAddressActions;
    use WithNoStrictRules;

    protected Customer $customer;
    /**
     * @var \App\Models\Dropshipping\CustomerSalesChannel
     */
    private CustomerSalesChannel $scope;


    public function handle(CustomerSalesChannel $customerSalesChannel, array $modelData): PalletReturn
    {
        $customerClient =  StoreCustomerClient::make()->action($customerSalesChannel, $modelData);

        $palletReturn = StoreRetinaPlatformPalletReturn::make()->action($customerClient, $modelData);

        return $palletReturn;
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

    public function htmlResponse(PalletReturn $palletReturn, ActionRequest $request): RedirectResponse
    {
        return  Redirect::route('retina.fulfilment.dropshipping.customer_sales_channels.basket.show', [
            'customerSalesChannel' => $palletReturn->customerSaleChannel->slug,
            'palletReturn' => $palletReturn->slug
        ]);
    }

    /**
     * @throws \Throwable
     */
    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): PalletReturn
    {
        $this->scope = $customerSalesChannel;
        $this->initialisation($request);

        return $this->handle($customerSalesChannel, $this->validatedData);
    }


}
