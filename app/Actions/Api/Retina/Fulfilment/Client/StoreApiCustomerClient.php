<?php

/*
 * author Arya Permana - Kirin
 * created on 13-05-2025-09h-57m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Api\Retina\Fulfilment\Client;

use App\Actions\Api\Retina\Fulfilment\Resource\CustomerClientApiResource;
use App\Actions\Dropshipping\CustomerClient\StoreCustomerClient;
use App\Actions\RetinaApiAction;
use App\Models\Dropshipping\CustomerClient;
use App\Models\Dropshipping\CustomerSalesChannel;
use Lorisleiva\Actions\ActionRequest;

class StoreApiCustomerClient extends RetinaApiAction
{
    /**
     * @throws \Throwable
     */
    public function handle(CustomerSalesChannel $customerSalesChannel, array $clientData = []): CustomerClient
    {
        return StoreCustomerClient::make()->action($customerSalesChannel, $clientData);
    }

    public function rules(): array
    {
        return StoreCustomerClient::make()->getBaseRules($this->customer);
    }

    /**
     * @throws \Throwable
     */
    public function asController(ActionRequest $request): CustomerClient
    {
        $this->initialisationFromFulfilment($request);

        return $this->handle($this->customerSalesChannel, $this->validatedData);
    }

    public function jsonResponse(CustomerClient $customerClient): CustomerClientApiResource
    {
        return CustomerClientApiResource::make($customerClient)
            ->additional([
                'message' => __('Client created successfully'),
            ]);
    }
}
