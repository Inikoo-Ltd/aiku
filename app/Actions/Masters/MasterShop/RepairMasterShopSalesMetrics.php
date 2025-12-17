<?php

/*
 * Author: Steven Wicca <stewicalf@gmail.com>
 * Created: Wed, 17 Dec 2025 09:56:01 WITA
 * Location: Lembeng Beach, Bali, Indonesia
 */

namespace App\Actions\Masters\MasterShop;

use App\Actions\Masters\MasterShop\Hydrators\MasterShopHydrateSalesMetrics;
use App\Models\Masters\MasterShop;
use App\Models\Ordering\Order;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairMasterShopSalesMetrics
{
    use AsAction;

    public string $commandSignature = 'repair:master-shop-sales-metrics';

    public function asCommand(Command $command): int
    {
        $masterShops = MasterShop::all();

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
        $totalMasterShops = $masterShops->count();

        $totalSteps = $totalDays * $totalMasterShops;

        $command->info("Repairing Master Shop Sales Metrics...");
        $command->info("Total days: $totalDays | Master Shops: $totalMasterShops | Steps: $totalSteps");

        $bar = $command->getOutput()->createProgressBar($totalSteps);
        $bar->setFormat('debug');
        $bar->start();

        foreach ($masterShops as $masterShop) {
            foreach ($period as $date) {
                MasterShopHydrateSalesMetrics::run($masterShop, $date);
                $bar->advance();
            }
        }

        $bar->finish();
        $command->info("");
        $command->info("Completed.");

        return 0;
    }
}
