<?php

/*
 * Author: Steven Wicca stewicalf@gmail.com
 * Created: Mon, 24 Nov 2025 16:10:27 Central Indonesia Time, Lembeng Beach, Bali, Indonesia
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\SysAdmin\Group;

use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateSalesMetrics;
use App\Models\Ordering\Order;
use App\Models\SysAdmin\Group;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairGroupSalesMetrics
{
    use AsAction;

    public string $commandSignature = 'repair:group-sales-metrics';

    public function asCommand(Command $command): int
    {
        $groups = Group::all();

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
        $totalGroups = $groups->count();

        $totalSteps = $totalDays * $totalGroups;

        $command->info("Repairing Group Sales Metrics...");
        $command->info("Total days: $totalDays | Groups: $totalGroups | Steps: $totalSteps");

        $bar = $command->getOutput()->createProgressBar($totalSteps);
        $bar->setFormat('debug');
        $bar->start();

        foreach ($groups as $group) {
            foreach ($period as $date) {
                GroupHydrateSalesMetrics::run($group, $date);
                $bar->advance();
            }
        }

        $bar->finish();
        $command->info("");
        $command->info("Completed.");

        return 0;
    }
}
