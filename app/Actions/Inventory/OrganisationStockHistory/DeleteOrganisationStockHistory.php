<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 01 Apr 2026 23:00:39 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrganisationStockHistory;

use App\Models\Inventory\OrganisationStockHistory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class DeleteOrganisationStockHistory
{
    use AsAction;

    public function handle(OrganisationStockHistory $organisationStockHistory, bool $dryRun = false, ?int $organisationId = null, ?Command $command = null): void
    {
        if ($organisationId !== null && $organisationStockHistory->organisation_id !== $organisationId) {
            $command?->info("Skipping OrganisationStockHistory ID: $organisationStockHistory->id (organisation_id mismatch)");

            return;
        }

        $locationOrgStockHistoriesCount = DB::table('location_org_stock_histories')
            ->where('organisation_stock_history_id', $organisationStockHistory->id)
            ->count();
        $orgStockHistoriesCount         = DB::table('org_stock_histories')
            ->where('id', $organisationStockHistory->id)
            ->count();

        if ($dryRun) {
            $command?->info("Would delete OrganisationStockHistory ID: $organisationStockHistory->id ".$organisationStockHistory->date->format('Y-m-d'));
            $command?->info("  - location_org_stock_histories: $locationOrgStockHistoriesCount records");
            $command?->info("  - org_stock_histories: $orgStockHistoriesCount records");
            $command?->info("  - organisation_stock_histories: 1 record");

            return;
        }

        DB::table('location_org_stock_histories')->where('organisation_stock_history_id', $organisationStockHistory->id)->delete();
        DB::table('org_stock_histories')->where('id', $organisationStockHistory->id)->delete();
        DB::table('organisation_stock_histories')->where('id', $organisationStockHistory->id)->delete();

        $command?->info("Deleted OrganisationStockHistory ID: $organisationStockHistory->id");
    }


    public function getCommandSignature(): string
    {
        return 'delete:organisation_stock_history {id?} {--date=} {--organisation=} {--dry-run}';
    }

    public function asCommand(Command $command): void
    {
        $dryRun         = $command->option('dry-run');
        $organisationId = $command->option('organisation') ? (int)$command->option('organisation') : null;

        if ($dryRun) {
            $command->warn('DRY RUN MODE - No records will be deleted');
        }

        if ($organisationId !== null) {
            $command->info("Filtering by organisation_id: $organisationId");
        }

        if ($command->argument('id')) {
            $query = OrganisationStockHistory::where('id', $command->argument('id'));
            if ($organisationId !== null) {
                $query->where('organisation_id', $organisationId);
            }
            $organisationStockHistory = $query->firstOrFail();
            $this->handle($organisationStockHistory, $dryRun, $organisationId, $command);

            return;
        }

        if ($command->option('date')) {
            $date  = $command->option('date');
            $query = OrganisationStockHistory::where('date', '<', $date);
            if ($organisationId !== null) {
                $query->where('organisation_id', $organisationId);
            }
            $organisationStockHistories = $query->get();
            $command->info("Found {$organisationStockHistories->count()} records to delete");

            foreach ($organisationStockHistories as $organisationStockHistory) {
                $this->handle($organisationStockHistory, $dryRun, $organisationId, $command);
            }
        }
    }

}