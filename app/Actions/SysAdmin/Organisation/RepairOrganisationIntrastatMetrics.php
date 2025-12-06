<?php

/*
 * Author: Steven Wicca stewicalf@gmail.com
 * Created: Fri, 05 Dec 2025 14:27:23 Central Indonesia Time, Lembeng Beach, Bali, Indonesia
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\SysAdmin\Organisation;

use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateIntrastatMetrics;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\SysAdmin\Organisation;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairOrganisationIntrastatMetrics
{
    use AsAction;

    public string $commandSignature = 'repair:organisation-intrastat-metrics {organisation?}';

    public function asCommand(Command $command): int
    {
        $organisationSlug = $command->argument('organisation');

        if ($organisationSlug) {
            $organisation = Organisation::where('slug', $organisationSlug)->first();
            if (!$organisation) {
                $command->error("Organisation not found: $organisationSlug");
                return 1;
            }
            $organisations = collect([$organisation]);
        } else {
            $organisations = Organisation::all();
        }

        // Find the first dispatched delivery note to EU to determine start date
        $firstDeliveryNote = DeliveryNote::where('state', DeliveryNoteStateEnum::DISPATCHED)
            ->whereNotNull('dispatched_at')
            ->whereNotNull('delivery_country_id')
            ->orderBy('dispatched_at')
            ->first();

        if (!$firstDeliveryNote) {
            $command->error('No dispatched delivery notes found. Nothing to repair.');
            return 0;
        }

        $start = Carbon::parse($firstDeliveryNote->dispatched_at)->startOfDay();
        $end   = Carbon::now()->endOfDay();

        $period = CarbonPeriod::create($start, $end);

        $totalDays          = iterator_count($period);
        $totalOrganisations = $organisations->count();
        $totalSteps         = $totalDays * $totalOrganisations;

        $command->info("Repairing Organisation Intrastat Metrics...");
        $command->info("Total days: $totalDays | Organisations: $totalOrganisations | Steps: $totalSteps");

        $bar = $command->getOutput()->createProgressBar($totalSteps);
        $bar->setFormat('debug');
        $bar->start();

        foreach ($organisations as $organisation) {
            foreach ($period as $date) {
                OrganisationHydrateIntrastatMetrics::run($organisation, $date);
                $bar->advance();
            }
        }

        $bar->finish();
        $command->info("");
        $command->info("Completed.");

        return 0;
    }
}
