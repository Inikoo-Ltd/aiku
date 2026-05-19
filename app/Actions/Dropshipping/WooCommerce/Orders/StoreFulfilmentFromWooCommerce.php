<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 18 May 2026 15:23:19 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\WooCommerce\Orders;

use App\Actions\Fulfilment\PalletReturn\StorePalletReturn;
use App\Actions\Fulfilment\PalletReturn\SubmitPalletReturn;
use App\Actions\Fulfilment\StoredItem\StoreStoredItemsToReturn;
use App\Actions\OrgAction;
use App\Actions\Retina\Dropshipping\Client\Traits\WithGeneratedWooCommerceAddress;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\WooCommerceUser;
use App\Models\Fulfilment\StoredItem;
use App\Models\Helpers\Address;
use App\Models\Helpers\Country;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Sentry;

class StoreFulfilmentFromWooCommerce extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;
    use WithGeneratedWooCommerceAddress;

    /**
     * @throws \Throwable
     */
    public function handle(WooCommerceUser $wooCommerceUser, array $wooOrderData): void
    {
        $fulfilmentCustomer = $wooCommerceUser->customer->fulfilmentCustomer;
        $wooProducts = collect(Arr::get($wooOrderData, 'line_items', []));
        $storedItemModels = $this->digestWooProducts($wooCommerceUser, $wooOrderData);

        $wooUserHasProductExists = $wooCommerceUser->customerSalesChannel->portfolios()
            ->whereIn('platform_product_id', $wooProducts->pluck('product_id'))->exists();

        if (! $wooUserHasProductExists) {
            return;
        }

        $palletReturn = StorePalletReturn::make()->actionWithDropshipping($fulfilmentCustomer, [
            'platform_id'               => $wooCommerceUser->platform_id,
            'customer_sales_channel_id' => $wooCommerceUser->customer_sales_channel_id,
            'date'                      => now(),
            'delivery_address'          => $this->digestWooAddress(Arr::get($wooOrderData, 'shipping')),
            'data'                      => ['woo_data' => $wooOrderData],
            'platform_order_id'         => Arr::get($wooOrderData, 'order_key'),
            'is_collection'             => false
        ]);

        StoreStoredItemsToReturn::make()->action(
            palletReturn: $palletReturn,
            modelData: [
                'stored_items' => $storedItemModels
            ]
        );

        SubmitPalletReturn::run($palletReturn, []);
    }



    public function digestWooAddress($wooOrderData): Address
    {
        $country = Country::where('code', Arr::get($wooOrderData, 'country'))->first();
        if (!$country) {
            $country = Country::where('code', 'GB')->first(); // ¯\_(ツ)_/¯
            Sentry::captureMessage('WooCommerceUserHasCountry >>'.Arr::get($wooOrderData, 'country').'<< country not found, using GB as default when creating CustomerClient. Please check the country code in the order data.');
        }


        $address = [
            'address_line_1'      => Arr::get($wooOrderData, 'address_1'),
            'address_line_2'      => Arr::get($wooOrderData, 'address_2'),
            'sorting_code'        => null,
            'postal_code'         => Arr::get($wooOrderData, 'postcode'),
            'dependent_locality'  => null,
            'locality'            => Arr::get($wooOrderData, 'city'),
            'administrative_area' => Arr::get($wooOrderData, 'state'),
            'country_code'        => $country->code,
            'country_id'          => $country->id
        ];

        return new Address($address);
    }


    public function digestWooProducts(WooCommerceUser $wooCommerceUser, array $wooOrderData): array
    {
        $storedItemModels = [];
        $wooProducts = Arr::get($wooOrderData, 'line_items', []);

        foreach ($wooProducts as $wooProduct) {
            /** @var Portfolio $portfolio */
            $portfolio = $wooCommerceUser->customerSalesChannel->portfolios()
                ->where('platform_product_id', $wooProduct['product_id'])->first();

            if ($portfolio) {
                /** @var StoredItem $product */
                $storedItem = $portfolio->item;
                if (!$storedItem) {
                    \Sentry\captureMessage('Portfolio '.$portfolio->id.' does not have a product');
                    continue;
                }

                $storedItemModels[$storedItem->id] = [
                    'quantity' => $wooProduct['quantity']
                ];
            }
        }

        return $storedItemModels;
    }


}
