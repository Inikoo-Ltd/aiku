<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 30 Sep 2026 12:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Shop;

use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Catalogue\Shop;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

class CalculateShopOrderAlertSizes
{
    use AsAction;

    public const int DAYS = 90;
    public const int MIN_ORDERS = 20;

    public string $commandSignature = 'shops:order_alert_sizes';
    public string $commandDescription = 'Work out which ecom orders count as small, normal or big for the new order alerts';

    public function handle(Shop $shop): void
    {
        $sizes = DB::table('orders')
            ->where('shop_id', $shop->id)
            ->whereNotIn('state', [OrderStateEnum::CREATING->value, OrderStateEnum::CANCELLED->value])
            ->whereNull('deleted_at')
            ->where('submitted_at', '>=', now()->subDays(self::DAYS))
            ->selectRaw('count(*) as orders')
            ->selectRaw('percentile_cont(0.25) within group (order by net_amount) as small_below')
            ->selectRaw('percentile_cont(0.90) within group (order by net_amount) as big_above')
            ->first();

        $orderAlerts = $sizes->orders >= self::MIN_ORDERS
            ? ['small_below' => round((float) $sizes->small_below, 2), 'big_above' => round((float) $sizes->big_above, 2), 'orders' => $sizes->orders]
            : ['orders' => $sizes->orders];

        $settings = $shop->settings;
        Arr::set($settings, 'order_alerts', $orderAlerts + ['calculated_at' => now()->toIso8601String()]);
        $shop->settings = $settings;
        $shop->saveQuietly();
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        Shop::whereIn('type', [ShopTypeEnum::B2B, ShopTypeEnum::B2C])
            ->where('state', ShopStateEnum::OPEN)
            ->each(fn (Shop $shop) => $this->handle($shop));

        $command->info('Order alert sizes calculated');

        return 0;
    }
}
