<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Sept 2024 11:46:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\CustomerClient;

use App\Actions\CRM\Customer\Hydrators\CustomerHydrateClients;
use App\Actions\Dropshipping\CustomerClient\Search\CustomerClientRecordSearch;
use App\Actions\Dropshipping\CustomerSalesChannel\Hydrators\CustomerSalesChannelsHydrateCustomerClients;
use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithModelAddressActions;
use App\Models\CRM\Customer;
use App\Models\CRM\WebUser;
use App\Models\Dropshipping\CustomerClient;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Rules\IUnique;
use App\Rules\ValidAddress;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;
use Lorisleiva\Actions\ActionRequest;

class StoreCustomerClient extends OrgAction
{
    use WithModelAddressActions;
    use WithNoStrictRules;

    private Customer $customer;

    /**
     * @throws \Throwable
     */
    public function handle(CustomerSalesChannel $customerSalesChannel, array $modelData): CustomerClient
    {
        $address = Arr::get($modelData, 'address');
        Arr::forget($modelData, 'address');

        data_set($modelData, 'ulid', Str::ulid());
        data_set($modelData, 'group_id', $customerSalesChannel->group_id);
        data_set($modelData, 'organisation_id', $customerSalesChannel->organisation_id);
        data_set($modelData, 'shop_id', $customerSalesChannel->shop_id);
        data_set($modelData, 'platform_id', $customerSalesChannel->platform_id);
        data_set($modelData, 'customer_id', $customerSalesChannel->customer_id);


        $customerClient = DB::transaction(function () use ($customerSalesChannel, $modelData, $address) {
            /** @var CustomerClient $customerClient */
            $customerClient = $customerSalesChannel->clients()->create($modelData);
            $customerClient->stats()->create();

            return $this->addAddressToModelFromArray(
                model: $customerClient,
                addressData: $address,
                scope: 'delivery',
                canShip: true
            );
        });


        CustomerClientRecordSearch::dispatch($customerClient)->delay($this->hydratorsDelay);
        CustomerHydrateClients::dispatch($customerSalesChannel->customer)->delay($this->hydratorsDelay);
        CustomerSalesChannelsHydrateCustomerClients::dispatch($customerSalesChannel);

        return $customerClient;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        if ($request->user() instanceof WebUser) {
            return true;
        }


        return $request->user()->authTo("crm.{$this->shop->id}.edit");
    }

    public function getBaseRules(Customer $customer): array
    {
        return [

            'reference'      => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
                new IUnique(
                    table: 'customer_clients',
                    extraConditions: [
                        ['column' => 'customer_id', 'value' => $customer->id],
                    ]
                ),
            ],
            'contact_name'   => ['nullable', 'string', 'max:255'],
            'company_name'   => ['nullable', 'string', 'max:255'],
            'email'          => ['nullable', 'email'],
            'phone'          => ['nullable', 'string', 'min:6'],
            'address'        => ['required', new ValidAddress()],
            'deactivated_at' => ['sometimes', 'nullable', 'date'],
            'status'         => ['sometimes', 'boolean'],

        ];
    }

    public function rules(): array
    {
        $rules = $this->getBaseRules($this->customer);

        if (!$this->strict) {
            $rules          = $this->noStrictStoreRules($rules);
            $rules['email'] = ['sometimes', 'nullable', 'string', 'max:255'];
            $rules['phone'] = ['sometimes', 'nullable', 'string', 'max:255'];
        }

        return $rules;
    }

    public function htmlResponse(CustomerClient $customerClient): RedirectResponse
    {
        if (request()->user() instanceof WebUser) {
            return Redirect::route('retina.dropshipping.customer_clients.index');
        }

        return Redirect::route(
            'grp.org.shops.show.crm.customers.show.customer_sales_channels.show.customer_clients.index',
            [$customerClient->customer->organisation->slug, $customerClient->shop->slug, $customerClient->customer->slug, $customerClient->platform->slug]
        );
    }

    /**
     * @throws \Throwable
     */
    public function action(CustomerSalesChannel $customerSalesChannel, array $modelData, int $hydratorsDelay = 0, bool $strict = true, $audit = true): CustomerClient
    {
        if (!$audit) {
            CustomerClient::disableAuditing();
        }
        $this->customer       = $customerSalesChannel->customer;
        $this->asAction       = true;
        $this->strict         = $strict;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFromShop($customerSalesChannel->shop, $modelData);

        return $this->handle($customerSalesChannel, $this->validatedData);
    }


    /**
     * @throws \Throwable
     */
    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): CustomerClient
    {
        $this->customer = $customerSalesChannel->customer;
        $this->initialisationFromShop($customerSalesChannel->shop, $request);

        return $this->handle($customerSalesChannel, $this->validatedData);
    }

}
