<?php

/*
 * author Arya Permana - Kirin
 * created on 12-06-2025-09h-28m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dropshipping\Magento\Orders;

use App\Actions\Dropshipping\CustomerClient\StoreCustomerClient;
use App\Actions\Ordering\Order\StoreOrder;
use App\Actions\Ordering\Order\SubmitOrder;
use App\Actions\Ordering\Transaction\StoreTransaction;
use App\Actions\OrgAction;
use App\Actions\Retina\Dropshipping\Client\Traits\WithGeneratedMagentoAddress;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\MagentoUser;
use App\Models\Dropshipping\Portfolio;
use App\Models\Helpers\Address;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Sentry;

class StoreOrderFromMagento extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;
    use WithGeneratedMagentoAddress;

    /**
     * @throws \Throwable
     */
    public function handle(MagentoUser $magentoUser, array $modelData): void
    {
        $deliveryAttributes = $this->getAttributes(Arr::get($modelData, 'extension_attributes.shipping_assignments.0.shipping.address'));

        $billingAddress = $this->getAttributes(Arr::get($modelData, 'billing_address'));

        $customerEmail = Arr::get($modelData, 'customer_email');
        $customerClient = $magentoUser->customer?->clients()->where('email', $customerEmail)->first();

        $magentoProducts = collect($modelData['items']);

        if (!$customerClient) {
            $customerClient = StoreCustomerClient::make()->action($magentoUser->customerSalesChannel, $deliveryAttributes);
        }

        $magentoUserHasProductExists = $magentoUser->customerSalesChannel->portfolios()
            ->whereIn('platform_product_id', $magentoProducts->pluck('product_id'))->exists();

        if ($magentoUserHasProductExists) {
            $order = StoreOrder::make()->action($magentoUser->customer, [
                'customer_client_id' => $customerClient->id,
                'platform_id' => $magentoUser->platform_id,
                'customer_sales_channel_id' => $magentoUser->customer_sales_channel_id,
                'date' => $modelData['created_at'],
                'delivery_address' => new Address(Arr::get($deliveryAttributes, 'address')),
                'billing_address' => new Address(Arr::get($billingAddress, 'address')),
                'data' => Arr::only($modelData, 'entity_id')
            ], false);

            foreach ($magentoProducts as $magentoProduct) {
                /** @var Portfolio $magentoUserHasProduct */
                $magentoUserHasProduct = $magentoUser->customerSalesChannel->portfolios()
                    ->where('platform_product_id', $magentoProduct['product_id'])->first();

                if ($magentoUserHasProduct) {
                    /** @var \App\Models\Catalogue\Product $product */
                    $product = $magentoUserHasProduct->item;
                    if (!$product) {
                        \Sentry\captureMessage('Magento product ' . $magentoUserHasProduct->id . ' does not have a product');
                        continue;
                    }

                    /** @var \App\Models\Catalogue\HistoricAsset $historicAsset */
                    $historicAsset = $magentoUserHasProduct->item->asset?->historicAsset;
                    if (!$historicAsset) {
                        \Sentry\captureMessage('Magento product ' . $magentoUserHasProduct->id . ' does not have a historic asset');
                        continue;
                    }

                    StoreTransaction::make()->action(
                        order: $order,
                        historicAsset: $historicAsset,
                        modelData: [
                            'quantity_ordered' => Arr::get($magentoProduct, 'qty_ordered', 1),
                            'data' => $magentoProduct
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
