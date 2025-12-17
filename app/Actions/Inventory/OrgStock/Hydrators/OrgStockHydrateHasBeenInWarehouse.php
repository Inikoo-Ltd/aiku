<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 04 Dec 2025 15:43:14 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

/*
 * Author: Junie (AI Assistant)
 * Created: Thu, 04 Dec 2025 12:40:00 Local Time
 */

namespace App\Actions\Inventory\OrgStock\Hydrators;

use App\Actions\Catalogue\Product\Hydrators\ProductHydrateAvailableQuantity;
use App\Models\Inventory\OrgStock;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\Console\Helper\ProgressBar;

class OrgStockHydrateHasBeenInWarehouse implements ShouldBeUnique
{
    use AsAction;

    public function getJobUniqueId(OrgStock $orgStock): int
    {
        return $orgStock->id;
    }

    public function handle(OrgStock $orgStock): void
    {
        // True if there has been at least one movement, or if there is any stock quantity in locations
        $hasBeen = ($orgStock->orgStockMovements()->count() > 0)
            || ((float) $orgStock->quantity_in_locations > 0);

        // Only update when the value changes to avoid noisy writes/audits
        if ($orgStock->has_been_in_warehouse !== $hasBeen) {
            $orgStock->updateQuietly([
                'has_been_in_warehouse' => $hasBeen,
            ]);

            foreach ($orgStock->products as $product) {
                ProductHydrateAvailableQuantity::dispatch($product);
            }

        }
    }

    public function getCommandSignature(): string
    {
        return 'org_stock:hydrate_has_been_in_warehouse {orgStock? : OrgStock ID or slug. Omit to process ALL in chunks}';
    }

    public function asCommand(Command $command): int
    {
        $arg = $command->argument('orgStock');

        // Bulk mode: no argument provided → process ALL org stocks in chunks with progress bar
        if ($arg === null) {
            $baseQuery = DB::table('org_stocks')->select('id');
            $total     = (clone $baseQuery)->count();

            if ($total === 0) {
                $command->info('No org stocks to process.');
                return 0;
            }

            $output = $command->getOutput();
            $output->writeln("Processing $total org stocks in chunks...");

            // Progress bar with ETA similar to other maintenance commands
            ProgressBar::setFormatDefinition(
                'aiku_eta',
                ' %current%/%max% [%bar%] %percent:3s%% | Elapsed: %elapsed:6s% | ETA: %remaining:6s%'
            );
            $progress = new ProgressBar($output, $total);
            $progress->setFormat('aiku_eta');
            $progress->setRedrawFrequency(50);
            $progress->start();

            $processed = 0;
            $chunkSize = 500;

            $baseQuery
                ->orderBy('id', 'asc')
                ->chunkById($chunkSize, function ($rows) use (&$processed, $progress) {
                    $ids      = collect($rows)->pluck('id')->all();
                    $orgStocks = OrgStock::query()->whereIn('id', $ids)->get();

                    foreach ($orgStocks as $orgStock) {
                        $this->handle($orgStock);
                        $processed++;
                        $progress->advance();
                    }
                }, 'id');

            $progress->finish();
            $output->writeln("");
            $command->info("Done. Processed $processed/$total org stocks.");

            return 0;
        }

        $orgStock = null;
        $arg = (string) $arg;
        if (is_numeric($arg)) {
            $orgStock = OrgStock::find((int) $arg);
        }

        if (!$orgStock) {
            $orgStock = OrgStock::where('slug', $arg)->first();
        }

        if (!$orgStock) {
            $command->error('OrgStock not found: '.$arg);

            return 1;
        }

        $this->handle($orgStock);

        $command->info(
            'Updated org_stock '.$orgStock->id.' has_been_in_warehouse='.(
                $orgStock->has_been_in_warehouse ? 'true' : 'false'
            )
        );

        return 0;
    }

}
