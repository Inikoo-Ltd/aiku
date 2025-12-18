<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 18 Dec 2024 01:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Organisation;

use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateIntrastatImportMetrics;
use App\Enums\Procurement\StockDelivery\StockDeliveryStateEnum;
use App\Models\Helpers\Country;
use App\Models\SysAdmin\Organisation;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairOrganisationIntrastatImportMetrics
{
    use AsAction;

    public string $commandSignature = 'repair:organisation-intrastat-import-metrics {organisation?} {--queue : Dispatch jobs to queue instead of running synchronously}';

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

        $euCountryCodes = Country::getCountryCodesInEU();

        $totalDays = 0;
        $organisationPeriods = [];

        $command->info("Analyzing organisations...");

        foreach ($organisations as $organisation) {
            $euCountryIds = Country::whereIn('code', $euCountryCodes)
                ->where('id', '!=', $organisation->country_id)
                ->pluck('id')
                ->toArray();

            if (empty($euCountryIds)) {
                $command->warn("Organisation {$organisation->slug} has no EU countries to process. Skipping.");
                continue;
            }

            // Find first delivery from OrgSupplier
            $firstSupplierDelivery = DB::table('stock_deliveries as sd')
                ->join('org_suppliers as osup', 'osup.id', '=', 'sd.parent_id')
                ->join('suppliers as sup', 'sup.id', '=', 'osup.supplier_id')
                ->leftJoin('addresses as addr', 'addr.id', '=', 'sup.address_id')
                ->where('sd.organisation_id', $organisation->id)
                ->where('sd.state', StockDeliveryStateEnum::CHECKED->value)
                ->where('sd.parent_type', 'OrgSupplier')
                ->whereNotNull('sd.checked_at')
                ->whereNull('sd.deleted_at')
                ->whereIn('addr.country_id', $euCountryIds)
                ->select('sd.checked_at')
                ->orderBy('sd.checked_at')
                ->first();

            // Find first delivery from OrgPartner
            $firstPartnerDelivery = DB::table('stock_deliveries as sd')
                ->join('org_partners as opar', 'opar.id', '=', 'sd.parent_id')
                ->join('organisations as partner_org', 'partner_org.id', '=', 'opar.partner_id')
                ->where('sd.organisation_id', $organisation->id)
                ->where('sd.state', StockDeliveryStateEnum::CHECKED->value)
                ->where('sd.parent_type', 'OrgPartner')
                ->whereNotNull('sd.checked_at')
                ->whereNull('sd.deleted_at')
                ->whereIn('partner_org.country_id', $euCountryIds)
                ->select('sd.checked_at')
                ->orderBy('sd.checked_at')
                ->first();

            // Use the earliest of the two
            $firstStockDelivery = null;
            if ($firstSupplierDelivery && $firstPartnerDelivery) {
                $firstStockDelivery = $firstSupplierDelivery->checked_at < $firstPartnerDelivery->checked_at
                    ? $firstSupplierDelivery
                    : $firstPartnerDelivery;
            } elseif ($firstSupplierDelivery) {
                $firstStockDelivery = $firstSupplierDelivery;
            } elseif ($firstPartnerDelivery) {
                $firstStockDelivery = $firstPartnerDelivery;
            }

            if (!$firstStockDelivery) {
                $command->warn("No EU supplier deliveries found for {$organisation->slug}. Skipping.");
                continue;
            }

            $start = Carbon::parse($firstStockDelivery->checked_at)->startOfDay();
            $end   = Carbon::now()->endOfDay();
            $period = CarbonPeriod::create($start, $end);

            $days = iterator_count($period);
            $totalDays += $days;

            $organisationPeriods[] = [
                'organisation' => $organisation,
                'period' => CarbonPeriod::create($start, $end),
                'days' => $days,
                'first_date' => $start->toDateString()
            ];

            $command->info("{$organisation->slug}: {$days} days from {$start->toDateString()}");
        }

        if (empty($organisationPeriods)) {
            $command->error('No data to repair. All organisations either have no EU countries or no EU supplier deliveries.');
            return 0;
        }

        $command->info("");
        $command->info("Repairing Organisation Intrastat Import Metrics...");
        $command->info("Total organisations: " . count($organisationPeriods));
        $command->info("Total days to process: $totalDays");

        $useQueue = $command->option('queue');

        if ($useQueue) {
            $command->info("Mode: Queue (asynchronous)");
            $command->info("");

            $jobsDispatched = 0;
            foreach ($organisationPeriods as $orgPeriod) {
                foreach ($orgPeriod['period'] as $date) {
                    OrganisationHydrateIntrastatImportMetrics::dispatch($orgPeriod['organisation'], $date);
                    $jobsDispatched++;
                }
            }

            $command->info("✓ Dispatched {$jobsDispatched} jobs to queue.");
            $command->info("Run queue worker to process: php artisan queue:work");
        } else {
            $command->info("Mode: Synchronous (with progress bar)");
            $command->info("");

            $bar = $command->getOutput()->createProgressBar($totalDays);
            $bar->setFormat('debug');
            $bar->start();

            foreach ($organisationPeriods as $orgPeriod) {
                foreach ($orgPeriod['period'] as $date) {
                    OrganisationHydrateIntrastatImportMetrics::run($orgPeriod['organisation'], $date);
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
