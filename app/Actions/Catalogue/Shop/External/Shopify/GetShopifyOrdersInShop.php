<?php

namespace App\Actions\Catalogue\Shop\External\Shopify;

use App\Actions\Catalogue\Shop\Traits\WithShopifyExternalShopApi;
use App\Actions\OrgAction;
use App\Enums\Catalogue\Shop\ShopEngineEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Catalogue\Shop;
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
