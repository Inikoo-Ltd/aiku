<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 26 Aug 2024 14:04:22 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Order;

use App\Actions\Dropshipping\CustomerClient\StoreCustomerClient;
use App\Actions\Dropshipping\CustomerClient\UpdateCustomerClient;
use App\Actions\Fulfilment\PalletReturn\StorePalletReturn;
use App\Actions\Fulfilment\StoredItem\StoreStoredItemsToReturn;
use App\Actions\OrgAction;
use App\Actions\Retina\Dropshipping\Client\Traits\WithGeneratedShopifyAddress;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\CustomerClient;
use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\ShopifyUser;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Fulfilment\StoredItem;
use App\Models\Helpers\Address;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreFulfilmentOrderFromShopify extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;
    use WithGeneratedShopifyAddress;

    /**
     * @throws \Throwable
     */
    public function handle(ShopifyUser $shopifyUser, array $modelData): void
    {
        $fulfilmentCustomer = $shopifyUser->customerSalesChannel->customer->fulfilmentCustomer;

        $deliveryAddress = Arr::get($modelData, 'shipping_address');
        $shopifyProducts = collect($modelData['line_items']);
        $customerClient = $this->digestShopifyCustomerClient($shopifyUser, $modelData);
        $attributes = $this->getShopifyAttributesFromWebhook(Arr::get($modelData, 'customer'), $deliveryAddress);
        $deliveryAddress = Arr::get($attributes, 'address');

        $shopifyUserHasProductExists = $shopifyUser->customerSalesChannel->portfolios()
            ->whereIn('platform_product_id', $shopifyProducts->pluck('product_id'))->exists();

        $existOrder = PalletReturn::where('platform_order_id', Arr::get($modelData, 'id'))->first();

        if ($existOrder) {
            return;
        }

        if ($shopifyUserHasProductExists) {
            $palletReturn = StorePalletReturn::make()->actionWithDropshipping($fulfilmentCustomer, [
                'platform_id'               => $shopifyUser->platform_id,
                'customer_sales_channel_id' => $shopifyUser->customer_sales_channel_id,
                'date'                      => $modelData['created_at'],
                'delivery_address'          => new Address($deliveryAddress),
                'data'                      => ['shopify_data' => $modelData],
                'platform_order_id'         => Arr::get($modelData, 'id'),
                'is_collection'             => false,
                'shopify_user_id'           => $shopifyUser->id
            ]);

            $storedItemModels = [];
            foreach ($shopifyProducts as $shopifyProduct) {
                /** @var Portfolio $portfolio */
                $portfolio = $shopifyUser->customerSalesChannel->portfolios()
                    ->where('platform_product_id', $shopifyProduct['product_id'])->first();

                if ($portfolio) {
                    /** @var StoredItem $product */
                    $storedItem = $portfolio->item;
                    if (!$storedItem) {
                        \Sentry\captureMessage('Portfolio '.$portfolio->id.' does not have a product');
                        continue;
                    }

                    $storedItemModels[$storedItem->id] = [
                        'quantity' => $shopifyProduct['quantity']
                    ];
                }
            }

            StoreStoredItemsToReturn::make()->action(
                palletReturn: $palletReturn,
                modelData: [
                    'stored_items' => $storedItemModels
                ]
            );

            $palletReturn->refresh();
        }
    }

    /**
     * @throws \Throwable
     */
    public function digestShopifyCustomerClient(ShopifyUser $shopifyUser, array $shopifyOrderData): CustomerClient
    {
        $reference = (string) Arr::get($shopifyOrderData, 'customer.id');

        $customerClientID = DB::table('customer_clients')
            ->select('id')
            ->where('customer_sales_channel_id', $shopifyUser->customer_sales_channel_id)
            ->where('reference', $reference)
            ->first();

        $attributes = $this->getShopifyAttributesFromWebhook(Arr::get($shopifyOrderData, 'customer'), Arr::get($shopifyOrderData, 'shipping_address'));
        $deliveryAddress = Arr::get($attributes, 'address');

        if (!$customerClientID) {
            $customerClient = StoreCustomerClient::make()->action($shopifyUser->customerSalesChannel, [
                'reference'    => $reference,
                'email'        => Arr::get($shopifyOrderData, 'customer.email'),
                'contact_name' => trim(Arr::get($shopifyOrderData, 'customer.firstName').' '.Arr::get($shopifyOrderData, 'customer.lastName')),
                'phone'        => Arr::get($shopifyOrderData, 'customer.phone'),
                'address'      => $deliveryAddress
            ]);
        } else {
            $customerClient = CustomerClient::find($customerClientID->id);
            $customerClient = UpdateCustomerClient::make()->action($customerClient, [
                'email'        => Arr::get($shopifyOrderData, 'customer.email'),
                'contact_name' => trim(Arr::get($shopifyOrderData, 'customer.firstName').' '.Arr::get($shopifyOrderData, 'customer.lastName')),
                'phone'        => Arr::get($shopifyOrderData, 'customer.phone'),
                'address'      => $deliveryAddress
            ]);
        }


        return $customerClient;
    }

}
