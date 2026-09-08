<?php

namespace App\Actions\Catalogue\Shop\External\Shopify;

use App\Actions\Ordering\Order\StoreOrder;
use App\Actions\Ordering\Order\UpdateState\SubmitOrder;
use App\Actions\Ordering\Transaction\StoreTransaction;
use App\Actions\OrgAction;
use App\Enums\Catalogue\Shop\ShopEngineEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Models\Dropshipping\ShopifyUser;
use App\Models\Ordering\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class GetShopifyOrders extends OrgAction
{
    public string $commandSignature = 'external_shop:shopify_orders {shop}';

    public function handle(Shop $shop): void
    {
        DB::transaction(function () use ($shop) {
            $shopifyUser = ShopifyUser::where('external_shop_id', $shop->id)->first();

            if (!$shopifyUser) {
                return;
            }

            $response = $shopifyUser->getShopifyOrders(['first' => 250]);
            $orders = Arr::get($response, 'data.orders.edges', []);

            foreach ($orders as $edge) {
                $shopifyOrder = Arr::get($edge, 'node', []);

                // Extract Shopify order ID (remove the gid://shopify/Order/ prefix)
                $externalId = str_replace('gid://shopify/Order/', '', Arr::get($shopifyOrder, 'id'));

                $orderExists = Order::where('shop_id', $shop->id)
                    ->where('external_id', $externalId)
                    ->exists();

                if ($orderExists) {
                    continue;
                }

                $shopifyCustomer = Arr::get($shopifyOrder, 'customer');

                if ($shopifyCustomer) {
                    $customer = StoreCustomerFromShopify::make()->handle(
                        $shop,
                        $shopifyCustomer,
                        Arr::get($shopifyOrder, 'shippingAddress')
                    );

                    // Prepare order data
                    $orderData = [
                        'external_id' => $externalId,
                        'reference' => Arr::get($shopifyOrder, 'name'), // e.g., #1011
                        'date' => Arr::get($shopifyOrder, 'createdAt'),
                        'data' => [
                            'platform_milestones' => [
                                'draft_created_at' => Arr::get($shopifyOrder, 'createdAt'),
                                'placed_at'        => Arr::get($shopifyOrder, 'processedAt'),
                            ]
                        ]
                    ];

                    // Add shipping address if available
                    if ($shippingAddress = Arr::get($shopifyOrder, 'shippingAddress')) {
                        data_set($orderData, 'delivery_address', $this->getFormattedAddress($shippingAddress));
                    }

                    // Add billing address if available
                    if ($billingAddress = Arr::get($shopifyOrder, 'billingAddress')) {
                        data_set($orderData, 'billing_address', $this->getFormattedAddress($billingAddress));
                    }

                    $awOrder = StoreOrder::make()->action($customer, $orderData);

                    // Process line items
                    foreach (Arr::get($shopifyOrder, 'lineItems.edges', []) as $lineItemEdge) {
                        $lineItem = Arr::get($lineItemEdge, 'node', []);

                        $sku = Arr::get($lineItem, 'sku');

                        if (!$sku) {
                            continue;
                        }

                        $product = Product::where('shop_id', $shop->id)
                            ->where('code', $sku)
                            ->first();

                        $historicAsset = $product?->asset?->historicAsset;

                        if (!$historicAsset) {
                            continue;
                        }

                        $quantity = Arr::get($lineItem, 'quantity', 1);

                        // Use discounted price if available, otherwise use original price
                        $unitPrice = (float)Arr::get($lineItem, 'discountedUnitPriceSet.shopMoney.amount')
                            ?: (float)Arr::get($lineItem, 'originalUnitPriceSet.shopMoney.amount', 0);

                        StoreTransaction::make()->action(
                            order: $awOrder,
                            historicAsset: $historicAsset,
                            modelData: [
                                'quantity_ordered' => $quantity,
                                'external_id' => str_replace('gid://shopify/LineItem/', '', Arr::get($lineItem, 'id')),
                                'net_amount' => $unitPrice * $quantity,
                                'gross_amount' => $unitPrice * $quantity,
                            ]
                        );
                    }

                    SubmitOrder::run($awOrder);
                }
            }
        });
    }

    public function getFormattedAddress(array $address): array
    {
        return StoreCustomerFromShopify::make()->getFormattedAddress($address);
    }

    public function asCommand(Command $command): int
    {
        $shops = Shop::where('type', ShopTypeEnum::EXTERNAL)
            ->where('engine', ShopEngineEnum::SHOPIFY)
            ->where('slug', $command->argument('shop'))
            ->get();

        foreach ($shops as $shop) {
            $this->handle($shop);
        }

        return 0;
    }
}
