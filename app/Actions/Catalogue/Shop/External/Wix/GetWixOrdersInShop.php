<?php

namespace App\Actions\Catalogue\Shop\External\Wix;

use App\Actions\Catalogue\Shop\Traits\WithWixExternalShopApi;
use App\Actions\OrgAction;
use App\Enums\Catalogue\Shop\ShopEngineEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\Dropshipping\WixUser;
use App\Models\Ordering\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Throwable;

class GetWixOrdersInShop extends OrgAction
{
    use WithWixExternalShopApi;

    public string $commandSignature = 'wix:shop_orders {shop}';

    public function handle(Shop $shop, Command|null $command = null): void
    {
        $wixUser = WixUser::where('external_shop_id', $shop->id)->first();

        if (!$wixUser) {
            return;
        }

        $orderFromDays = (int) Arr::get($shop->settings, 'wix.order_from_days', 30);

        $newOrders = $this->getAllWixOrders($wixUser, [
            'status'            => ['$eq' => 'APPROVED'],
            'fulfillmentStatus' => ['$eq' => 'NOT_FULFILLED'],
            'createdDate'       => ['$gte' => now()->subDays($orderFromDays)->toIso8601ZuluString()],
        ]);

        foreach ($newOrders['orders'] as $wixOrder) {
            try {
                $order = StoreOrderFromWix::run($shop, $wixOrder);

                if ($order) {
                    $command?->info('Order '.$order->reference.' created');
                }
            } catch (Throwable $e) {
                $command?->error('Wix order '.Arr::get($wixOrder, 'number').' failed: '.$e->getMessage());
                \Sentry::captureException($e);
            }
        }

        $canceledOrders = $this->getAllWixOrders($wixUser, [
            'status'      => ['$eq' => 'CANCELED'],
            'updatedDate' => ['$gte' => now()->subDays(2)->toIso8601ZuluString()],
        ]);

        foreach ($canceledOrders['orders'] as $wixOrder) {
            $order = Order::where('shop_id', $shop->id)->where('external_id', Arr::get($wixOrder, 'id'))->first();

            if ($order) {
                CancelOrderFromWix::run($order);
            }
        }
    }

    public function asCommand(Command $command): int
    {
        $shop = Shop::where('type', ShopTypeEnum::EXTERNAL)
            ->where('engine', ShopEngineEnum::WIX)
            ->where('slug', $command->argument('shop'))
            ->firstOrFail();

        $this->handle($shop, $command);

        return 0;
    }
}
