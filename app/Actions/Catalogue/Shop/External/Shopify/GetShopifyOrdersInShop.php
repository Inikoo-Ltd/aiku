<?php

namespace App\Actions\Catalogue\Shop\External\Shopify;

use App\Actions\Catalogue\Shop\Traits\WithShopifyExternalShopApi;
use App\Actions\OrgAction;
use App\Enums\Catalogue\Shop\ShopEngineEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\Dropshipping\ShopifyUser;
use App\Models\Ordering\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Sentry;
use Throwable;

class GetShopifyOrdersInShop extends OrgAction
{
    use WithShopifyExternalShopApi;

    public const int DEFAULT_ORDER_FROM_DAYS = 30;

    public const int CANCELLED_LOOKBACK_DAYS = 2;

    public const string FULFILLMENT_REQUESTED = 'FULFILLMENT_REQUESTED';

    public const string CANCELLATION_REQUESTED = 'CANCELLATION_REQUESTED';

    public string $commandSignature = 'external_shop:shopify_orders {shop}';

    /**
     * @return array<string, int> number of Shopify orders by what happened to them
     */
    public function handle(Shop $shop, ?Command $command = null): array
    {
        $summary = [];

        if ($shop->type !== ShopTypeEnum::EXTERNAL || $shop->engine !== ShopEngineEnum::SHOPIFY) {
            return $summary;
        }

        $shopifyUser = $this->getShopifyExternalShopUser($shop);

        if ($blockedReason = $this->getShopifyExternalShopBlockedReason($shopifyUser)) {
            $command?->error($blockedReason);

            return $summary;
        }

        if ($this->isShopifyExternalShopFulfilmentService($shop)) {
            $summary = $this->importFulfillmentRequests($shop, $shopifyUser, $command);
            $summary = $this->answerCancellationRequests($shop, $shopifyUser, $summary, $command);
        } else {
            $orderFromDays = max((int) Arr::get($shop->settings, 'shopify.order_from_days', self::DEFAULT_ORDER_FROM_DAYS), 1);

            $newOrders = $this->getAllShopifyExternalShopOrders($shopifyUser, $this->getNewOrdersSearch($orderFromDays));

            if ($message = Arr::get($newOrders, 'message')) {
                $command?->error('Shopify orders read incomplete: '.$message);
                Sentry::captureMessage("Shopify orders read incomplete ($shop->slug): $message");
            }

            foreach ($newOrders['orders'] as $shopifyOrder) {
                $status = $this->importOrder($shop, $shopifyOrder, $command);

                $summary[$status] = ($summary[$status] ?? 0) + 1;
            }
        }

        $cancelledOrders = $this->getAllShopifyExternalShopOrders($shopifyUser, $this->getCancelledOrdersSearch());

        foreach ($cancelledOrders['orders'] as $shopifyOrder) {
            $order = Order::where('shop_id', $shop->id)
                ->where('marketplace_id', Arr::get($shopifyOrder, 'id'))
                ->first();

            if ($order && CancelOrderFromShopifyExternalShop::run($order)) {
                $command?->info('Order '.$order->reference.' cancelled');
                $summary['cancelled'] = ($summary['cancelled'] ?? 0) + 1;
            }
        }

        return $summary;
    }

    /**
     * With a fulfilment service Shopify asks us to fulfil each order sent to our location: the order is imported
     * and only then accepted, so a request we cannot take yet waits in Shopify and is tried again on the next run.
     *
     * @return array<string, int>
     */
    private function importFulfillmentRequests(Shop $shop, ShopifyUser $shopifyUser, ?Command $command): array
    {
        $summary  = [];
        $requests = $this->getShopifyExternalShopAssignedFulfillmentOrders($shopifyUser, self::FULFILLMENT_REQUESTED);

        if ($message = Arr::get($requests, 'message')) {
            $command?->error('Shopify fulfilment requests read incomplete: '.$message);
            Sentry::captureMessage("Shopify fulfilment requests read incomplete ($shop->slug): $message");
        }

        foreach ($requests['fulfillment_orders'] as $fulfillmentOrder) {
            $status = $this->importFulfillmentRequest($shop, $shopifyUser, $fulfillmentOrder, $command);

            $summary[$status] = ($summary[$status] ?? 0) + 1;
        }

        return $summary;
    }

