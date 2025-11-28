<?php

/*
 * Author: Steven Wicca stewicalf@gmail.com
 * Created: Fri, 28 Nov 2025 16:50:27 Central Indonesia Time, Lembeng Beach, Bali, Indonesia
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\Dropshipping\Platform;

use App\Models\Dropshipping\Platform;
use App\Models\Dropshipping\PlatformSalesMetrics;
use App\Models\Ordering\Order;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairPlatformSalesMetrics
{
    use AsAction;

    public string $commandSignature = 'repair:platform-sales-metrics';

    public function asCommand(Command $command): int
    {
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

        $command->info("Repairing Platform Sales Metrics...");
        $command->info("Total days: $totalDays | Platforms: $totalPlatforms | Steps: $totalSteps");

        $bar = $command->getOutput()->createProgressBar($totalSteps);
        $bar->setFormat('debug');
        $bar->start();

        foreach ($platforms as $platform) {
            foreach ($period as $date) {
                PlatformSalesMetrics::run($platform, $date);
                $bar->advance();
            }
        }

        $bar->finish();
        $command->info("");
        $command->info("Completed.");

        return 0;
    }
}
