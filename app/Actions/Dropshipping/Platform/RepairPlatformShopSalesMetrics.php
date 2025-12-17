<?php

/*
 * Author: Steven Wicca <stewicalf@gmail.com>
 * Created: Tue, 17 Dec 2025 11:20:00 Central Indonesia Time, Lembeng Beach, Bali, Indonesia
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\Dropshipping\Platform;

use App\Actions\Dropshipping\Platform\Hydrators\PlatformShopHydrateSalesMetrics;
use App\Models\Catalogue\Shop;
use App\Models\Dropshipping\Platform;
use App\Models\Ordering\Order;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairPlatformShopSalesMetrics
{
    use AsAction;

    public string $commandSignature = 'repair:platform-shop-sales-metrics {--platform=} {--shop=}';

    public function asCommand(Command $command): int
    {
        $platformSlug = $command->option('platform');
        $shopSlug = $command->option('shop');

        if ($platformSlug && $shopSlug) {
            return $this->repairSpecific($command, $platformSlug, $shopSlug);
        }

        if ($platformSlug) {
            return $this->repairByPlatform($command, $platformSlug);
        }

        if ($shopSlug) {
            return $this->repairByShop($command, $shopSlug);
        }

        return $this->repairAll($command);
    }

    private function repairAll(Command $command): int
    {
        $platforms = Platform::all();
        $shops = Shop::where('type', 'dropshipping')->get();

        if ($shops->count() === 0) {
            $command->error('No dropshipping shops found. Nothing to repair.');
            return 0;
        }

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
        $totalPlatforms = $platforms->count();
        $totalShops = $shops->count();

        $totalSteps = $totalDays * $totalPlatforms * $totalShops;

        $command->info("Repairing Platform Shop Sales Metrics...");
        $command->info("Total days: $totalDays | Platforms: $totalPlatforms | Dropshipping Shops: $totalShops | Steps: $totalSteps");

        $bar = $command->getOutput()->createProgressBar($totalSteps);
        $bar->setFormat('debug');
        $bar->start();

        foreach ($platforms as $platform) {
            foreach ($shops as $shop) {
                foreach ($period as $date) {
                    PlatformShopHydrateSalesMetrics::run($platform, $shop, $date);
                    $bar->advance();
                }
            }
        }

        $bar->finish();
        $command->info("");
        $command->info("Completed.");

        return 0;
    }

    private function repairSpecific(Command $command, string $platformSlug, string $shopSlug): int
    {
        $platform = Platform::where('slug', $platformSlug)->first();
        $shop = Shop::where('slug', $shopSlug)->first();

        if (!$platform) {
            $command->error("Platform '{$platformSlug}' not found.");
            return 1;
        }

        if (!$shop) {
            $command->error("Shop '{$shopSlug}' not found.");
            return 1;
        }

        if ($shop->type !== 'dropshipping') {
            $command->error("Shop '{$shopSlug}' is not a dropshipping shop.");
            return 1;
        }

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
        $totalDays = iterator_count($period);

        $command->info("Repairing Platform Shop Sales Metrics for {$platform->code} - {$shop->code}...");
        $command->info("Total days: $totalDays");

        $bar = $command->getOutput()->createProgressBar($totalDays);
        $bar->setFormat('debug');
        $bar->start();

        foreach ($period as $date) {
            PlatformShopHydrateSalesMetrics::run($platform, $shop, $date);
            $bar->advance();
        }

        $bar->finish();
        $command->info("");
        $command->info("Completed.");

        return 0;
    }

    private function repairByPlatform(Command $command, string $platformSlug): int
    {
        $platform = Platform::where('slug', $platformSlug)->first();

        if (!$platform) {
            $command->error("Platform '{$platformSlug}' not found.");
            return 1;
        }

        $shops = Shop::where('type', 'dropshipping')->get();

        if ($shops->count() === 0) {
            $command->error('No dropshipping shops found. Nothing to repair.');
            return 0;
        }

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

        $command->info("Repairing Platform Shop Sales Metrics for platform {$platform->code}...");
        $command->info("Total days: $totalDays | Dropshipping Shops: $totalShops | Steps: $totalSteps");

        $bar = $command->getOutput()->createProgressBar($totalSteps);
        $bar->setFormat('debug');
        $bar->start();

        foreach ($shops as $shop) {
            foreach ($period as $date) {
                PlatformShopHydrateSalesMetrics::run($platform, $shop, $date);
                $bar->advance();
            }
        }

        $bar->finish();
        $command->info("");
        $command->info("Completed.");

        return 0;
    }

    private function repairByShop(Command $command, string $shopSlug): int
    {
        $shop = Shop::where('slug', $shopSlug)->first();

        if (!$shop) {
            $command->error("Shop '{$shopSlug}' not found.");
            return 1;
        }

        if ($shop->type !== 'dropshipping') {
            $command->error("Shop '{$shopSlug}' is not a dropshipping shop.");
            return 1;
        }

        $platforms = Platform::all();

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
        $totalPlatforms = $platforms->count();
        $totalSteps = $totalDays * $totalPlatforms;

        $command->info("Repairing Platform Shop Sales Metrics for shop {$shop->code}...");
        $command->info("Total days: $totalDays | Platforms: $totalPlatforms | Steps: $totalSteps");

        $bar = $command->getOutput()->createProgressBar($totalSteps);
        $bar->setFormat('debug');
        $bar->start();

        foreach ($platforms as $platform) {
            foreach ($period as $date) {
                PlatformShopHydrateSalesMetrics::run($platform, $shop, $date);
                $bar->advance();
            }
        }

        $bar->finish();
        $command->info("");
        $command->info("Completed.");

        return 0;
    }
}