    private function importFulfillmentRequest(Shop $shop, ShopifyUser $shopifyUser, array $fulfillmentOrder, ?Command $command): string
    {
        $fulfillmentOrderId = (string) Arr::get($fulfillmentOrder, 'id');

        if (Arr::get($fulfillmentOrder, 'lineItems.pageInfo.hasNextPage')) {
            $command?->error("Shopify fulfilment request $fulfillmentOrderId has too many lines to read");
            Sentry::captureMessage("Shopify fulfilment request $fulfillmentOrderId has too many lines to read ($shop->slug)");

            return 'skipped';
        }

        $status = $this->importOrder($shop, $this->getShopifyOrderFromFulfillmentOrder($fulfillmentOrder), $command);

        if ($status === 'exists') {
            $order = Order::where('shop_id', $shop->id)->where('marketplace_id', Arr::get($fulfillmentOrder, 'order.id'))->first();

            if (!in_array($fulfillmentOrderId, Arr::get($order?->data, 'shopify_fulfillment_order_ids', []), true)) {
                Sentry::captureMessage("Shopify fulfilment request $fulfillmentOrderId is for an order already imported with other lines ($shop->slug)");

                return 'needs_review';
            }
        }

        if (!in_array($status, ['created', 'exists'])) {
            return $status;
        }

        if (!$this->isShopifyExternalShopWriteAllowed()) {
            return $status === 'created' ? 'created_not_accepted_read_only' : 'not_accepted_read_only';
        }

        if ($message = Arr::get($this->acceptShopifyExternalShopFulfillmentRequest($shopifyUser, $fulfillmentOrderId), 'message')) {
            $command?->error("Shopify fulfilment request $fulfillmentOrderId not accepted: $message");
            Sentry::captureMessage("Shopify fulfilment request $fulfillmentOrderId not accepted ($shop->slug): $message");

            return 'not_accepted';
        }

        return $status === 'created' ? 'created' : 'accepted';
    }

    /**
     * Only the lines Shopify sent to our location are ours to ship, so the order is read from the fulfilment order.
     */
    public function getShopifyOrderFromFulfillmentOrder(array $fulfillmentOrder): array
    {
        $shopifyOrder = Arr::get($fulfillmentOrder, 'order', []);

        $shopifyOrder['fulfillmentOrders'] = ['nodes' => [['id' => Arr::get($fulfillmentOrder, 'id')]]];
        $shopifyOrder['lineItems']         = [
            'nodes' => collect(Arr::get($fulfillmentOrder, 'lineItems.nodes', []))
                ->map(fn (array $fulfillmentOrderLineItem) => [
                    ...Arr::get($fulfillmentOrderLineItem, 'lineItem', []),
                    'currentQuantity'     => (int) Arr::get($fulfillmentOrderLineItem, 'remainingQuantity', 0),
                    'unfulfilledQuantity' => (int) Arr::get($fulfillmentOrderLineItem, 'remainingQuantity', 0),
                ])
                ->values()
                ->all(),
        ];

        return $shopifyOrder;
    }

