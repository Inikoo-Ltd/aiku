<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order;

use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Ordering\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class SweepGoldRewardWindowBaskets
{
    use AsAction;

    public function handle(Shop $shop, int $lookbackDays = 2): int
    {
        if (Arr::get($shop->offers_data, 'gr.amnesty_offer_id')) {
            return 0;
        }

        $grInterval = Arr::get($shop->offers_data, 'gr.interval', 30);

        $customersIds = Customer::where('shop_id', $shop->id)
            ->whereNotNull('last_invoiced_at')
            ->whereBetween(DB::raw('last_invoiced_at::date'), [
                now()->subDays($grInterval + $lookbackDays - 1)->toDateString(),
                now()->subDays($grInterval)->toDateString(),
            ])
            ->select('id');

        $count = 0;
        Order::where('shop_id', $shop->id)
            ->where('state', OrderStateEnum::CREATING)
            ->whereIn('customer_id', $customersIds)
            ->chunkById(100, function ($orders) use (&$count) {
                foreach ($orders as $order) {
                    CalculateOrderDiscounts::dispatch($order);
                    $count++;
                }
            });

        return $count;
    }

    public function asJob(): void
    {
        foreach ($this->getShops() as $shop) {
            $this->handle($shop);
        }
    }

    protected function getShops(?string $slug = null)
    {
        return Shop::where('is_aiku', true)
            ->where('type', ShopTypeEnum::B2B)
            ->when($slug, fn ($query) => $query->where('slug', $slug))
            ->get()
            ->filter(fn (Shop $shop) => Arr::get($shop->offers_data, 'gr.active'));
    }

    public function getCommandSignature(): string
    {
        return 'ordering:sweep-gold-reward-window-baskets {shop?}';
    }

    public function asCommand(Command $command): void
    {
        foreach ($this->getShops($command->argument('shop')) as $shop) {
            $command->info($shop->slug.': '.$this->handle($shop).' baskets queued');
        }
    }
}
