<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Fri, 27 Jun 2025 11:04:20 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Magento;

use App\Actions\Dropshipping\CustomerSalesChannel\UpdateCustomerSalesChannel;
use App\Actions\OrgAction;
use App\Enums\Dropshipping\CustomerSalesChannelStateEnum;
use App\Models\Dropshipping\MagentoUser;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class ReAuthorizeMagentoUser extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithMagentoApiRequest;

    public function handle(MagentoUser $magentoUser, array $modelData): string
    {
        return DB::transaction(function () use ($magentoUser, $modelData) {
            $customerSalesChannel = $magentoUser->customerSalesChannel;
            $validated = $this->validateMagentoAccount($magentoUser->username, $magentoUser->password, Arr::get($magentoUser->settings, 'credentials.base_url'));

            if (! $validated) {
                throw ValidationException::withMessages(['username' => __('The credentials provided is invalid.')]);
            }

            $magentoUser->refresh();
            $accessToken = $magentoUser->getMagentoToken();
            $stores = $magentoUser->getStores();

            $magentoUser->update([
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

            $routeName = match ($magentoUser->customer->is_fulfilment) {
                true => 'retina.fulfilment.dropshipping.customer_sales_channels.show',
                default => 'retina.dropshipping.customer_sales_channels.show'
            };

            return route($routeName, [
                'customerSalesChannel' => $magentoUser->customerSalesChannel->slug
            ]);
        });
    }
}
