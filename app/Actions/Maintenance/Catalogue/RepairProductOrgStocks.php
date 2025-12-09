<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 24 Nov 2025 22:38:18 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Catalogue;

use App\Actions\Catalogue\Product\Hydrators\ProductHydrateAvailableQuantity;
use App\Actions\Catalogue\Product\SyncProductOrgStocksFromTradeUnits;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\Console\Helper\ProgressBar;

class RepairProductOrgStocks
{
    use asAction;

    /**
     * @throws \Throwable
     */
    public function handle(Shop|Product $subject, Command $command = null): void
    {

        if($subject instanceof Product){
            SyncProductOrgStocksFromTradeUnits::run($subject);
            ProductHydrateAvailableQuantity::run($subject);
            return;
        }

        $shop=$subject;

        $baseQuery = DB::table('products')
            ->select('id')
            ->where('shop_id', $shop->id);

        $total = (clone $baseQuery)->count();

        if ($command) {
            $output = $command->getOutput();
            $output->writeln("Processing $total products in chunks...");

            if ($total === 0) {
                $output->writeln('Nothing to process.');

                return;
            }

            // Custom progress bar format with ETA
            ProgressBar::setFormatDefinition(
                'aiku_eta',
                ' %current%/%max% [%bar%] %percent:3s%% | Elapsed: %elapsed:6s% | ETA: %remaining:6s%'
            );
            $progress = new ProgressBar($output, $total);
            $progress->setFormat('aiku_eta');
            $progress->setRedrawFrequency(50);
            $progress->start();
        } else {
            if ($total === 0) {
                return;
            }
        }

        $processed = 0;
        $chunkSize = 500; // tune chunk size as needed

        $baseQuery
            ->orderBy('id', 'desc')
            ->chunkById($chunkSize, function ($rows) use (&$processed, $command, &$progress) {
                // Eager to load products in the current chunk to avoid N+1 lookups
                $ids      = collect($rows)->pluck('id')->all();
                $products = Product::query()->whereIn('id', $ids)->get();

                foreach ($products as $product) {
                    if ($product->tradeUnits->count() == 0) {
                        $command->info($product->code." {$product->state->value} no trade units skipping");
                        continue;
                    }

                    SyncProductOrgStocksFromTradeUnits::run($product);
                    ProductHydrateAvailableQuantity::run($product);


                    $processed++;
                    if ($command && isset($progress)) {
                        $progress->advance();
                    }
                }
            }, 'id');

        if ($command) {
            // Finish and newline
            if (isset($progress)) {
                $progress->finish();
                $command->getOutput()->writeln("");
            }
            $command->info("Done. Processed $processed/$total products.");
        }
    }


    public function getCommandSignature(): string
    {
        return 'shop:fix_product_org_stocks {type} {slug}';
    }

    /**
     * @throws \Throwable
     */
    public function asCommand(Command $command): int
    {
        if($command->argument('type')=='Product'){
            $subject = Product::where('slug', $command->argument('slug'))->firstOrFail();
        }else{
            $subject = Shop::where('slug', $command->argument('shop'))->firstOrFail();
        }


        $command->info("Fixing product org stocks for ".$command->argument('type')."  $subject->code");


        $this->handle($subject, $command);

        return 0;
    }


}
