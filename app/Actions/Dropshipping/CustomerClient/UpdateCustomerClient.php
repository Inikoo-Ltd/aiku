<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Sept 2024 11:46:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\CustomerClient;

use App\Actions\Dropshipping\CustomerClient\Search\CustomerClientRecordSearch;
use App\Actions\Helpers\Address\UpdateAddress;
use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Traits\WithModelAddressActions;
use App\Http\Resources\CRM\CustomerClientResource;
use App\Models\Dropshipping\CustomerClient;
use App\Rules\IUnique;
use App\Rules\Phone;
use App\Rules\ValidAddress;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class UpdateCustomerClient extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;
    use WithModelAddressActions;

    private CustomerClient $customerClient;

    public function handle(CustomerClient $customerClient, array $modelData): CustomerClient
    {
        $addressChange = [];
        if (Arr::has($modelData, 'address')) {
            $addressData = Arr::pull($modelData, 'address');

            if ($customerClient->address) {
                $address = UpdateAddress::run($customerClient->address, $addressData);
                data_set($modelData, 'location', $address->getLocation());

                $addressChange = $address->getChanges();
            } else {
                $this->addAddressToModelFromArray(
                    model: $customerClient,
                    addressData: $addressData,
                    scope: 'delivery',
                    canShip: true
                );
                $addressChange = ['new' => true];
            }
        }

        $customerClient = $this->update($customerClient, $modelData, ['data']);

        $changes = Arr::except($customerClient->getChanges(), ['updated_at', 'last_fetched_at']);

        if (count($changes) > 0 || count($addressChange) > 0) {
            CustomerClientRecordSearch::dispatch($customerClient);
        }

        return $customerClient;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("crm.{$this->shop->id}.edit");
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
            'status'         => ['sometimes', 'boolean'],
            'contact_name'   => ['sometimes', 'nullable', 'string', 'max:255'],
            'company_name'   => ['sometimes', 'nullable', 'string', 'max:255'],
            'email'          => ['sometimes', 'nullable', 'email'],
            'phone'          => ['sometimes', 'nullable', new Phone()],
            'address'        => ['sometimes', new ValidAddress()],
            'deactivated_at' => ['sometimes', 'nullable', 'date'],
        ];

        if (!$this->strict) {
            $rules['phone']       = ['sometimes', 'nullable', 'string', 'max:255'];
            $rules['email']       = ['sometimes', 'nullable', 'string', 'max:255'];
            $rules['reference']   = ['sometimes', 'nullable', 'string', 'max:255'];
            $rules['platform_id'] = ['sometimes', 'integer'];
            $rules                = $this->noStrictUpdateRules($rules);
        }

        return $rules;
    }

    public function asController(CustomerClient $customerClient, ActionRequest $request): CustomerClient
    {
        $this->customerClient = $customerClient;
        $this->initialisationFromShop($customerClient->shop, $request);

        return $this->handle($customerClient, $this->validatedData);
    }

    public function action(CustomerClient $customerClient, array $modelData, int $hydratorsDelay = 0, bool $strict = true, bool $audit = true): CustomerClient
    {
        $this->strict = $strict;
        if (!$audit) {
            CustomerClient::disableAuditing();
        }
        $this->customerClient = $customerClient;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->asAction       = true;


        $this->initialisationFromShop($customerClient->shop, $modelData);

        return $this->handle($customerClient, $this->validatedData);
    }


    public function jsonResponse(CustomerClient $customerClient): CustomerClientResource
    {
        return new CustomerClientResource($customerClient);
    }
}
