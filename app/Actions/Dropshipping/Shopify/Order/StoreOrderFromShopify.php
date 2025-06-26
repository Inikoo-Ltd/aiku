<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 26 Aug 2024 14:04:22 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Order;

use App\Actions\Dropshipping\CustomerClient\StoreCustomerClient;
use App\Actions\Ordering\Order\StoreOrder;
use App\Actions\Ordering\Order\SubmitOrder;
use App\Actions\Ordering\Transaction\StoreTransaction;
use App\Actions\OrgAction;
use App\Actions\Retina\Dropshipping\Client\Traits\WithGeneratedShopifyAddress;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dropshipping\ChannelFulfilmentStateEnum;
use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\ShopifyUser;
use App\Models\Helpers\Address;
use App\Models\Ordering\Order;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreOrderFromShopify extends OrgAction
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
        $customer = Arr::get($modelData, 'customer');
        $deliveryAddress = Arr::get($modelData, 'shipping_address');
        $billingAddress = Arr::get($modelData, 'billing_address');
        $customerClient = $shopifyUser->customer?->clients()->where('email', Arr::get($customer, 'email'))->first();

        $shopifyProducts = collect($modelData['line_items']);

        $attributes = $this->getAttributes(Arr::get($modelData, 'customer'), $deliveryAddress);

        if ($billingAddress) {
            $billingAddressAttribute = $this->getAttributes(Arr::get($modelData, 'customer'), $billingAddress);
            $billingAddress = Arr::get($billingAddressAttribute, 'address');
        } else {
            $billingAddress = Arr::get($attributes, 'address');
        }

        $deliveryAddress = Arr::get($attributes, 'address');

        if (!$customerClient) {
            $customerClient = StoreCustomerClient::make()->action($shopifyUser->customerSalesChannel, $attributes);
        }

        $shopifyUserHasProductExists = $shopifyUser->customerSalesChannel->portfolios()
            ->whereIn('platform_product_id', $shopifyProducts->pluck('product_id'))->exists();

        if ($shopifyUserHasProductExists) {
            $order = StoreOrder::make()->action($shopifyUser->customer, [
                'customer_client_id' => $customerClient->id,
                'platform_id' => $shopifyUser->platform_id,
                'customer_sales_channel_id' => $shopifyUser->customer_sales_channel_id,
                'date' => $modelData['created_at'],
                'delivery_address' => new Address($deliveryAddress),
                'billing_address' => new Address($billingAddress),
                'data' => $modelData
            ], false);

            foreach ($shopifyProducts as $shopifyProduct) {
                /** @var Portfolio $shopifyUserHasProduct */
                $shopifyUserHasProduct = $shopifyUser->customerSalesChannel->portfolios()
                    ->where('platform_product_id', $shopifyProduct['product_id'])->first();

                if ($shopifyUserHasProduct) {
                    /** @var \App\Models\Catalogue\Product $product */
                    $product = $shopifyUserHasProduct->item;
                    if (!$product) {
                        \Sentry\captureMessage('ShopifyUserHasProduct ' . $shopifyUserHasProduct->id . ' does not have a product');
                        continue;
                    }

                    /** @var \App\Models\Catalogue\HistoricAsset $product */
                    $historicAsset = $product->asset?->historicAsset;
                    if (!$historicAsset) {
                        \Sentry\captureMessage('ShopifyUserHasProduct ' . $shopifyUserHasProduct->id . ' does not have a historic asset');
                        continue;
                    }

                    StoreTransaction::make()->action(
                        order: $order,
                        historicAsset: $historicAsset,
                        modelData: [
                            'quantity_ordered' => $shopifyProduct['quantity'],
                        ]
                    );
                }
            }

            $shopifyUser->orders()->attach($order->id, [
                'shopify_user_id' => $shopifyUser->id,
                'model_type' => class_basename(Order::class),
                'model_id' => $order->id,
                'shopify_order_id' => Arr::get($modelData, 'order_id'),
                'shopify_fulfilment_id' => Arr::get($modelData, 'id'),
                'state' => ChannelFulfilmentStateEnum::OPEN,
                'customer_client_id' => $customerClient->id
            ]);

            SubmitOrder::run($order);
        }
    }
}
