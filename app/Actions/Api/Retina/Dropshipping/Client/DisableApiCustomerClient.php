<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 25-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Api\Retina\Dropshipping\Client;

use App\Actions\Api\Retina\Dropshipping\Resource\CustomerClientApiResource;
use App\Actions\Dropshipping\CustomerClient\UpdateCustomerClient;
use App\Actions\RetinaApiAction;
use App\Models\Dropshipping\CustomerClient;
use Lorisleiva\Actions\ActionRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

class DisableApiCustomerClient extends RetinaApiAction
{
    private CustomerClient $customerClient;
    /**
     * @throws \Throwable
     */
    public function handle(CustomerClient $customerClient, array $modelData = []): CustomerClient
    {
        $modelData = array_merge($modelData, [
            'status' => false,
            'deactivated_at' => Carbon::now(),
        ]);

        return UpdateCustomerClient::make()->action($customerClient, $modelData);
    }

    /**
     * @throws \Throwable
     */
    public function asController(CustomerClient $customerClient, ActionRequest $request): CustomerClient
    {
        $this->customerClient = $customerClient;
        $this->initialisationFromDropshipping($request);

        return $this->handle($customerClient, $this->validatedData);
    }

    public function afterValidator(Validator $validator): void
    {

        if ($this->customerClient->status === false) {
            $validator->errors()->add('message', __('Client is already disabled.'));
        }

        if ($this->customerClient->orders()->exists()) {
            $validator->errors()->add('message', __('Cannot disable client with existing orders.'));
        }
    }

    public function jsonResponse(CustomerClient $customerClient): CustomerClientApiResource
    {
        return CustomerClientApiResource::make($customerClient)
            ->additional([
                'message' => __('Client disabled successfully'),
            ]);
    }
}
