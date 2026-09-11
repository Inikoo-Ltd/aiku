<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 12 Sep 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Catalogue;

use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Catalogue\Product;
use Illuminate\Console\Command;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * A Faire listing "by the case of N" whose trade units were copied unchanged from a seeder product
 * sold as a single is either N seeder singles (pickers must take N) or one seeder pack of N
 * (pickers take 1). Only a person can tell which, so the product is flagged for review, never guessed.
 */
class FlagFaireCaseSizeMismatch
{
    use AsAction;

    public const string BUCKET = 'faire_case_size';

    public string $commandSignature = 'repair:flag_faire_case_size_mismatch {--dry-run}';

    public function handle(?Command $command = null, bool $dryRun = false): int
    {
        $flagged = 0;
        Product::query()
            ->whereHas('shop', fn ($query) => $query->where('type', ShopTypeEnum::EXTERNAL)->whereNotNull('seeder_shop_id'))
            ->whereNull('units_review')
            ->where('units', '>', 1)
            ->with(['tradeUnits', 'shop.seederShop'])
            ->orderBy('id')
            ->chunk(200, function ($products) use ($command, $dryRun, &$flagged) {
                foreach ($products as $product) {
                    if (!$this->tradeUnitsStillCopiedFromSingleSeeder($product)) {
                        continue;
                    }
                    $flagged++;
                    $command?->line($product->slug.' case of '.trimDecimalZeros($product->units).' but picks like the seeder single');
                    if (!$dryRun) {
                        $product->updateQuietly(['units_review' => self::BUCKET]);
                    }
                }
            });
        $command?->info($flagged.' products '.($dryRun ? 'would be ' : '').'flagged');

        return $flagged;
    }

    public function tradeUnitsStillCopiedFromSingleSeeder(Product $product): bool
    {
        $seederProduct = Product::whereRaw('lower(code) = lower(?)', [$product->code])
            ->where('shop_id', $product->shop->seeder_shop_id)
            ->with('tradeUnits')
            ->first();
        if (!$seederProduct || (float) $seederProduct->units != 1.0 || $seederProduct->tradeUnits->isEmpty()) {
            return false;
        }

        $quantities       = fn ($tradeUnits) => $tradeUnits->mapWithKeys(fn ($tradeUnit) => [$tradeUnit->id => round((float) $tradeUnit->pivot->quantity, 6)])->sortKeys()->all();
        $productTradeUnits = $quantities($product->tradeUnits);

        return $productTradeUnits == $quantities($seederProduct->tradeUnits)
            && round((float) $product->units, 6) != round(array_sum($productTradeUnits), 6);
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();
        $this->handle($command, (bool) $command->option('dry-run'));

        return 0;
    }
}
