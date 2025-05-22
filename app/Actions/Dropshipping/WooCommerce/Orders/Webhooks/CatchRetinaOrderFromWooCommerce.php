<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 16 Oct 2024 10:47:26 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\WooCommerce\Orders\Webhooks;

use App\Actions\Dropshipping\CustomerClient\StoreCustomerClient;
use App\Actions\Ordering\Order\StoreOrder;
use App\Actions\Ordering\Order\SubmitOrder;
use App\Actions\Ordering\Transaction\StoreTransaction;
use App\Actions\OrgAction;
use App\Actions\Retina\Dropshipping\Client\Traits\WithGeneratedWooCommerceAddress;
use App\Enums\Dropshipping\ChannelFulfilmentStateEnum;
use App\Models\Dropshipping\ShopifyUserHasProduct;
use App\Models\Dropshipping\WooCommerceUser;
use App\Models\Helpers\Address;
use App\Models\Ordering\Order;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class CatchRetinaOrderFromWooCommerce extends OrgAction
{
    use WithGeneratedWooCommerceAddress;

    /**
     * @throws \Throwable
     */
    public function handle(WooCommerceUser $wooCommerceUser, array $modelData): void
    {
        $customer        = Arr::get($modelData, 'customer');
        $deliveryAddress = Arr::get($modelData, 'customer.default_address');
        $customerClient  = $wooCommerceUser->customer?->clients()->where('email', Arr::get($customer, 'email'))->first();

        $wooCommerceProducts = collect($modelData['line_items']);

        $attributes      = $this->getAttributes(Arr::get($modelData, 'customer'), $deliveryAddress);
        $deliveryAddress = Arr::get($attributes, 'address');

        if (!$customerClient) {
            $customerClient = StoreCustomerClient::make()->action($wooCommerceUser->customerSalesChannel, $attributes);
        }

        $order = StoreOrder::make()->action($wooCommerceUser->customer, [
            'customer_client_id'        => $customerClient->id,
            'platform_id'               => $wooCommerceUser->platform_id,
            'customer_sales_channel_id' => $wooCommerceUser->customer_sales_channel_id,
            'date'                      => $modelData['created_at'],
            'delivery_address'          => new Address($deliveryAddress),
            'billing_address'           => new Address($deliveryAddress)
        ]);

        foreach ($wooCommerceProducts as $wooCommerceProduct) {
            /** @var ShopifyUserHasProduct $wooCommerceUserHasProduct */
            $wooCommerceUserHasProduct = ShopifyUserHasProduct::where('shopify_user_id', $wooCommerceUser->id)
                ->where('shopify_product_id', $wooCommerceProduct['product_id'])->first();

            if ($wooCommerceUserHasProduct) {
                /** @var \App\Models\Catalogue\Product $product */
                $product = $wooCommerceUserHasProduct->product;
                if (!$product) {
                    \Sentry\captureMessage('ShopifyUserHasProduct '.$wooCommerceUserHasProduct->id.' does not have a product');
                    continue;
                }

                /** @var \App\Models\Catalogue\HistoricAsset $product */
                $historicAsset = $product->asset?->historicAsset;
                if (!$historicAsset) {
                    \Sentry\captureMessage('ShopifyUserHasProduct '.$wooCommerceUserHasProduct->id.' does not have a historic asset');
                    continue;
                }

                StoreTransaction::make()->action(
                    order: $order,
                    historicAsset: $historicAsset,
                    modelData: [
                        'quantity_ordered' => $wooCommerceProduct['quantity'],
                    ]
                );
            }
        }

        $wooCommerceUser->orders()->attach($order->id, [
            'woo_commerce_user_id'       => $wooCommerceUser->id,
            'model_type'            => class_basename(Order::class),
            'model_id'              => $order->id,
            'woo_commerce_order_id'      => Arr::get($modelData, 'order_id'),
            'state'                 => ChannelFulfilmentStateEnum::OPEN,
            'customer_client_id'    => $customerClient->id
        ]);

        SubmitOrder::run($order);
    }

    public function asController(WooCommerceUser $wooCommerceUser, ActionRequest $request): void
    {
        $this->initialisation($wooCommerceUser->organisation, $request);
        $this->handle($wooCommerceUser, $request->all());
    }
}
