<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 01 Apr 2026 21:01:34 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Inventory\LocationOrgStockHistory;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairLocationOrgStockHistoriesOrganisationStockHistoryId
{
    use AsAction;

    public string $commandSignature = 'repair:location_org_stock_histories_organisation_stock_history_id';

    public function handle(): void
    {
        DB::statement('
            UPDATE location_org_stock_histories
            SET organisation_stock_history_id = org_stock_histories.organisation_stock_history_id
            FROM org_stock_histories
            WHERE location_org_stock_histories.org_stock_history_id = org_stock_histories.id
            AND location_org_stock_histories.organisation_stock_history_id IS NULL
        ');
    }

    public function asCommand(Command $command): int
    {
        $command->info($command->getName());

        $count = DB::table('location_org_stock_histories')
            ->whereNull('organisation_stock_history_id')
            ->count();

        if ($count === 0) {
            $command->info('Nothing to repair.');
            return 0;
        }

        $bar = $command->getOutput()->createProgressBar($count);
        $bar->setFormat('debug');
        $bar->start();

        DB::table('location_org_stock_histories')
            ->whereNull('organisation_stock_history_id')
            ->orderBy('id')
            ->select('id')
            ->chunk(1000, function ($rows) use ($bar) {
                $ids = $rows->pluck('id')->all();

                DB::statement('
                    UPDATE location_org_stock_histories
                    SET organisation_stock_history_id = org_stock_histories.organisation_stock_history_id
                    FROM org_stock_histories
                    WHERE location_org_stock_histories.org_stock_history_id = org_stock_histories.id
                    AND location_org_stock_histories.id IN ('.implode(',', $ids).')
                ');

                $bar->advance(count($ids));
            });

        $bar->finish();
        $command->info('');

        return 0;
    }
}
