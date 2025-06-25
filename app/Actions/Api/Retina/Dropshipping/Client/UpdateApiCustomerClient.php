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
use App\Rules\IUnique;
use App\Rules\Phone;
use App\Rules\ValidAddress;
use Lorisleiva\Actions\ActionRequest;

class UpdateApiCustomerClient extends RetinaApiAction
{
    private CustomerClient $customerClient;
    /**
     * @throws \Throwable
     */
    public function handle(CustomerClient $customerClient, array $clientData = []): CustomerClient
    {
        return UpdateCustomerClient::make()->action($customerClient, $clientData);
    }

    public function rules(): array
    {
        $rules = [
            'reference'      => [
                'sometimes',
                'nullable',
                'string',
                'max:255',

                new IUnique(
                    table: 'customer_clients',
                    extraConditions: [
                        [
                            'column' => 'customer_id',
                            'value'  => $this->customerClient->customer_id
                        ],
                        ['column' => 'id', 'value' => $this->customerClient->id, 'operator' => '!=']
                    ]
                ),

            ],
            'contact_name'   => ['sometimes', 'nullable', 'string', 'max:255'],
            'company_name'   => ['sometimes', 'nullable', 'string', 'max:255'],
            'email'          => ['sometimes', 'nullable', 'email'],
            'phone'          => ['sometimes', 'nullable', new Phone()],
            'address'        => ['sometimes', new ValidAddress()],
        ];

        return $rules;
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

    public function jsonResponse(CustomerClient $customerClient): CustomerClientApiResource
    {
        return CustomerClientApiResource::make($customerClient)
            ->additional([
                'message' => __('Client updated successfully'),
            ]);
    }
}
