<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 23 Oct 2026 23:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Inventory\OrgStock;

use App\Actions\Inventory\OrganisationStockHistory\Hydrators\OrganisationStockHistoryHydrateFromOrgStockHistories;
use App\Actions\Inventory\LocationOrgStock\SyncLocationOrgStockQuantityFromMovements;
use App\Actions\Inventory\OrgStock\HydrateOrgStock;
use App\Actions\Inventory\OrgStock\UpdateOrgStock;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Actions\Traits\WithStockHistoryArchiveWrite;
use App\Models\Inventory\OrgStock;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * HELP-4274. Takes the whole stock history off an org stock that was never real stock (IAL01,
 * Import Address Labels: a label line that picked up a 100M unit "found" worth £5M). Movements,
 * and daily histories are copied to help4274_erased_* tables first, on the same database they
 * come from, then deleted; pickings and sowings keep the warehouse record and lose only their
 * movement link. Sales time series are not stock history and stay. Its locations are left
 * holding nothing, and the organisation and group stock value days the histories fed are rolled
 * up again so the value leaves the totals too. With --retire, for a charge or fee that was never
 * stock, it is also unlinked from its products, so nothing can pick from it again, and
 * discontinued; the row stays so old delivery notes and audits keep pointing at it.
 */
class EraseOrgStockHistory
{
    use AsAction;
    use WithStockHistoryArchiveWrite;

    private const string BACKUP_PREFIX = 'help4274_erased_';

    public function getCommandSignature(): string
    {
        return 'org_stock:erase_history {org_stock : org stock id} {--retire : also unlink it from its products and discontinue it, for a charge or fee that was never stock} {--dry-run}';
    }

    /**
     * @return array<string, int>
     */
    public function handle(OrgStock $orgStock, bool $dryRun, bool $retire = false): array
    {
        $counts = [
            'org_stock_movements'          => DB::table('org_stock_movements')->where('org_stock_id', $orgStock->id)->count(),
            'pickings_unlinked'            => DB::table('pickings')->where('org_stock_id', $orgStock->id)->whereNotNull('org_stock_movement_id')->count(),
            'sowings_unlinked'             => DB::table('sowings')->where('org_stock_id', $orgStock->id)->whereNotNull('org_stock_movement_id')->count(),
            'org_stock_histories'          => 0,
            'location_org_stock_histories' => 0,
            'organisation_days_rolled_up'  => 0,
            'products_unlinked'            => $retire ? $orgStock->products()->count() : 0,
            'discontinued'                 => (int)($retire && $orgStock->state !== OrgStockStateEnum::DISCONTINUED),
        ];

        $organisationStockHistoryIds = collect();
        foreach ($this->stockHistoryWriteConnections() as $connection) {
            $counts['org_stock_histories']          += DB::connection($connection)->table('org_stock_histories')->where('org_stock_id', $orgStock->id)->count();
            $counts['location_org_stock_histories'] += DB::connection($connection)->table('location_org_stock_histories')->where('org_stock_id', $orgStock->id)->count();
            $organisationStockHistoryIds = $organisationStockHistoryIds->concat(
                DB::connection($connection)->table('org_stock_histories')->where('org_stock_id', $orgStock->id)->whereNotNull('organisation_stock_history_id')->distinct()->pluck('organisation_stock_history_id')
            );
        }
        $organisationStockHistoryIds = $organisationStockHistoryIds->unique()->values();
        $counts['organisation_days_rolled_up'] = $organisationStockHistoryIds->count();

        if ($dryRun) {
            return $counts;
        }

        DB::transaction(function () use ($orgStock) {
            $movementIds = DB::table('org_stock_movements')->where('org_stock_id', $orgStock->id)->select('id');
            DB::table('pickings')->whereIn('org_stock_movement_id', $movementIds)->update(['org_stock_movement_id' => null]);
            DB::table('sowings')->whereIn('org_stock_movement_id', $movementIds)->update(['org_stock_movement_id' => null]);
            $this->archiveAndDelete(null, 'org_stock_movements', $orgStock->id);
        });

        foreach ($this->stockHistoryWriteConnections() as $connection) {
            DB::connection($connection)->transaction(function () use ($connection, $orgStock) {
                $this->archiveAndDelete($connection, 'location_org_stock_histories', $orgStock->id);
                $this->archiveAndDelete($connection, 'org_stock_histories', $orgStock->id);
            });
        }

        foreach ($organisationStockHistoryIds as $organisationStockHistoryId) {
            OrganisationStockHistoryHydrateFromOrgStockHistories::run($organisationStockHistoryId);
        }

        foreach ($orgStock->locationOrgStocks as $locationOrgStock) {
            SyncLocationOrgStockQuantityFromMovements::run($locationOrgStock);
        }

        if ($retire) {
            $orgStock->products()->detach();
            if ($orgStock->state !== OrgStockStateEnum::DISCONTINUED) {
                UpdateOrgStock::make()->action($orgStock, [
                    'state'                           => OrgStockStateEnum::DISCONTINUED,
                    'discontinued_in_organisation_at' => now(),
                ], strict: false);
            }
        }

        HydrateOrgStock::run($orgStock);

        return $counts;
    }

    private function archiveAndDelete(?string $connection, string $table, int $orgStockId): void
    {
        $backupTable = self::BACKUP_PREFIX.$table;
        $database    = DB::connection($connection);

        $database->statement("create table if not exists $backupTable (like $table)");
        $database->statement("insert into $backupTable select * from $table where org_stock_id = ?", [$orgStockId]);
        $database->table($table)->where('org_stock_id', $orgStockId)->delete();
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $orgStock = OrgStock::findOrFail($command->argument('org_stock'));
        $dryRun   = (bool)$command->option('dry-run');
        $retire   = (bool)$command->option('retire');

        $command->info(($dryRun ? '[dry run] ' : '')."$orgStock->code $orgStock->name");
        $counts = $this->handle($orgStock, $dryRun, $retire);
        $command->table(['What', 'Rows'], collect($counts)->map(fn (int $rows, string $what) => [$what, $rows])->values()->all());

        return 0;
    }
}
