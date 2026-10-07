<?php

namespace App\Actions\Catalogue\Shop\External\Shopify;

use App\Actions\Catalogue\Shop\Traits\WithShopifyExternalShopApi;
use App\Actions\Ordering\Order\StoreOrder;
use App\Actions\Ordering\Order\UpdateOrder;
use App\Actions\Ordering\Order\UpdateOrderBillingAddress;
use App\Actions\Ordering\Order\UpdateOrderDeliveryAddress;
use App\Actions\Ordering\Order\UpdateState\SendOrderToWarehouse;
use App\Actions\Ordering\Order\UpdateState\SubmitOrder;
use App\Actions\Ordering\Transaction\StoreTransaction;
use App\Actions\OrgAction;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Models\Ordering\Order;
use App\Models\Ordering\SalesChannel;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StoreOrderFromShopifyExternalShop extends OrgAction
{
    use WithShopifyExternalShopApi;

    public const string SALES_CHANNEL_CODE = 'website';

    /**
     * @return array{order: ?Order, status: string, errors: array<int, array{product_code: string, product_name: string, message: string}>}
     *
     * @throws \Throwable
     */
    public function handle(Shop $shop, array $shopifyOrder): array
    {
        $orderGid   = (string) Arr::get($shopifyOrder, 'id');
        $externalId = Str::after($orderGid, self::SHOPIFY_ORDER_GID_PREFIX);

        if (!$externalId || Arr::get($shopifyOrder, 'cancelledAt')) {
            return $this->result('ignored');
        }

        $lock = Cache::lock('shopify-external-shop-order:'.$orderGid, 300);

        if (!$lock->get()) {
            return $this->result('locked');
        }

        try {
            if ($this->isAlreadyImported($shop, $orderGid, $externalId)) {
                return $this->result('exists');
            }

            if ($this->isTakenByDropshipping($shopifyOrder)) {
                return $this->result('dropshipping');
            }

            $result = $this->processShopifyLineItems($shop, $shopifyOrder);

            if (empty($result['transactions']) && empty($result['errors'])) {
                return $this->result('nothing_to_ship');
            }

            $shippingAddress = $this->getShopifyOrderAddress($shopifyOrder, 'shippingAddress');

            if (!$shippingAddress) {
                $result['errors'][] = [
                    'product_code' => '-',
                    'product_name' => '-',
                    'message'      => 'The order has no shipping address with a known country',
                ];
            }

            if (Arr::get($shopifyOrder, 'line_items_incomplete')) {
                $result['errors'][] = [
                    'product_code' => '-',
                    'product_name' => '-',
                    'message'      => 'Not every line of the order could be read from Shopify',
                ];
            }

            if (!empty($result['errors'])) {
                return $this->result('skipped', errors: $result['errors']);
            }

            $order = DB::transaction(fn () => $this->storeOrder($shop, $shopifyOrder, $orderGid, $externalId, $shippingAddress, $result['transactions']));

            return $this->result('created', $order);
        } finally {
            $lock->release();
        }
    }

    /**
     * @throws \Throwable
     */
    private function storeOrder(Shop $shop, array $shopifyOrder, string $orderGid, string $externalId, array $shippingAddress, array $transactions): Order
    {
        $customer       = StoreCustomerFromShopify::make()->handleFromOrder($shop, $shopifyOrder);
        $billingAddress = $this->getShopifyOrderAddress($shopifyOrder, 'billingAddress') ?? $shippingAddress;

        $orderData = [
            'is_shipping_by_external' => false,
            'external_id'             => $externalId,
            'marketplace_id'          => $orderGid,
            'reference'               => $this->getOrderReference($shop, $shopifyOrder),
            'customer_notes'          => Str::limit((string) Arr::get($shopifyOrder, 'note'), 3990, '') ?: null,
            'data'                    => [
                'shopify_order'       => Arr::only($shopifyOrder, [
                    'id',
                    'name',
                    'createdAt',
                    'processedAt',
                    'displayFinancialStatus',
                    'displayFulfillmentStatus',
                    'taxesIncluded',
                    'currencyCode',
                    'totalPriceSet',
                    'subtotalPriceSet',
                    'totalTaxSet',
                    'totalShippingPriceSet',
                    'totalDiscountsSet',
                ]),
                'shopify_fulfillment_order_ids' => $this->getFulfillmentOrderIds($shopifyOrder),
                'platform_milestones' => [
                    'draft_created_at' => Arr::get($shopifyOrder, 'createdAt'),
                    'placed_at'        => Arr::get($shopifyOrder, 'processedAt'),
                ],
            ],
        ];

        if ($salesChannel = SalesChannel::where('group_id', $shop->group_id)->where('code', self::SALES_CHANNEL_CODE)->first()) {
            $orderData['sales_channel_id'] = $salesChannel->id;
        }

        $order = StoreOrder::make()->action($customer, $orderData);

        $order = UpdateOrderBillingAddress::make()->action($order, ['address' => $billingAddress]);
        $order = UpdateOrderDeliveryAddress::make()->action($order, ['address' => $shippingAddress]);

        $recipient     = Arr::get($shopifyOrder, 'shippingAddress', []);
        $recipientData = array_filter([
            'contact_name' => trim((string) Arr::get($recipient, 'name')) ?: trim(Arr::get($recipient, 'firstName', '').' '.Arr::get($recipient, 'lastName', '')),
            'company_name' => trim((string) Arr::get($recipient, 'company')),
        ]);

        if ($recipientData) {
            $order = UpdateOrder::make()->action($order, $recipientData);
        }

        foreach ($transactions as $transactionData) {
            StoreTransaction::make()->action(
                order: $order,
                historicAsset: $transactionData['historic_asset'],
                modelData: Arr::except($transactionData, 'historic_asset'),
                strict: false
            );
        }

        $order = SubmitOrder::make()->action($order->refresh());

        if ($warehouse = $shop->organisation->warehouses()->first()) {
            SendOrderToWarehouse::make()->action($order, [
                'warehouse_id' => $warehouse->id
            ]);
        }

        return $order->refresh();
    }

    private function isAlreadyImported(Shop $shop, string $orderGid, string $externalId): bool
    {
        return Order::withTrashed()->where('shop_id', $shop->id)->where('external_id', $externalId)->exists()
            || Order::withTrashed()->where('group_id', $shop->group_id)->where('marketplace_id', $orderGid)->exists();
    }

    /**
     * While a store moves from dropshipping to an external shop, an order the dropshipping channel already
     * took (it keeps the Shopify fulfilment order id) must not be imported a second time.
     */
    /**
     * @return array<int, string>
     */
    public function getFulfillmentOrderIds(array $shopifyOrder): array
    {
        return collect(Arr::get($shopifyOrder, 'fulfillmentOrders.nodes', []))->pluck('id')->filter()->values()->all();
    }

    private function isTakenByDropshipping(array $shopifyOrder): bool
    {
        $fulfillmentOrderIds = $this->getFulfillmentOrderIds($shopifyOrder);

        if (empty($fulfillmentOrderIds)) {
            return false;
        }

        return Order::withTrashed()->whereIn('platform_order_id', $fulfillmentOrderIds)->exists();
    }

    /**
     * @return array{transactions: array<int, array<string, mixed>>, errors: array<int, array{product_code: string, product_name: string, message: string}>}
     */
    public function processShopifyLineItems(Shop $shop, array $shopifyOrder): array
    {
        $transactions  = [];
        $errors        = [];
        $taxesIncluded = (bool) Arr::get($shopifyOrder, 'taxesIncluded', false);

        foreach (Arr::get($shopifyOrder, 'lineItems.nodes', []) as $lineItem) {
            if (!Arr::get($lineItem, 'requiresShipping', true)) {
                continue;
            }

            $quantity = $this->getQuantityToShip($lineItem);

            if ($quantity <= 0) {
                continue;
            }

            $product = $this->findProduct($shop, $lineItem);
            $error   = $this->getProductError($product);

            if ($error) {
                $errors[] = [
                    'product_code' => (string) (Arr::get($lineItem, 'sku') ?: 'NO SKU'),
                    'product_name' => (string) (Arr::get($lineItem, 'name') ?: 'NO NAME'),
                    'message'      => $error,
                ];

                continue;
            }

            $transactions[] = [
                'historic_asset'   => $product->currentHistoricProduct,
                'quantity_ordered' => $quantity,
                'marketplace_id'   => (string) Arr::get($lineItem, 'id'),
                ...$this->getLineAmounts($lineItem, $quantity, $taxesIncluded),
            ];
        }

        return [
            'transactions' => $transactions,
            'errors'       => $errors,
        ];
    }

    /**
     * What is left to send: neither refunded or removed (currentQuantity) nor already fulfilled (unfulfilledQuantity).
     */
    public function getQuantityToShip(array $lineItem): int
    {
        $quantity = (int) Arr::get($lineItem, 'quantity', 0);

        return max(min(
            (int) Arr::get($lineItem, 'currentQuantity', $quantity),
            (int) Arr::get($lineItem, 'unfulfilledQuantity', $quantity)
        ), 0);
    }

    private function getProductError(?Product $product): ?string
    {
        if (!$product) {
            return 'Product not found in catalogue';
        }

        if (!$product->currentHistoricProduct) {
            return 'Product has no historic asset';
        }

        if ($product->state === ProductStateEnum::IN_PROCESS || !$product->tradeUnits()->exists()) {
            return 'Product has no trade units yet, so the warehouse cannot pick it';
        }

        return null;
    }

    /**
     * Amounts are kept without tax, as every other line in Aiku, and for the quantity still to ship.
     *
     * @return array{net_amount: float, gross_amount: float}
     */
    public function getLineAmounts(array $lineItem, int $quantity, bool $taxesIncluded): array
    {
        $orderedQuantity = max((int) Arr::get($lineItem, 'quantity', $quantity), 1);

        $originalUnitPrice   = (float) Arr::get($lineItem, 'originalUnitPriceSet.shopMoney.amount', 0);
        $discountedUnitPrice = (float) (Arr::get($lineItem, 'discountedUnitPriceAfterAllDiscountsSet.shopMoney.amount') ?? $originalUnitPrice);

        $lineTax = collect(Arr::get($lineItem, 'taxLines', []))
            ->sum(fn (array $taxLine) => (float) Arr::get($taxLine, 'priceSet.shopMoney.amount', 0)) * $quantity / $orderedQuantity;

        $discountedTotal = $discountedUnitPrice * $quantity;
        $originalTotal   = $originalUnitPrice * $quantity;

        if (!$taxesIncluded) {
            return [
                'net_amount'   => round($discountedTotal, 2),
                'gross_amount' => round($originalTotal, 2),
            ];
        }

        $netAmount   = $discountedTotal - $lineTax;
        $grossAmount = $discountedTotal > 0 ? $originalTotal * $netAmount / $discountedTotal : $originalTotal - $lineTax;

        return [
            'net_amount'   => round($netAmount, 2),
            'gross_amount' => round($grossAmount, 2),
        ];
    }

    public function findProduct(Shop $shop, array $lineItem): ?Product
    {
        if ($variantId = Arr::get($lineItem, 'variant.id')) {
            $product = Product::where('shop_id', $shop->id)->where('marketplace_id', $variantId)->first();

            if ($product) {
                return $product;
            }
        }

        $sku = trim((string) Arr::get($lineItem, 'sku'));

        if ($sku === '') {
            return null;
        }

        return Product::where('shop_id', $shop->id)
            ->whereRaw('lower(code) = lower(?)', [$sku])
            ->first();
    }

    private function getShopifyOrderAddress(array $shopifyOrder, string $key): ?array
    {
        $shopifyAddress = Arr::get($shopifyOrder, $key);

        if (!$shopifyAddress) {
            return null;
        }

        $address = StoreCustomerFromShopify::make()->getFormattedAddress($shopifyAddress);

        return $address['country_id'] ? $address : null;
    }

    /**
     * The Shopify order name ("#1001") is unique in the store; the shop code keeps it unique in the organisation,
     * where the delivery note takes the same reference.
     */
    public function getOrderReference(Shop $shop, array $shopifyOrder): string
    {
        $name = ltrim(trim((string) Arr::get($shopifyOrder, 'name')), '#');

        return $shop->code.'-'.($name ?: Str::afterLast((string) Arr::get($shopifyOrder, 'id'), '/'));
    }

    /**
     * @return array{order: ?Order, status: string, errors: array<int, array{product_code: string, product_name: string, message: string}>}
     */
    private function result(string $status, ?Order $order = null, array $errors = []): array
    {
        return [
            'order'  => $order,
            'status' => $status,
            'errors' => $errors,
        ];
    }
}
