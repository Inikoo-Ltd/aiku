<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product;

use App\Actions\Catalogue\Product\Hydrators\ProductHydrateHeathAndSafetyFromTradeUnits;
use App\Actions\Catalogue\Product\Hydrators\ProductHydrateMarketingIngredientsFromTradeUnits;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairProductIngredientsAndOriginFromTradeUnits
{
    use AsAction;

    private const ORIGIN_FIELDS = ['country_of_origin', 'origin_country_id'];

    public string $commandSignature = 'products:repair-ingredients-and-origin
        {--dry-run : Count the products that would change without writing}
        {--S|shop= : Restrict to one shop slug}';

    /**
     * Products whose stored marketing_ingredients / country_of_origin no longer match
     * what their trade units say. Both hydrators used to write values but never clear
     * them, so detaching a trade unit left its text on the product forever.
     *
     * @return array{scanned: int, stale: int}
     */
    public function handle(?int $shopId = null, bool $dryRun = false, ?Command $command = null): array
    {
        $ingredientsHydrator = ProductHydrateMarketingIngredientsFromTradeUnits::make();
        $healthHydrator      = ProductHydrateHeathAndSafetyFromTradeUnits::make();

        $query = Product::has('tradeUnits')
            ->with('tradeUnits')
            ->when($shopId, fn ($query) => $query->where('shop_id', $shopId));

        $bar = null;
        if ($command) {
            $bar = $command->getOutput()->createProgressBar($query->count());
            $bar->setFormat('debug');
            $bar->start();
        }

        $scanned = 0;
        $stale   = 0;

        $query->chunkById(1000, function ($products) use ($ingredientsHydrator, $healthHydrator, $dryRun, $bar, &$scanned, &$stale) {
            foreach ($products as $product) {
                $scanned++;

                if ($this->isStale($product, $ingredientsHydrator, $healthHydrator)) {
                    $stale++;

                    if (!$dryRun) {
                        $ingredientsHydrator->handle($product);
                        $healthHydrator->handle($product, self::ORIGIN_FIELDS);
                    }
                }

                $bar?->advance();
            }
        });

        $bar?->finish();
        $command?->line('');

        return ['scanned' => $scanned, 'stale' => $stale];
    }

    private function isStale(
        Product $product,
        ProductHydrateMarketingIngredientsFromTradeUnits $ingredientsHydrator,
        ProductHydrateHeathAndSafetyFromTradeUnits $healthHydrator
    ): bool {
        if ($ingredientsHydrator->marketingIngredients($product->tradeUnits) != ($product->marketing_ingredients ?? '')) {
            return true;
        }

        $expected = $product->tradeUnits->count() == 1
            ? $healthHydrator->dataFromASingleTradeUnit($product->tradeUnits->first())
            : $healthHydrator->dataFromMultipleTradeUnits($product->tradeUnits);

        foreach (array_intersect_key($expected, array_flip(self::ORIGIN_FIELDS)) as $field => $value) {
            if ($product->$field != $value) {
                return true;
            }
        }

        return false;
    }

    public function asCommand(Command $command): int
    {
        $shopId = null;

        if ($slug = $command->option('shop')) {
            $shop = Shop::where('slug', $slug)->first();

            if (!$shop) {
                $command->error("Shop $slug not found.");

                return Command::FAILURE;
            }

            $shopId = $shop->id;
        }

        $result = $this->handle($shopId, (bool)$command->option('dry-run'), $command);

        $command->line('Products scanned: '.$result['scanned']);
        $command->line('Products out of sync with their trade units: '.$result['stale']);

        if ($command->option('dry-run')) {
            $command->info('Dry run only. No changes were written.');
        }

        return Command::SUCCESS;
    }
}
