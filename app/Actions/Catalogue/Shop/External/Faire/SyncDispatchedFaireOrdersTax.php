<?php

namespace App\Actions\Catalogue\Shop\External\Faire;

use App\Actions\OrgAction;
use App\Enums\Catalogue\Shop\ShopEngineEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\Ordering\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Laravel\Nightwatch\Facades\Nightwatch;
use Sentry;

/**
 * Faire can still change its tax after dispatch, and dispatched orders are never fully re-synced,
 * so this re-asserts only Faire's tax on recently dispatched orders (HELP-3694).
 */
class SyncDispatchedFaireOrdersTax extends OrgAction
{
    public function handle(int $days = 14, ?Command $command = null): void
    {
        $faireShopIds = Shop::where('type', ShopTypeEnum::EXTERNAL)
            ->where('engine', ShopEngineEnum::FAIRE)
            ->get()
            ->filter(fn (Shop $shop) => Arr::has($shop->settings, 'faire.access_token'))
            ->pluck('id');

        $orders = Order::whereIn('shop_id', $faireShopIds)
            ->whereIn('state', [OrderStateEnum::DISPATCHED, OrderStateEnum::FINALISED])
            ->whereNotNull('external_id')
            ->where('dispatched_at', '>=', now()->subDays($days));

        /** @var Order $order */
        foreach ($orders->cursor() as $order) {
            $command?->info("{$order->shop->slug} $order->slug");
            try {
                UpdateFaireOrder::make()->syncFaireTax($order);
            } catch (\Throwable $e) {
                Sentry::captureException($e);
            }
        }
    }

    public string $commandSignature = 'faire:sync_dispatched_tax {--days=14}';

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();
        $this->handle((int)$command->option('days'), $command);

        return 0;
    }
}
