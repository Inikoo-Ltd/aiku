<?php

/*
 * author Arya Permana - Kirin
 * created on 12-06-2025-09h-28m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dropshipping\Ebay\Orders;

use App\Actions\Dropshipping\CustomerClient\StoreCustomerClient;
use App\Actions\Ordering\Order\StoreOrder;
use App\Actions\Ordering\Order\SubmitOrder;
use App\Actions\Ordering\Transaction\StoreTransaction;
use App\Actions\OrgAction;
use App\Actions\Retina\Dropshipping\Client\Traits\WithGeneratedEbayAddress;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\EbayUser;
use App\Models\Dropshipping\Portfolio;
use App\Models\Helpers\Address;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Sentry;

class StoreOrderFromEbay extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;
    use WithGeneratedEbayAddress;

    /**
     * @throws \Throwable
     */
    public function handle(EbayUser $ebayUser, array $modelData): void
    {
        $deliveryAttributes = $this->getAttributes(Arr::get($modelData, 'fulfillmentStartInstructions.0.shippingStep.shipTo'));
        $deliveryAddress = Arr::get($deliveryAttributes, 'address');

        $billingAddress = $ebayUser->customer->address->getFields();

        $customerEmail = Arr::get($deliveryAttributes, 'email');
        $customerClient = $ebayUser->customer?->clients()->where('email', $customerEmail)->first();

        $ebayProducts = collect($modelData['line_items']);

        if (!$customerClient) {
            $customerClient = StoreCustomerClient::make()->action($ebayUser->customerSalesChannel, $deliveryAttributes);
        }

        $ebayUserHasProductExists = $ebayUser->customerSalesChannel->portfolios()
            ->whereIn('platform_product_id', $ebayProducts->pluck('legacyItemId'))->exists();

        if ($ebayUserHasProductExists) {
            $order = StoreOrder::make()->action($ebayUser->customer, [
                'customer_client_id' => $customerClient->id,
                'platform_id' => $ebayUser->platform_id,
                'customer_sales_channel_id' => $ebayUser->customer_sales_channel_id,
                'date' => $modelData['date_created'],
                'delivery_address' => new Address($deliveryAddress),
                'billing_address' => new Address($billingAddress),
                'data' => $modelData
            ], false);

            foreach ($ebayProducts as $ebayProduct) {
                /** @var Portfolio $ebayUserHasProduct */
                $ebayUserHasProduct = $ebayUser->customerSalesChannel->portfolios()
                    ->where('platform_product_id', $ebayProduct['legacyItemId'])->first();

                if ($ebayUserHasProduct) {
                    /** @var \App\Models\Catalogue\Product $product */
                    $product = $ebayUserHasProduct->item;
                    if (!$product) {
                        \Sentry\captureMessage('WooCommerceUserHasProduct ' . $ebayUserHasProduct->id . ' does not have a product');
                        continue;
                    }

                    /** @var \App\Models\Catalogue\HistoricAsset $historicAsset */
                    $historicAsset = $ebayUserHasProduct->item->asset?->historicAsset;
                    if (!$historicAsset) {
                        \Sentry\captureMessage('WooCommerceUserHasProduct ' . $ebayUserHasProduct->id . ' does not have a historic asset');
                        continue;
                    }

                    StoreTransaction::make()->action(
                        order: $order,
                        historicAsset: $historicAsset,
                        modelData: [
                            'quantity_ordered' => $ebayProduct['quantity'],
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
