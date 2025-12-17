<?php

/*
 * Author: Steven Wicca stewicalf@gmail.com
 * Created: Fri, 05 Dec 2025 14:27:23 Central Indonesia Time, Lembeng Beach, Bali, Indonesia
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\SysAdmin\Organisation;

use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateIntrastatExportMetrics;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Helpers\Country;
use App\Models\SysAdmin\Organisation;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairOrganisationIntrastatMetrics
{
    use AsAction;

    public string $commandSignature = 'repair:organisation-intrastat-metrics {organisation?} {--queue : Dispatch jobs to queue instead of running synchronously}';

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

        // Get EU country codes once
        $euCountryCodes = Country::getCountryCodesInEU();

        $totalDays = 0;
        $organisationPeriods = [];

        $command->info("Analyzing organisations...");

        foreach ($organisations as $organisation) {
            // Get EU country IDs excluding organisation's own country
            $euCountryIds = Country::whereIn('code', $euCountryCodes)
                ->where('id', '!=', $organisation->country_id)
                ->pluck('id')
                ->toArray();

            if (empty($euCountryIds)) {
                $command->warn("Organisation {$organisation->slug} has no EU countries to process. Skipping.");
                continue;
            }

            // Find first delivery note PER ORGANISATION to EU countries only
            $firstDeliveryNote = DeliveryNote::where('organisation_id', $organisation->id)
                ->where('state', DeliveryNoteStateEnum::DISPATCHED)
                ->whereNotNull('dispatched_at')
                ->whereIn('delivery_country_id', $euCountryIds)
                ->orderBy('dispatched_at')
                ->first();

            if (!$firstDeliveryNote) {
                $command->warn("No EU delivery notes found for {$organisation->slug}. Skipping.");
                continue;
            }

            $start = Carbon::parse($firstDeliveryNote->dispatched_at)->startOfDay();
            $end   = Carbon::now()->endOfDay();
            $period = CarbonPeriod::create($start, $end);

            $days = iterator_count($period);
            $totalDays += $days;

            $organisationPeriods[] = [
                'organisation' => $organisation,
                'period' => CarbonPeriod::create($start, $end),  // Recreate because iterator was consumed
                'days' => $days,
                'first_date' => $start->toDateString()
            ];

            $command->info("{$organisation->slug}: {$days} days from {$start->toDateString()}");
        }

        if (empty($organisationPeriods)) {
            $command->error('No data to repair. All organisations either have no EU countries or no EU deliveries.');
            return 0;
        }

        $command->info("");
        $command->info("Repairing Organisation Intrastat Metrics...");
        $command->info("Total organisations: " . count($organisationPeriods));
        $command->info("Total days to process: $totalDays");

        $useQueue = $command->option('queue');

        if ($useQueue) {
            // Queue mode: dispatch jobs without progress bar
            $command->info("Mode: Queue (asynchronous)");
            $command->info("");

            $jobsDispatched = 0;
            foreach ($organisationPeriods as $orgPeriod) {
                foreach ($orgPeriod['period'] as $date) {
                    OrganisationHydrateIntrastatExportMetrics::dispatch($orgPeriod['organisation'], $date);
                    $jobsDispatched++;
                }
            }

            $command->info("✓ Dispatched {$jobsDispatched} jobs to queue.");
            $command->info("Run queue worker to process: php artisan queue:work");
        } else {
            // Sync mode: run with progress bar
            $command->info("Mode: Synchronous (with progress bar)");
            $command->info("");

            $bar = $command->getOutput()->createProgressBar($totalDays);
            $bar->setFormat('debug');
            $bar->start();

            foreach ($organisationPeriods as $orgPeriod) {
                foreach ($orgPeriod['period'] as $date) {
                    OrganisationHydrateIntrastatExportMetrics::run($orgPeriod['organisation'], $date);
                    $bar->advance();
                }
            }

            $bar->finish();
            $command->info("");
            $command->info("✓ Completed successfully.");
        }

        return 0;
    }
}
