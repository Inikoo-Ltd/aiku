<?php

/*
 * author Arya Permana - Kirin
 * created on 02-06-2025-08h-34m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dropshipping\WooCommerce\Orders;

use App\Actions\Dropshipping\CustomerClient\StoreCustomerClient;
use App\Actions\Ordering\Order\StoreOrder;
use App\Actions\Ordering\Order\SubmitOrder;
use App\Actions\Ordering\Transaction\StoreTransaction;
use App\Actions\OrgAction;
use App\Actions\Retina\Dropshipping\Client\Traits\WithGeneratedWooCommerceAddress;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\WooCommerceUser;
use App\Models\Helpers\Address;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Sentry;

class StoreOrderFromWooCommerce extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;
    use WithGeneratedWooCommerceAddress;

    /**
     * @throws \Throwable
     */
    public function handle(WooCommerceUser $wooCommerceUser, array $modelData): void
    {
        $deliveryAttributes = $this->getAttributes(Arr::get($modelData, 'shipping'));
        $deliveryAddress = Arr::get($deliveryAttributes, 'address');

        $billingAttributes = $this->getAttributes(Arr::get($modelData, 'billing'));
        $billingAddress = Arr::get($billingAttributes, 'address');

        $customer = Arr::get($modelData, 'shipping');
        $customerClient = $wooCommerceUser->customer?->clients()->where('phone', Arr::get($customer, 'phone'))->first();

        $wooProducts = collect($modelData['line_items']);

        if (!$customerClient) {
            $customerClient = StoreCustomerClient::make()->action($wooCommerceUser->customerSalesChannel, $deliveryAttributes);
        }

        $wooCommerceUserHasProductExists = $wooCommerceUser->customerSalesChannel->portfolios()
            ->whereIn('platform_product_id', $wooProducts->pluck('product_id'))->exists();

        if ($wooCommerceUserHasProductExists) {
            $order = StoreOrder::make()->action($wooCommerceUser->customer, [
                'customer_client_id' => $customerClient->id,
                'platform_id' => $wooCommerceUser->platform_id,
                'customer_sales_channel_id' => $wooCommerceUser->customer_sales_channel_id,
                'date' => $modelData['date_created'],
                'delivery_address' => new Address($deliveryAddress),
                'billing_address' => new Address($billingAddress),
                'data' => $modelData
            ], false);

            foreach ($wooProducts as $wooProduct) {
                /** @var Portfolio $wooCommerceUserHasProduct */
                $wooCommerceUserHasProduct = $wooCommerceUser->customerSalesChannel->portfolios()
                    ->where('platform_product_id', $wooProduct['product_id'])->first();

                if ($wooCommerceUserHasProduct) {
                    /** @var \App\Models\Catalogue\Product $product */
                    $product = $wooCommerceUserHasProduct->item;
                    if (!$product) {
                        \Sentry\captureMessage('WooCommerceUserHasProduct ' . $wooCommerceUserHasProduct->id . ' does not have a product');
                        continue;
                    }

                    /** @var \App\Models\Catalogue\HistoricAsset $historicAsset */
                    $historicAsset = $wooCommerceUserHasProduct->item->asset?->historicAsset;
                    if (!$historicAsset) {
                        \Sentry\captureMessage('WooCommerceUserHasProduct ' . $wooCommerceUserHasProduct->id . ' does not have a historic asset');
                        continue;
                    }

                    StoreTransaction::make()->action(
                        order: $order,
                        historicAsset: $historicAsset,
                        modelData: [
                            'quantity_ordered' => $wooProduct['quantity'],
                        ]
                    );
                }
            }

            SubmitOrder::run($order);
        } else {
            Sentry::captureMessage('Some products dont exist');
        }
    }
}
