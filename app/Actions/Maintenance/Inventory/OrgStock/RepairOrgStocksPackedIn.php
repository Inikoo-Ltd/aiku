<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Jul 2025 16:05:47 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Inventory\OrgStock;

use App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydratePackedIn;
use App\Models\Inventory\OrgStock;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairOrgStocksPackedIn
{
    use AsAction;



    public function asCommand(Command $command): int
    {
        $chunkSize = 100; // Process 100 records at a time to save memory
        $count = 0;

        // Get total count for progress bar
        $total = OrgStock::count();

        if ($total === 0) {
            $command->info("No org stocks found.");
            return 0;
        }

        // Create a progress bar
        $progressBar = $command->getOutput()->createProgressBar($total);
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $progressBar->start();

        OrgStock::chunk($chunkSize, function ($orgStocks) use (&$count, $progressBar) {
            foreach ($orgStocks as $orgStock) {
                OrgStockHydratePackedIn::run($orgStock);
                $count++;
                $progressBar->advance();
            }
        });

        $progressBar->finish();
        $command->newLine();
        $command->info("Updated packed_in for $count org stocks.");
        return $count;
    }

    public function getCommandSignature(): string
    {
        return 'repair:org-stocks-packed-in';
    }

    public function getCommandDescription(): string
    {
        return 'Update null packed_in values in org_stocks using OrgStockHydratePackedIn';
    }
}
