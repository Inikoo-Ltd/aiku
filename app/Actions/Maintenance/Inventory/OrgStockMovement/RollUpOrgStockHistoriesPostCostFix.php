<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 13 Aug 2026 20:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Inventory\OrgStockMovement;

use App\Actions\Inventory\OrganisationStockHistory\Hydrators\OrganisationStockHistoryHydrateFromOrgStockHistories;
use App\Actions\Traits\WithStockHistoryArchiveWrite;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementCostStatusEnum;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The per SKU recalculation writes org_stock_histories directly, so the organisation and group
 * rollups stay stale until they are hydrated again from the corrected rows. Days beyond the
 * retention window are named by the archive rather than the operational database, and the
 * hydrator sums each day from wherever it lives.
 */
class RollUpOrgStockHistoriesPostCostFix
{
    use AsAction;
    use WithStockHistoryArchiveWrite;

    public function handle(Organisation $organisation, ?Command $command = null): int
    {
        $earliestRepaired = DB::table('org_stock_movements')
            ->where('organisation_id', $organisation->id)
            ->where('cost_status', OrgStockMovementCostStatusEnum::DELIVERY->value)
            ->min('date');

        if (!$earliestRepaired) {
            return 0;
        }

        $organisationStockHistoryIds = collect();
        foreach ($this->stockHistoryWriteConnections() as $connection) {
            $organisationStockHistoryIds = $organisationStockHistoryIds->concat(
                DB::connection($connection)->table('org_stock_histories')
                    ->where('organisation_id', $organisation->id)
                    ->where('date', '>=', $earliestRepaired)
                    ->whereNotNull('organisation_stock_history_id')
                    ->distinct()
                    ->pluck('organisation_stock_history_id')
            );
        }
        $organisationStockHistoryIds = $organisationStockHistoryIds->unique()->values();

        $progressBar = $command?->getOutput()->createProgressBar(count($organisationStockHistoryIds));
        $progressBar?->start();

        foreach ($organisationStockHistoryIds as $organisationStockHistoryId) {
            OrganisationStockHistoryHydrateFromOrgStockHistories::run($organisationStockHistoryId);
            $progressBar?->advance();
        }

        $progressBar?->finish();
        $command?->newLine();

        return count($organisationStockHistoryIds);
    }

    public function getCommandSignature(): string
    {
        return 'org_stock_movement:recalculate_histories_post_costfix_rollup {organisation}';
    }

    public function asCommand(Command $command): int
    {
        $organisation = Organisation::where('slug', $command->argument('organisation'))->firstOrFail();

        $rolledUp = $this->handle($organisation, $command);
        $command->info("Rolled up $rolledUp organisation stock history days");

        return 0;
    }
}
