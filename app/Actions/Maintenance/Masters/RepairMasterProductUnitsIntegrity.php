<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 27 Jul 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Masters;

use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Models\Catalogue\Product;
use App\Models\Helpers\Currency;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterShop;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairMasterProductUnitsIntegrity
{
    use AsAction;

    public const float PRICE_TOLERANCE = 0.05;

    public string $commandSignature = 'repair:master_product_units_integrity {master_shop} {--fix : Write corrections (default is report only)}';

    /**
     * Classification ladder for a master asset whose following products disagree with it on units.
     * The trade-unit pivot arbitrates which side is stale, but pivots are synced from the master so
     * they are not independent evidence: every auto-fix additionally requires price corroboration
     * (product price matches master price in that currency within tolerance). No price data means
     * no proof, so the finding is reported for manual review instead of fixed.
     *  - product with different trade-unit composition        -> diff_trade_units (manual review)
     *  - multi trade-unit (bundle) master                      -> bundle (manual review)
     *  - master units == its own pivot quantity                -> product_stale_pivot (fix product; price-guarded)
     *  - all products agree AND consensus == pivot quantity    -> master_stale_consensus (fix master; price-guarded)
     *  - consensus contradicts pivot quantity                  -> consensus_conflicts_pivot (manual review)
     *  - products disagree among themselves                    -> no_consensus (manual review)
     *
     * @return array<int, array{bucket: string, code: string, master_units: float, suggested: float|null, detail: string, fixed: bool}>
     */
    public function handle(MasterAsset $masterAsset, bool $fix = false): array
    {
        $products = $masterAsset->products()
            ->whereNot('products.state', ProductStateEnum::DISCONTINUED)
            ->where('is_for_sale', true)
            ->where('not_follow_master_trade_units', false)
            ->with('shop')
            ->get();

        $masterUnits = $this->round($masterAsset->units);

        $mismatched = $products->filter(fn (Product $product) => $this->round($product->units) !== $masterUnits);
        if ($mismatched->isEmpty()) {
            return [];
        }

        $masterTradeUnits  = $this->tradeUnitQuantities('MasterAsset', [$masterAsset->id])->get($masterAsset->id, collect());
        $productTradeUnits = $this->tradeUnitQuantities('Product', $products->pluck('id')->all());

        $masterSignature      = $this->signature($masterTradeUnits);
        $masterPivotQ         = $masterTradeUnits->count() === 1 ? $this->round($masterTradeUnits->first()) : null;
        $masterSelfConsistent = $masterPivotQ !== null && $masterUnits === $masterPivotQ;

        $findings           = [];
        $sameSignatureCount = 0;

        /** @var Product $product */
        foreach ($mismatched as $product) {
            $productSignature = $this->signature($productTradeUnits->get($product->id, collect()));

            if ($productSignature !== $masterSignature) {
                $findings[] = $this->finding($masterAsset, 'diff_trade_units', null, $this->productDetail($product, $masterPivotQ), false);
                continue;
            }

            if ($masterTradeUnits->count() !== 1) {
                $findings[] = $this->finding($masterAsset, 'bundle', null, $this->productDetail($product, $masterPivotQ), false);
                continue;
            }

            if ($masterSelfConsistent) {
                $priceVerdict = $this->priceVerdict($masterAsset, collect([$product]));

                if ($priceVerdict !== 'ok') {
                    $findings[] = $this->finding($masterAsset, 'product_stale_pivot_price_'.$priceVerdict, null, $this->productDetail($product, $masterPivotQ), false);
                    continue;
                }

                $fixed = false;
                if ($fix) {
                    $product->updateQuietly(['units' => $masterUnits]);
                    $fixed = true;
                }
                $findings[] = $this->finding($masterAsset, 'product_stale_pivot', $masterUnits, $this->productDetail($product, $masterPivotQ), $fixed);
                continue;
            }

            $sameSignatureCount++;
        }

        if ($sameSignatureCount > 0) {
            $findings[] = $this->masterLevelFinding($masterAsset, $products, $masterPivotQ, $fix);
        }

        return $findings;
    }

    private function masterLevelFinding(MasterAsset $masterAsset, Collection $products, ?float $masterPivotQ, bool $fix): array
    {
        $distinctProductUnits = $products->map(fn (Product $product) => $this->round($product->units))->unique();
        $detail               = sprintf('%d products, units: %s, pivot_q=%s', $products->count(), $distinctProductUnits->join('/'), $masterPivotQ ?? 'n/a');

        if ($distinctProductUnits->count() > 1) {
            return $this->finding($masterAsset, 'no_consensus', null, $detail, false);
        }

        $consensusUnits = $distinctProductUnits->first();

        if ($masterPivotQ === null || $consensusUnits !== $masterPivotQ) {
            return $this->finding($masterAsset, 'consensus_conflicts_pivot', null, $detail, false);
        }

        $priceVerdict = $this->priceVerdict($masterAsset, $products);
        if ($priceVerdict !== 'ok') {
            return $this->finding($masterAsset, 'master_stale_consensus_price_'.$priceVerdict, null, $detail, false);
        }

        $fixed = false;
        if ($fix) {
            $masterAsset->updateQuietly(['units' => $consensusUnits]);
            $fixed = true;
        }

        return $this->finding($masterAsset, 'master_stale_consensus', $consensusUnits, $detail, $fixed);
    }

    /**
     * Product pivots are synced from the master, so matching signatures alone don't prove which
     * side is right. Price agreement is the independent evidence: 'ok' only when every product has
     * a comparable master price in its currency and all are within tolerance.
     */
    private function priceVerdict(MasterAsset $masterAsset, Collection $products): string
    {
        $currencies = $this->currenciesById();
        $verdict    = 'ok';

        foreach ($products as $product) {
            $currencyCode = $currencies->get($product->currency_id)?->code;
            $masterPrice  = (float) data_get($masterAsset->master_prices, "$currencyCode.value");

            if (!$masterPrice || (float) $product->price <= 0) {
                $verdict = 'unverifiable';
                continue;
            }

            if (abs((float) $product->price / $masterPrice - 1) > self::PRICE_TOLERANCE) {
                return 'divergent';
            }
        }

        return $verdict;
    }

    /**
     * @return Collection<int, Collection<int, float>> model_id => (trade_unit_id => quantity)
     */
    private function tradeUnitQuantities(string $modelType, array $modelIDs): Collection
    {
        return DB::table('model_has_trade_units')
            ->where('model_type', $modelType)
            ->whereIn('model_id', $modelIDs)
            ->get()
            ->groupBy('model_id')
            ->map(fn ($rows) => $rows->pluck('quantity', 'trade_unit_id'));
    }

    private function signature(Collection $tradeUnitQuantities): string
    {
        return $tradeUnitQuantities
            ->map(fn ($quantity) => $this->round($quantity))
            ->sortKeys()
            ->map(fn ($quantity, $tradeUnitID) => $tradeUnitID.'x'.$quantity)
            ->join(',');
    }

    private function round(mixed $value): float
    {
        return round((float) $value, 3);
    }

    private function productDetail(Product $product, ?float $masterPivotQ): string
    {
        return sprintf('product %s (%s) units=%s pivot_q=%s', $product->slug, $product->shop->code ?? '?', $this->round($product->units), $masterPivotQ ?? 'n/a');
    }

    private function finding(MasterAsset $masterAsset, string $bucket, ?float $suggested, string $detail, bool $fixed): array
    {
        return [
            'bucket'       => $bucket,
            'code'         => $masterAsset->code,
            'master_units' => $this->round($masterAsset->units),
            'suggested'    => $suggested,
            'detail'       => $detail,
            'fixed'        => $fixed,
        ];
    }

    private ?Collection $currenciesById = null;

    private function currenciesById(): Collection
    {
        return $this->currenciesById ??= Currency::all()->keyBy('id');
    }

    public function asCommand(Command $command): int
    {
        ini_set('memory_limit', '2G');
        DB::connection()->disableQueryLog();

        $masterShop = MasterShop::where('slug', $command->argument('master_shop'))->firstOrFail();
        $fix        = (bool) $command->option('fix');

        if (!$fix) {
            $command->warn('REPORT ONLY: pass --fix to write corrections');
        }

        $bucketCounts = [];
        MasterAsset::where('master_shop_id', $masterShop->id)
            ->where('is_main', true)
            ->where('status', true)
            ->orderBy('id')
            ->chunkById(250, function ($masterAssets) use ($fix, $command, &$bucketCounts) {
                foreach ($masterAssets as $masterAsset) {
                    foreach ($this->handle($masterAsset, $fix) as $finding) {
                        $bucketCounts[$finding['bucket']] = ($bucketCounts[$finding['bucket']] ?? 0) + 1;
                        $command->line($this->format($finding));
                    }
                }
            });

        $command->newLine();
        ksort($bucketCounts);
        foreach ($bucketCounts as $bucket => $count) {
            $command->info("$bucket: $count");
        }
        $command->info('Total: '.array_sum($bucketCounts).' findings'.($fix ? ' (fixes applied where safe)' : ''));

        return 0;
    }

    private function format(array $finding): string
    {
        $parts = [
            "[{$finding['code']}]",
            $finding['bucket'],
            'master_units='.$finding['master_units'],
            $finding['detail'],
        ];

        if ($finding['suggested'] !== null) {
            $parts[] = ($finding['fixed'] ? 'FIXED to ' : 'suggest ').$finding['suggested'];
        } else {
            $parts[] = 'review manually';
        }

        return implode(' | ', $parts);
    }
}
