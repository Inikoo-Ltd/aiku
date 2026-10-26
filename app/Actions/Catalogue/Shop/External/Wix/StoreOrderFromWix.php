<?php

namespace App\Actions\Catalogue\Shop\External\Wix;

use App\Actions\Ordering\Order\CalculateOrderTotalAmounts;
use App\Actions\Ordering\Order\StoreOrder;
use App\Actions\Ordering\Order\UpdateOrderShippingEngineAsManual;
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
use Illuminate\Support\Facades\Cache;
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

        $result = $this->processWixLineItems($shop, $wixOrder);

        if (!empty($result['errors']) || empty($result['transactions'])) {
            if (Cache::add($this->skippedOrderReportKey($shop, $externalId), true, now()->addDay())) {
                \Sentry\withScope(function ($scope) use ($shop, $wixOrder, $result) {
                    $scope->setContext('wix_order', [
                        'shop'   => $shop->slug,
                        'number' => Arr::get($wixOrder, 'number'),
                        'errors' => $result['errors'],
                    ]);
                    \Sentry\captureMessage('Wix order skipped ('.$shop->slug.')');
                });
            }

            return null;
        }

        return DB::transaction(function () use ($shop, $wixOrder, $externalId, $result) {
            $customer = StoreCustomerFromWix::run($shop, $wixOrder);
            $address  = StoreCustomerFromWix::make()->getFormattedAddress($wixOrder);

            $orderData = [
                'is_shipping_by_external' => false,
                'external_id'             => $externalId,
                'marketplace_id'          => $externalId,
                'reference'               => $this->getOrderReference($shop, $wixOrder),
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

            if (!is_null($wixTaxAmount = Arr::get($wixOrder, 'priceSummary.tax.amount'))) {
                $orderData['data']['marketplace_tax_amount'] = round((float) $wixTaxAmount, 2);
                $orderData['data']['marketplace_net_amount'] = round(
                    array_sum(array_column($result['transactions'], 'net_amount')) + $this->getShippingAmount($wixOrder),
                    2
                );
            }

            if ($salesChannel = SalesChannel::where('code', 'wix')->first()) {
                $orderData['sales_channel_id'] = $salesChannel->id;
            }

            $order = StoreOrder::make()->action($customer, $orderData);

            foreach ($result['transactions'] as $transactionData) {
                StoreTransaction::make()->action(
                    order: $order,
                    historicAsset: $transactionData['historic_asset'],
                    modelData: Arr::except($transactionData, 'historic_asset'),
                    strict: false
                );
            }

            CalculateOrderTotalAmounts::run($order, calculateDiscounts: false);

            $order = SubmitOrder::make()->action($order->refresh());

            if (($shippingAmount = $this->getShippingAmount($wixOrder)) > 0) {
                $order = UpdateOrderShippingEngineAsManual::run($order, ['shipping_amount' => $shippingAmount]);
            }

            if ($warehouse = $shop->organisation->warehouses()->first()) {
                SendOrderToWarehouse::make()->action($order, [
                    'warehouse_id' => $warehouse->id
                ]);
            }

            return $order;
        });
    }

    /**
     * Every poll offers the same order again until its products exist, so a skipped order is reported once a day.
     */
    public function skippedOrderReportKey(Shop $shop, string $wixOrderId): string
    {
        return 'wix-order-skipped:'.$shop->id.':'.$wixOrderId;
    }

    /**
     * @return array{transactions: array<int, array<string, mixed>>, errors: array<int, array<string, mixed>>}
     */
    public function processWixLineItems(Shop $shop, array $wixOrder): array
    {
        $wixOrderId = Arr::get($wixOrder, 'id');
        $lineItems  = Arr::get($wixOrder, 'lineItems', []);
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

            $quantity = (int) Arr::get($lineItem, 'quantity', 1);

            $transactions[] = [
                'historic_asset'   => $product->currentHistoricProduct,
                'quantity_ordered' => $quantity,
                'marketplace_id'   => $this->getTransactionMarketplaceId($wixOrderId, Arr::get($lineItem, 'id')),
                ...$this->getLineAmounts($lineItem, $quantity, (bool) Arr::get($wixOrder, 'taxIncludedInPrices', false)),
            ];
        }

        return [
            'transactions' => $transactions,
            'errors'       => $errors,
        ];
    }

    /**
     * What the buyer paid in Wix, sales and coupons taken off, without tax as every other line in Aiku.
     * Gross is the price before discounts, so the discount shows on the order.
     *
     * @return array{net_amount: float, gross_amount: float}
     */
    public function getLineAmounts(array $lineItem, int $quantity, bool $taxIncludedInPrices): array
    {
        $unitPrice   = (float) Arr::get($lineItem, 'price.amount', 0);
        $grossAmount = (float) (Arr::get($lineItem, 'priceBeforeDiscounts.amount') ?? $unitPrice) * $quantity;
        $netAmount   = (float) (Arr::get($lineItem, 'totalPriceBeforeTax.amount') ?? $unitPrice * $quantity);
        $afterTax    = (float) Arr::get($lineItem, 'totalPriceAfterTax.amount', 0);

        if ($taxIncludedInPrices && $afterTax > 0) {
            $grossAmount = $grossAmount * $netAmount / $afterTax;
        }

        return [
            'net_amount'   => round($netAmount, 2),
            'gross_amount' => round(max($grossAmount, $netAmount), 2),
        ];
    }

    /**
     * What the buyer paid for shipping, after shipping discounts and without tax.
     */
    public function getShippingAmount(array $wixOrder): float
    {
        $shippingAmount = Arr::get($wixOrder, 'shippingInfo.cost.totalPriceBeforeTax.amount')
            ?? Arr::get($wixOrder, 'priceSummary.shipping.amount', 0);

        return round(max((float) $shippingAmount, 0), 2);
    }

    /**
     * Every Wix site numbers its orders from 10001, and the delivery note takes the order reference and must be
     * unique in the organisation, so the shop code keeps two Wix shops from colliding.
     */
    public function getOrderReference(Shop $shop, array $wixOrder): string
    {
        return $shop->code.'-'.(Arr::get($wixOrder, 'number') ?: Arr::get($wixOrder, 'id'));
    }

    /**
     * Wix numbers line items per order (…0001, …0002), so the order id is prefixed to keep them unique in the group.
     */
    public function getTransactionMarketplaceId(string $wixOrderId, string $lineItemId): string
    {
        return $wixOrderId.':'.$lineItemId;
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
