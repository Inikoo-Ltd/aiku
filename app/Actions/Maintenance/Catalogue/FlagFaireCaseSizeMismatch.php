<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 12 Sep 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Catalogue;

use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Catalogue\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
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

    public string $commandSignature = 'repair:flag_faire_case_size_mismatch {--dry-run} {--seeder-remodelled}';

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

    /**
     * Faire products copy their trade units from the seeder product once. When the seeder's case size or
     * composition is remodelled later (e.g. 12 glasses become 1 box), the copies keep the old composition and
     * pickers take 12 boxes for a Faire case of 12 glasses (HELP-3764), so every copy is sent to review.
     */
    public function flagCopiesOfSeederProduct(Product $seederProduct): int
    {
        return Product::query()
            ->whereRaw('lower(code) = lower(?)', [$seederProduct->code])
            ->whereHas('shop', fn ($query) => $query->where('type', ShopTypeEnum::EXTERNAL)->where('seeder_shop_id', $seederProduct->shop_id))
            ->whereHas('tradeUnits')
            ->whereNull('units_review')
            ->update(['units_review' => self::BUCKET]);
    }

    /**
     * Copies left behind before seeder changes were flagged. A copy was only given trade units when its case size
     * matched the seeder's with one trade unit per unit, so a copy still shaped like that while the seeder's case
     * size has since changed (12 glasses became 1 box) was remodelled under it. Audits can't tell: those seeder
     * units changes were often written quietly.
     */
    public function flagCopiesOfRemodelledSeederProducts(?Command $command = null, bool $dryRun = false): int
    {
        $tradeUnitsTotal = fn (string $alias) => "(select sum(quantity) from model_has_trade_units where model_type = 'Product' and model_id = $alias.id)";

        $productIds = DB::table('products as copy')
            ->join('shops', 'shops.id', '=', 'copy.shop_id')
            ->join('products as seeder', function ($join) {
                $join->on('seeder.shop_id', '=', 'shops.seeder_shop_id')
                    ->whereRaw('lower(seeder.code) = lower(copy.code)');
            })
            ->where('shops.type', ShopTypeEnum::EXTERNAL->value)
            ->whereNull('copy.units_review')
            ->whereNull('copy.deleted_at')
            ->where('copy.state', '!=', ProductStateEnum::DISCONTINUED->value)
            ->where('copy.units', '>', 1)
            ->whereColumn('seeder.units', '!=', 'copy.units')
            ->whereRaw($tradeUnitsTotal('copy').' = copy.units')
            ->whereRaw($tradeUnitsTotal('seeder').' = seeder.units')
            ->pluck('copy.id');

        foreach (Product::whereIn('id', $productIds)->get() as $product) {
            $command?->line($product->slug.' case of '.trimDecimalZeros($product->units).' still picks the old seeder composition');
        }

        if (!$dryRun) {
            Product::whereIn('id', $productIds)->update(['units_review' => self::BUCKET]);
        }

        $command?->info($productIds->count().' products '.($dryRun ? 'would be ' : '').'flagged');

        return $productIds->count();
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
        if ($command->option('seeder-remodelled')) {
            $this->flagCopiesOfRemodelledSeederProducts($command, (bool) $command->option('dry-run'));

            return 0;
        }

        $this->handle($command, (bool) $command->option('dry-run'));

        return 0;
    }
}
