<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 26 Aug 2024 14:04:22 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Order;

use App\Actions\Dropshipping\CustomerClient\StoreCustomerClient;
use App\Actions\Ordering\Order\StoreOrder;
use App\Actions\Ordering\Transaction\StoreTransaction;
use App\Actions\OrgAction;
use App\Actions\Retina\Dropshipping\Client\Traits\WithGeneratedShopifyAddress;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dropshipping\ChannelFulfilmentStateEnum;
use App\Models\Dropshipping\ShopifyUser;
use App\Models\Helpers\Address;
use App\Models\Ordering\Order;
use App\Models\ShopifyUserHasProduct;
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
        $customer        = Arr::get($modelData, 'customer');
        $deliveryAddress = Arr::get($modelData, 'customer.default_address');
        $customerClient  = $shopifyUser->customer?->clients()->where('email', Arr::get($customer, 'email'))->first();

        $shopifyProducts = collect($modelData['line_items']);

        $attributes      = $this->getAttributes(Arr::get($modelData, 'customer'), $deliveryAddress);
        $deliveryAddress = Arr::get($attributes, 'address');

        $order = StoreOrder::make()->action($shopifyUser->customer, [
            'date'             => $modelData['created_at'],
            'delivery_address' => new Address($deliveryAddress),
            'billing_address'  => new Address($deliveryAddress),
        ]);

        if (!$customerClient) {
            $customerClient = StoreCustomerClient::make()->action($shopifyUser->customer, $attributes);
        }

        foreach ($shopifyProducts as $shopifyProduct) {
            /** @var ShopifyUserHasProduct $shopifyUserHasProduct */
            $shopifyUserHasProduct = ShopifyUserHasProduct::where('shopify_product_id', $shopifyProduct['product_id'])->first();
            if ($shopifyUserHasProduct) {
                /** @var \App\Models\Catalogue\Product $product */
                $product = $shopifyUserHasProduct->product;
                if (!$product) {
                    \Sentry\captureMessage('ShopifyUserHasProduct '.$shopifyUserHasProduct->id.' does not have a product');
                    continue;
                }

                /** @var \App\Models\Catalogue\HistoricAsset $product */
                $historicAsset = $product->asset?->historicAsset;
                if (!$historicAsset) {
                    \Sentry\captureMessage('ShopifyUserHasProduct '.$shopifyUserHasProduct->id.' does not have a historic asset');
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
            'shopify_user_id'       => $shopifyUser->id,
            'model_type'            => class_basename(Order::class),
            'model_id'              => $order->id,
            'shopify_order_id'      => Arr::get($modelData, 'order_id'),
            'shopify_fulfilment_id' => Arr::get($modelData, 'id'),
            'state'                 => ChannelFulfilmentStateEnum::OPEN,
            'customer_client_id'    => $customerClient->id
        ]);
    }
}
