<?php

/*
 * Author: Steven Wicca stewicalf@gmail.com
 * Created: Tue, 25 Nov 2025 13:16:33 Central Indonesia Time, Lembeng Beach, Bali, Indonesia
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\Catalogue\Shop;

use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateSalesMetrics;
use App\Models\Catalogue\Shop;
use App\Models\Ordering\Order;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairShopSalesMetrics
{
    use AsAction;

    public string $commandSignature = 'repair:shop-sales-metrics';

    public function asCommand(Command $command): int
    {
        $shops = Shop::all();

        $firstOrder = Order::withTrashed()
            ->orderBy('date')
            ->first();

        if (!$firstOrder) {
            $command->error('No orders found. Nothing to repair.');
            return 0;
        }

        $start = Carbon::parse($firstOrder->date)->startOfDay();
        $end   = Carbon::now()->endOfDay();

        $period = CarbonPeriod::create($start, $end);

        $totalDays  = iterator_count($period);
        $totalShops = $shops->count();

        $totalSteps = $totalDays * $totalShops;

        $command->info("Repairing Shop Sales Metrics...");
        $command->info("Total days: $totalDays | Shops: $totalShops | Steps: $totalSteps");

        $bar = $command->getOutput()->createProgressBar($totalSteps);
        $bar->setFormat('debug');
        $bar->start();

        foreach ($shops as $shop) {
            foreach ($period as $date) {
                ShopHydrateSalesMetrics::run($shop, $date);
                $bar->advance();
            }
        }

        $bar->finish();
        $command->info("");
        $command->info("Completed.");

        return 0;
    }
}
