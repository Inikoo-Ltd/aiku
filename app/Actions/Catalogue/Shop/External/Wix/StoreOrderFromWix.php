<?php

namespace App\Actions\Catalogue\Shop\External\Wix;

use App\Actions\Ordering\Order\StoreOrder;
use App\Actions\Ordering\Order\UpdateState\SendOrderToWarehouse;
use App\Actions\Ordering\Order\UpdateState\SubmitOrder;
use App\Actions\Ordering\Transaction\StoreTransaction;
use App\Actions\OrgAction;
use App\Enums\Ordering\Order\OrderPayDetailedStatusEnum;
use App\Enums\Ordering\Order\OrderPayStatusEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Models\Ordering\Order;
use App\Models\Ordering\SalesChannel;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class StoreOrderFromWix extends OrgAction
{
    /**
     * @throws \Throwable
     */
    public function handle(Shop $shop, array $wixOrder): ?Order
    {
        $externalId = Arr::get($wixOrder, 'id');

        if (!$externalId || Arr::get($wixOrder, 'status') !== 'APPROVED') {
            return null;
        }

        if (Order::where('shop_id', $shop->id)->where('external_id', $externalId)->exists()) {
            return null;
        }

        $result = $this->processWixLineItems($shop, Arr::get($wixOrder, 'lineItems', []));

        if (!empty($result['errors']) || empty($result['transactions'])) {
            \Sentry\withScope(function ($scope) use ($shop, $wixOrder, $result) {
                $scope->setContext('wix_order', [
                    'shop'   => $shop->slug,
                    'number' => Arr::get($wixOrder, 'number'),
                    'errors' => $result['errors'],
                ]);
                \Sentry\captureMessage('Wix order skipped ('.$shop->slug.')');
            });

            return null;
        }

        return DB::transaction(function () use ($shop, $wixOrder, $externalId, $result) {
            $customer = StoreCustomerFromWix::run($shop, $wixOrder);
            $address  = StoreCustomerFromWix::make()->getFormattedAddress($wixOrder);

            $orderData = [
                'is_shipping_by_external' => false,
                'external_id'             => $externalId,
                'marketplace_id'          => $externalId,
                'reference'               => (string) (Arr::get($wixOrder, 'number') ?: $externalId),
                'created_at'              => Carbon::parse(Arr::get($wixOrder, 'createdDate'))->toDateTimeString(),
                'billing_address'         => $address,
                'delivery_address'        => $address,
                'customer_notes'          => Arr::get($wixOrder, 'buyerNote'),
                'pay_status'              => OrderPayStatusEnum::UNPAID->value,
                'pay_detailed_status'     => OrderPayDetailedStatusEnum::UNPAID->value,
                'data'                    => [
                    'wix_order' => Arr::only($wixOrder, ['id', 'number', 'paymentStatus', 'fulfillmentStatus', 'priceSummary', 'currency']),
                ],
            ];

            if ($salesChannel = SalesChannel::where('code', 'wix')->first()) {
                $orderData['sales_channel_id'] = $salesChannel->id;
            }

            $order = StoreOrder::make()->action($customer, $orderData);

            foreach ($result['transactions'] as $transactionData) {
                StoreTransaction::make()->action(
                    order: $order,
                    historicAsset: $transactionData['historic_asset'],
                    modelData: Arr::except($transactionData, 'historic_asset')
                );
            }

            $order = SubmitOrder::make()->action($order);

            if ($warehouse = $shop->organisation->warehouses()->first()) {
                SendOrderToWarehouse::make()->action($order, [
                    'warehouse_id' => $warehouse->id
                ]);
            }

            return $order;
        });
    }

    /**
     * @return array{transactions: array<int, array<string, mixed>>, errors: array<int, array<string, mixed>>}
     */
    public function processWixLineItems(Shop $shop, array $lineItems): array
    {
        $transactions = [];
        $errors       = [];

        foreach ($lineItems as $lineItem) {
            $product = $this->findProduct($shop, $lineItem);

            if (!$product?->currentHistoricProduct) {
                $errors[] = [
                    'product_code' => Arr::get($lineItem, 'physicalProperties.sku', 'NO SKU'),
                    'product_name' => Arr::get($lineItem, 'productName.original', 'NO NAME'),
                    'message'      => $product ? 'Product has no historic asset' : 'Product not found in catalogue',
                ];

                continue;
            }

            $quantity  = (int) Arr::get($lineItem, 'quantity', 1);
            $unitPrice = (float) Arr::get($lineItem, 'price.amount', 0);
            $netAmount = (float) (Arr::get($lineItem, 'totalPriceBeforeTax.amount') ?? $unitPrice * $quantity);

            $transactions[] = [
                'historic_asset'   => $product->currentHistoricProduct,
                'quantity_ordered' => $quantity,
                'external_id'      => Arr::get($lineItem, 'id'),
                'marketplace_id'   => Arr::get($lineItem, 'id'),
                'net_amount'       => $netAmount,
                'gross_amount'     => $unitPrice * $quantity,
            ];
        }

        return [
            'transactions' => $transactions,
            'errors'       => $errors,
        ];
    }

    public function findProduct(Shop $shop, array $lineItem): ?Product
    {
        $variantId = Arr::get($lineItem, 'catalogReference.options.variantId')
            ?: GetWixProducts::WIX_V1_DEFAULT_VARIANT_ID;

        $product = Product::where('shop_id', $shop->id)
            ->where('marketplace_second_id', Arr::get($lineItem, 'catalogReference.catalogItemId'))
            ->where('marketplace_id', $variantId)
            ->first();

        if ($product) {
            return $product;
        }

        $sku = Arr::get($lineItem, 'physicalProperties.sku');

        if (!$sku) {
            return null;
        }

        return Product::where('shop_id', $shop->id)
            ->whereRaw('lower(code) = lower(?)', [$sku])
            ->first();
    }
}