    /**
     * An order still being prepared is cancelled and the request accepted; one already on its way is refused.
     * A request for an order dropshipping took before the switch is left for a person to decide.
     *
     * @param array<string, int> $summary
     * @return array<string, int>
     */
    private function answerCancellationRequests(Shop $shop, ShopifyUser $shopifyUser, array $summary, ?Command $command): array
    {
        if (!$this->isShopifyExternalShopWriteAllowed()) {
            return $summary;
        }

        $requests = $this->getShopifyExternalShopAssignedFulfillmentOrders($shopifyUser, self::CANCELLATION_REQUESTED);

        foreach ($requests['fulfillment_orders'] as $fulfillmentOrder) {
            $fulfillmentOrderId = (string) Arr::get($fulfillmentOrder, 'id');

            $order = Order::where('shop_id', $shop->id)->where('marketplace_id', Arr::get($fulfillmentOrder, 'order.id'))->first();

            if (!$order) {
                if (Order::where('platform_order_id', $fulfillmentOrderId)->exists()) {
                    Sentry::captureMessage("Shopify cancellation request $fulfillmentOrderId is for a dropshipping order, answer it by hand ($shop->slug)");
                    $summary['cancellation_needs_review'] = ($summary['cancellation_needs_review'] ?? 0) + 1;

                    continue;
                }

                $result = $this->acceptShopifyExternalShopCancellationRequest($shopifyUser, $fulfillmentOrderId);
            } elseif ($order->state === OrderStateEnum::CANCELLED || CancelOrderFromShopifyExternalShop::run($order)) {
                $result = $this->acceptShopifyExternalShopCancellationRequest($shopifyUser, $fulfillmentOrderId);
                $command?->info('Order '.$order->reference.' cancelled');
            } else {
                $result = $this->rejectShopifyExternalShopCancellationRequest($shopifyUser, $fulfillmentOrderId, __('The order has already been dispatched.'));
            }

            if ($message = Arr::get($result, 'message')) {
                Sentry::captureMessage("Shopify cancellation request $fulfillmentOrderId not answered ($shop->slug): $message");
            }

            $summary['cancellation_answered'] = ($summary['cancellation_answered'] ?? 0) + 1;
        }

        return $summary;
    }

    private function importOrder(Shop $shop, array $shopifyOrder, ?Command $command): string
    {
        try {
            $result = StoreOrderFromShopifyExternalShop::run($shop, $shopifyOrder);
        } catch (Throwable $e) {
            $command?->error('Shopify order '.Arr::get($shopifyOrder, 'name').' failed: '.$e->getMessage());
            Sentry::captureException($e);

            return 'failed';
        }

        if ($result['status'] === 'created') {
            $command?->info('Order '.$result['order']->reference.' created');
        }

        if ($result['status'] === 'skipped') {
            $command?->error('Shopify order '.Arr::get($shopifyOrder, 'name').' skipped: '.collect($result['errors'])->map(fn (array $error) => $error['product_code'].' '.$error['message'])->join('; '));

            \Sentry\withScope(function ($scope) use ($shop, $shopifyOrder, $result) {
                $scope->setContext('shopify_order', [
                    'shop'   => $shop->slug,
                    'name'   => Arr::get($shopifyOrder, 'name'),
                    'errors' => $result['errors'],
                ]);
                \Sentry\captureMessage('Shopify order skipped ('.$shop->slug.')');
            });
        }

        return $result['status'];
    }

    public function getNewOrdersSearch(int $orderFromDays): string
    {
        return implode(' AND ', [
            'status:open',
            '(financial_status:paid OR financial_status:partially_refunded)',
            '(fulfillment_status:unfulfilled OR fulfillment_status:partial)',
            "created_at:>='".now()->subDays($orderFromDays)->toIso8601ZuluString()."'",
        ]);
    }

    public function getCancelledOrdersSearch(): string
    {
        return implode(' AND ', [
            'status:cancelled',
            "updated_at:>='".now()->subDays(self::CANCELLED_LOOKBACK_DAYS)->toIso8601ZuluString()."'",
        ]);
    }

    public function asCommand(Command $command): int
    {
        $shop = Shop::where('type', ShopTypeEnum::EXTERNAL)
            ->where('engine', ShopEngineEnum::SHOPIFY)
            ->where('slug', $command->argument('shop'))
            ->firstOrFail();

        $summary = $this->handle($shop, $command);

        $command->table(['Result', 'Orders'], collect($summary)->map(fn (int $count, string $status) => [$status, $count])->values()->all());

        return 0;
    }
}
