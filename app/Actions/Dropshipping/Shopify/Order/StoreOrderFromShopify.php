<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 26 Aug 2024 14:04:22 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Order;

use App\Actions\Dropshipping\CustomerClient\StoreCustomerClient;
use App\Actions\Dropshipping\CustomerClient\UpdateCustomerClient;
use App\Actions\Ordering\Order\StoreOrder;
use App\Actions\Ordering\Order\SubmitOrder;
use App\Actions\Ordering\Transaction\StoreTransaction;
use App\Actions\OrgAction;
use App\Actions\Retina\Dropshipping\Client\Traits\WithGeneratedShopifyAddress;
use App\Actions\Retina\Dropshipping\Orders\PayOrderAsync;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Catalogue\HistoricAsset;
use App\Models\Catalogue\Product;
use App\Models\Dropshipping\CustomerClient;
use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\ShopifyUser;
use App\Models\Helpers\Address;
use App\Models\Ordering\Order;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Sentry;

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
        $deliveryAddress = Arr::get($modelData, 'shipping_address');

        $customerClient = $this->digestShopifyCustomerClient($shopifyUser, $modelData);
        $shopifyProducts = collect($modelData['line_items']);
        $attributes = $this->getAttributes(Arr::get($modelData, 'customer'), $deliveryAddress);
        $deliveryAddress = Arr::get($attributes, 'address');


        $shopifyUserHasProductExists = $shopifyUser->customerSalesChannel->portfolios()
            ->whereIn('platform_product_id', $shopifyProducts->pluck('product_id'))->exists();

        $existOrder = Order::where('platform_order_id', Arr::get($modelData, 'order_id'))->first();

        if ($existOrder) {
            return;
        }

        if ($shopifyUserHasProductExists) {
            $order = StoreOrder::make()->action($customerClient, [
                'platform_id'               => $shopifyUser->platform_id,
                'customer_sales_channel_id' => $shopifyUser->customer_sales_channel_id,
                'date'                      => $modelData['created_at'],
                'delivery_address'          => new Address($deliveryAddress),
                'data'                      => ['shopify_data' => $modelData],
                'platform_order_id'         => Arr::get($modelData, 'order_id'),

            ]);

            foreach ($shopifyProducts as $shopifyProduct) {
                /** @var Portfolio $shopifyUserHasProduct */
                $shopifyUserHasProduct = $shopifyUser->customerSalesChannel->portfolios()
                    ->where('platform_product_id', $shopifyProduct['product_id'])->first();

                if ($shopifyUserHasProduct) {
                    /** @var Product $product */
                    $product = $shopifyUserHasProduct->item;
                    if (!$product) {
                        \Sentry\captureMessage('ShopifyUserHasProduct '.$shopifyUserHasProduct->id.' does not have a product');
                        continue;
                    }

                    /** @var HistoricAsset $product */
                    $historicAsset = $product->asset?->historicAsset;
                    if (!$historicAsset) {
                        \Sentry\captureMessage('ShopifyUserHasProduct '.$shopifyUserHasProduct->id.' does not have a historic asset');
                        continue;
                    }

                    StoreTransaction::make()->action(
                        order: $order,
                        historicAsset: $historicAsset,
                        modelData: [
                            'quantity_ordered'        => $shopifyProduct['quantity'],
                            'platform_transaction_id' => $shopifyProduct['id'],
                        ]
                    );
                }
            }
            try {
                PayOrderAsync::run($order);
            } catch (Exception $e) {
                Sentry::captureException($e);
            }

            SubmitOrder::run($order);
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

        $attributes = $this->getAttributes(Arr::get($shopifyOrderData, 'customer'), Arr::get($shopifyOrderData, 'shipping_address'));
        $deliveryAddress = Arr::get($attributes, 'address');

        if (!$customerClientID) {
            $customerClient = StoreCustomerClient::make()->action($shopifyUser->customerSalesChannel, [
                'reference'    => $reference,
                'email'        => Arr::get($attributes, 'customer.email'),
                'contact_name' => trim(Arr::get($shopifyOrderData, 'customer.first_name').' '.Arr::get($shopifyOrderData, 'customer.last_name')),
                'phone'        => Arr::get($shopifyOrderData, 'customer.phone'),
                'address'      => $deliveryAddress
            ]);
        } else {
            $customerClient = CustomerClient::find($customerClientID->id);
            $customerClient = UpdateCustomerClient::make()->action($customerClient, [
                'email'        => Arr::get($attributes, 'customer.email'),
                'contact_name' => trim(Arr::get($shopifyOrderData, 'customer.first_name').' '.Arr::get($shopifyOrderData, 'customer.last_name')),
                'phone'        => Arr::get($shopifyOrderData, 'customer.phone'),
                'address'      => $deliveryAddress
            ]);
        }


        return $customerClient;
    }

}
