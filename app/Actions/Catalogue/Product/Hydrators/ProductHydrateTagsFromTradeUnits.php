<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 09 Sept 2024 17:15:49 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product\Hydrators;

use App\Models\Catalogue\Product;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class ProductHydrateTagsFromTradeUnits implements ShouldBeUnique
{
    use AsAction;

    public string $commandSignature = 'product:hydrate-tags {product?} {--chunk=1000}';

    public function getJobUniqueId(Product $product): string
    {
        return $product->id;
    }

    public function handle(Product $product): void
    {
        $tagsFromTradeUnits = $product->tradeUnitTagsViaTradeUnits();

        $tags = [];
        foreach ($tagsFromTradeUnits as $tagFromTradeUnits) {
            if (isset($tagFromTradeUnits['id'])) {
                $tags[$tagFromTradeUnits['id']] = [
                    'shop_id'     => $product->shop_id,
                    'is_for_sale' => $product->is_for_sale
                ];
            }
        }

        $product->tags()->sync($tags);

    }

    public function asCommand(Command $command): int
    {
        $code = $command->argument('product');

        // If a product code is provided, process just that product
        if (!empty($code)) {
            $product = Product::where('code', $code)->first();
            if (!$product) {
                $command->error("Product with code [$code] not found.");
                return 1;
            }

            $this->handle($product);
            $command->info("Hydrated tags for product code [$code].");
            return 0;
        }

        // Otherwise, process all products in chunks to avoid loading all at once
        $chunkSizeOption = (int)($command->option('chunk') ?? 1000);
        $chunkSize = $chunkSizeOption > 0 ? $chunkSizeOption : 1000;

        $total = Product::count();
        if ($total === 0) {
            $command->warn('No products found to hydrate.');
            return 0;
        }

        $command->line("Hydrating tags for $total products in chunks of $chunkSize...");

        // Setup progress bar with ETA
        $bar = $command->getOutput()->createProgressBar($total);
        // Display a rich format including ETA/elapsed/remaining
        $bar->setFormat('[%bar%] %percent:3s%% | %current%/%max% | Elapsed: %elapsed:6s% | Remaining: %remaining:6s% | ETA: %estimated:-6s%');
        $bar->setRedrawFrequency(max(1, (int) floor($total / 200))); // throttle redraws for large totals
        $bar->start();

        $processed = 0;

        Product::query()
            ->orderBy('id')
            ->chunkById($chunkSize, function ($products) use (&$processed, $bar) {
                foreach ($products as $product) {
                    $this->handle($product);
                    $processed++;
                    $bar->advance();
                }
            });

        $bar->finish();
        $command->newLine(2);
        $command->info("Hydrated tags for $processed products.");
        return 0;
    }
}
