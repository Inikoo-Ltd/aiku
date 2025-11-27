<?php

/*
 * Author: Steven Wicca stewicalf@gmail.com
 * Created: Tue, 25 Nov 2025 11:54:44 Central Indonesia Time, Lembeng Beach, Bali, Indonesia
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\SysAdmin\Organisation;

use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateSalesMetrics;
use App\Models\Ordering\Order;
use App\Models\SysAdmin\Organisation;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairOrganisationSalesMetrics
{
    use AsAction;

    public string $commandSignature = 'repair:organisation-sales-metrics';

    public function asCommand(Command $command): int
    {
        $organisations = Organisation::all();

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
        $totalOrganisations = $organisations->count();

        $totalSteps = $totalDays * $totalOrganisations;

        $command->info("Repairing Organisation Sales Metrics...");
        $command->info("Total days: $totalDays | Organisations: $totalOrganisations | Steps: $totalSteps");

        $bar = $command->getOutput()->createProgressBar($totalSteps);
        $bar->setFormat('debug');
        $bar->start();

        foreach ($organisations as $organisation) {
            foreach ($period as $date) {
                OrganisationHydrateSalesMetrics::run($organisation, $date);
                $bar->advance();
            }
        }

        $bar->finish();
        $command->info("");
        $command->info("Completed.");

        return 0;
    }
}
