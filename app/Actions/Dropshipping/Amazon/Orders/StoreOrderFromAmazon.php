<?php

/*
 * author Arya Permana - Kirin
 * created on 12-06-2025-09h-28m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dropshipping\Amazon\Orders;

use App\Actions\Dropshipping\CustomerClient\StoreCustomerClient;
use App\Actions\Ordering\Order\StoreOrder;
use App\Actions\Ordering\Order\SubmitOrder;
use App\Actions\Ordering\Transaction\StoreTransaction;
use App\Actions\OrgAction;
use App\Actions\Retina\Dropshipping\Client\Traits\WithGeneratedAmazonAddress;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\AmazonUser;
use App\Models\Dropshipping\Portfolio;
use App\Models\Helpers\Address;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Sentry;

class StoreOrderFromAmazon extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;
    use WithGeneratedAmazonAddress;

    /**
     * @throws \Throwable
     */
    public function handle(AmazonUser $amazonUser, array $modelData): void
    {
        $deliveryAttributes = $this->getAttributes($modelData);
        $deliveryAddress = Arr::get($deliveryAttributes, 'address');

        $billingAddress = $amazonUser->customer->address->getFields();

        $customerEmail = Arr::get($deliveryAttributes, 'email');
        $customerClient = $amazonUser->customer?->clients()->where('email', $customerEmail)->first();

        $amazonProducts = $amazonUser->getOrderItems(Arr::get($modelData, 'AmazonOrderId'));
        $amazonProducts = Arr::get($amazonProducts, 'payload.OrderItems');

        if (!$customerClient) {
            $customerClient = StoreCustomerClient::make()->action($amazonUser->customerSalesChannel, $deliveryAttributes);
        }

        $amazonUserHasProductExists = $amazonUser->customerSalesChannel->portfolios()
            ->whereIn('platform_product_id', $amazonProducts->pluck('SellerSKU'))->exists();

        if ($amazonUserHasProductExists) {
            $order = StoreOrder::make()->action($amazonUser->customer, [
                'customer_client_id' => $customerClient->id,
                'platform_id' => $amazonUser->platform_id,
                'customer_sales_channel_id' => $amazonUser->customer_sales_channel_id,
                'date' => $modelData['date_created'],
                'delivery_address' => new Address($deliveryAddress),
                'billing_address' => new Address($billingAddress),
                'data' => $modelData
            ], false);

            foreach ($amazonProducts as $amazonProduct) {
                /** @var Portfolio $amazonUserHasProduct */
                $amazonUserHasProduct = $amazonUser->customerSalesChannel->portfolios()
                    ->where('platform_product_id', $amazonProduct['SellerSKU'])->first();

                if ($amazonUserHasProduct) {
                    /** @var \App\Models\Catalogue\Product $product */
                    $product = $amazonUserHasProduct->item;
                    if (!$product) {
                        \Sentry\captureMessage('AmazonUserHasProduct ' . $amazonUserHasProduct->id . ' does not have a product');
                        continue;
                    }

                    /** @var \App\Models\Catalogue\HistoricAsset $historicAsset */
                    $historicAsset = $amazonUserHasProduct->item->asset?->historicAsset;
                    if (!$historicAsset) {
                        \Sentry\captureMessage('AmazonUserHasProduct ' . $amazonUserHasProduct->id . ' does not have a historic asset');
                        continue;
                    }

                    StoreTransaction::make()->action(
                        order: $order,
                        historicAsset: $historicAsset,
                        modelData: [
                            'quantity_ordered' => $amazonProduct['QuantityOrdered'],
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
