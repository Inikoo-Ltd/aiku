<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Fri, 27 Jun 2025 11:04:20 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Magento;

use App\Actions\Dropshipping\CustomerSalesChannel\StoreCustomerSalesChannel;
use App\Actions\Dropshipping\CustomerSalesChannel\UpdateCustomerSalesChannel;
use App\Actions\OrgAction;
use App\Enums\Dropshipping\CustomerSalesChannelStateEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\MagentoUser;
use App\Models\Dropshipping\Platform;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreMagentoUser extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithMagentoApiRequest;

    public function handle(Customer $customer, array $modelData): MagentoUser
    {
        $platform = Platform::where('type', PlatformTypeEnum::MAGENTO->value)->first();
        $username = Arr::get($modelData, 'username');
        $password = Arr::get($modelData, 'password');
        $storeUrl = Arr::pull($modelData, 'url');

        if ($customer->magentoUsers()->whereJsonContains('settings->credentials->base_url', $storeUrl)->exists()) {
            throw ValidationException::withMessages(['username' => __('The store already exists.')]);
        }

        $validated = $this->validateMagentoAccount($username, $password, $storeUrl);

        if (! $validated) {
            throw ValidationException::withMessages(['username' => __('The credentials provided is invalid.')]);
        }

        data_set($modelData, 'group_id', $customer->group_id);
        data_set($modelData, 'organisation_id', $customer->organisation_id);
        data_set($modelData, 'name', Arr::get($modelData, 'username'));
        data_set($modelData, 'platform_id', $platform->id);
        data_set($modelData, 'settings.credentials.base_url', $storeUrl);

        /** @var MagentoUser $magentoUser */
        $magentoUser = $customer->magentoUsers()->create($modelData);

        $customerSalesChannel = StoreCustomerSalesChannel::make()->action($customer, $platform, [
            'platform_user_type' => class_basename($magentoUser),
            'platform_user_id' => $magentoUser->id,
            'reference' => $magentoUser->name,
            'name' => $magentoUser->name
        ]);

        $magentoUser->refresh();
        $accessToken = $magentoUser->getMagentoToken();
        $stores = $magentoUser->getStores();

        $magentoUser->update([
            'customer_sales_channel_id' => $customerSalesChannel->id,
            'settings' => [
                'credentials' => [
                    ...Arr::get($magentoUser->settings, 'credentials'),
                    'access_token' => $accessToken
                ]
            ],
            'name' => Arr::get($stores, '0.name')
        ]);

        UpdateCustomerSalesChannel::run($customerSalesChannel, [
            'state' => CustomerSalesChannelStateEnum::AUTHENTICATED
        ]);

        return $magentoUser;
    }

    public function jsonResponse(MagentoUser $magentoUser): array
    {
        return [
            'slug' => $magentoUser->customerSalesChannel->slug
        ];
    }

    public function rules(): array
    {
        return [
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
            'url' => ['required', 'string', 'url']
        ];
    }

    public function asController(ActionRequest $request): MagentoUser
    {
        $customer = $request->user()->customer;
        $this->initialisationFromShop($customer->shop, $request);

        return $this->handle($customer, $this->validatedData);
    }

    public function action(Customer $customer, array $modelData): MagentoUser
    {
        $this->initialisation($customer->organisation, $modelData);

        return $this->handle($customer, $modelData);
    }
}
