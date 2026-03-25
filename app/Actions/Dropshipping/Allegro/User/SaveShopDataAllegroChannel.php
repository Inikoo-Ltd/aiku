<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 05 Mar 2026 16:53:20 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Allegro\User;

use App\Actions\Dropshipping\CustomerSalesChannel\UpdateCustomerSalesChannel;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Catalogue\Shop;
use App\Models\Dropshipping\AllegroUser;
use App\Models\Dropshipping\CustomerSalesChannel;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class SaveShopDataAllegroChannel
{
    use WithActionUpdate;

    public function handle(AllegroUser $allegroUser): AllegroUser
    {
        try {
            /** @var Shop $shop */
            $shop = $allegroUser->customerSalesChannel->shop;
            // Get user info from Allegro API
            $userInfo = $allegroUser->getUserInfo();

            if ($userInfo) {
                $data = $allegroUser->data ?? [];

                // Save user/seller information
                data_set($data, 'user_id', Arr::get($userInfo, 'id'));
                data_set($data, 'login', Arr::get($userInfo, 'login'));
                data_set($data, 'email', Arr::get($userInfo, 'email'));
                data_set($data, 'company_name', Arr::get($userInfo, 'company.name'));
                data_set($data, 'taxId', Arr::get($userInfo, 'company.taxId'));
                data_set($data, 'marketplace_id', Arr::get($userInfo, 'baseMarketplace.id'));

                if (! Arr::get($allegroUser->settings, 'shipping.id')) {
                    try {
                        $deliveryMethods = $allegroUser->getDeliveryMethods();
                        $deliveryMethod = collect(Arr::get($deliveryMethods, 'deliveryMethods'))->firstWhere('destinationCountry', $shop->country->code);

                        $shipping = $allegroUser->createShippingRates([
                            'name' => 'Shipping-rates-'.$allegroUser->customerSalesChannel->slug,
                            'rates' => [
                                [
                                    'deliveryMethod' => [
                                        'id' => Arr::get($deliveryMethod ?? [], 'id'),
                                    ],
                                    'maxQuantityPerPackage' => 5,
                                    'firstItemRate' => [
                                        'currency' => Arr::get($deliveryMethod ?? [], 'shippingRatesConstraints.firstItemRate.currency'),
                                        'amount' => '2.00'
                                    ],
                                    'nextItemRate' => [
                                        'currency' => Arr::get($deliveryMethod ?? [], 'shippingRatesConstraints.nextItemRate.currency'),
                                        'amount' => '0.00'
                                    ],
                                ]
                            ]
                        ]);
                    } catch (\Exception $e) {
                        $shipping = [];
                    }

                    data_set($data, 'shipping_id', Arr::get($shipping, 'id'));
                }

                if (! Arr::get($allegroUser->settings, 'policy.return_id')) {
                    try {
                        $return = $allegroUser->createReturnPolicy([
                            'address' => [
                                'name'         => $shop->name,
                                'street'        => $shop->address->address_line_1,
                                'city'        => $shop->address->locality,
                                'country_code' => $shop->country->code,
                                'post_code'    => $shop->address->postal_code,
                                'province'    => $shop->address->administrative_area
                            ]
                        ]);
                    } catch (\Exception $e) {
                        $return = [];
                    }

                    data_set($data, 'return_id', Arr::get($return, 'id'));
                }

                $allegroUser = $this->update($allegroUser, [
                    'allegro_id' => Arr::get($data, 'user_id'),
                    'marketplace_id' => Arr::get($data, 'marketplace_id'),
                    'data' => $data,
                    'email' => Arr::get($data, 'email') ?? $allegroUser->email,
                    'username' => Arr::get($data, 'login') ?? $allegroUser->username,
                    'settings' => [
                        'shipping' => [
                            'id' => Arr::get($data, 'shipping_id')
                        ],
                        'policy' => [
                            'return_id' => Arr::get($data, 'return_id')
                        ]
                    ]
                ], ['settings']);

                UpdateCustomerSalesChannel::run($allegroUser->customerSalesChannel, [
                    'name' => Arr::get($data, 'company_name')
                ]);
            }

            return $allegroUser->refresh();
        } catch (\Exception $e) {
            Log::error('Failed to save Allegro shop data: ' . $e->getMessage());
            return $allegroUser;
        }
    }

    public string $commandSignature = 'allegro:save-shop-data {customerSalesChannel}';

    public function asCommand(Command $command)
    {
        $customerSalesChannel = CustomerSalesChannel::where('slug', $command->argument('customerSalesChannel'))->first();

        $this->handle($customerSalesChannel->user);
    }
}
