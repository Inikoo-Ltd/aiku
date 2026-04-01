<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 01 Apr 2026 21:03:55 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Inventory\OrgStockHistory;

use App\Models\Inventory\OrgStockHistory;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairLastSold
{
    use asAction;

    public function handle(OrgStockHistory $orgStockHistory): void
    {
        if ($orgStockHistory->last_sold_date) {
            $oneYearAgo   = $orgStockHistory->date->subYear();
            $soldWithin1y = $orgStockHistory->last_sold_date->isAfter($oneYearAgo);

            $orgStockHistory->update([
                'sold_within_1y' => $soldWithin1y
            ]);
        } else {
            $orgStockHistory->update([
                'sold_within_1y' => false
            ]);
        }
    }

    public function getCommandSignature(): string
    {
        return 'repair:last-sold';
    }

    public function asCommand(Command $command): void
    {
        $total = OrgStockHistory::count();
        $bar   = $command->getOutput()->createProgressBar($total);
        $bar->setFormat('debug');
        $bar->start();

        OrgStockHistory::chunk(10000, function ($orgStockHistories) use ($command, $bar) {
            foreach ($orgStockHistories as $orgStockHistory) {
                $this->handle($orgStockHistory);
                $bar->advance();
            }
        });

        $bar->finish();
        $command->newLine();
        $command->info('Completed processing OrgStockHistory records.');
    }

}
