<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 26 Jul 2026 14:40:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterAsset\Hydrators;

use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Models\Inventory\OrgStock;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterShop;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Computes master_assets.effective_cost: the cost of one outer in group currency,
 * averaged across the organisations that stock it, weighted by their available stock
 * (sales are served where the stock is). Falls back to a simple average when nothing
 * is in stock anywhere. A trade unit made in-house counts only the organisations making it.
 * Per-org cost mirrors the product-creation math:
 * current_supplier_sku_cost / packed_in * trade unit quantity, org → group currency.
 */
class MasterAssetHydrateEffectiveCost implements ShouldBeUnique
{
    use AsAction;

    public string $jobQueue = 'low-priority';

    public string $commandSignature = 'master_assets:hydrate_effective_cost {master_shop? : master shop slug, all if omitted}';

    /** @var array<int, float|null> */
    private array $orgToGroupRate = [];

    public function getJobUniqueId(MasterAsset $masterAsset): string
    {
        return (string) $masterAsset->id;
    }

    /**
     * Any change to an org stock's cost inputs invalidates the effective cost of every
     * master asset built from its trade units, so re-hydrate them instead of waiting
     * for the nightly sweep.
     */
    public static function dispatchForOrgStock(OrgStock $orgStock): void
    {
        $tradeUnitIds = $orgStock->tradeUnits()->pluck('trade_units.id');

        if ($tradeUnitIds->isEmpty()) {
            return;
        }

        MasterAsset::where('status', true)
            ->where('is_main', true)
            ->whereIn('id', DB::table('model_has_trade_units')
                ->where('model_type', 'MasterAsset')
                ->whereIn('trade_unit_id', $tradeUnitIds)
                ->pluck('model_id'))
            ->each(fn (MasterAsset $masterAsset) => self::dispatch($masterAsset));
    }

    public function handle(MasterAsset $masterAsset): void
    {
        $perOrg = $this->costsByOrganisation($masterAsset);

        if (!$perOrg) {
            if ($masterAsset->effective_cost !== null) {
                $masterAsset->updateQuietly(['effective_cost' => null]);
            }

            return;
        }

        $weightedCost = $this->weightedCost($perOrg);
        if ($weightedCost === null) {
            return;
        }

        $effectiveCost = round($weightedCost, 4);

        if ((float) $masterAsset->effective_cost !== $effectiveCost) {
            $masterAsset->updateQuietly(['effective_cost' => $effectiveCost]);
        }
    }

    /**
     * The effective cost once one stock is bought at a new price: its share of the cost moves by
     * the price change and the rest stays, so a bundle moves by that component's part only.
     */
    public function costWithStockPriceChange(MasterAsset $masterAsset, int $stockId, float $priceRatio): ?float
    {
        if ($masterAsset->effective_cost === null) {
            return null;
        }

        $currentCost = $this->weightedCost($this->costsByOrganisation($masterAsset));
        $newCost     = $this->weightedCost($this->costsByOrganisation($masterAsset, $stockId, $priceRatio));

        if (!$currentCost || $newCost === null) {
            return null;
        }

        return round((float) $masterAsset->effective_cost * $newCost / $currentCost, 4);
    }

    /**
     * @return array<int, array{cost: float, availability: array<int, float>, weight: float}>
     */
    private function costsByOrganisation(MasterAsset $masterAsset, ?int $repricedStockId = null, float $priceRatio = 1): array
    {
        $perOrg = [];

        foreach ($masterAsset->tradeUnits as $tradeUnit) {
            $tradeUnitQuantity = (float) $tradeUnit->pivot->quantity;

            foreach (self::costingOrgStocks($tradeUnit->orgStocks, fn (int $organisationId) => $this->getOrgToGroupRate($organisationId)) as $orgStock) {
                $skuCost = self::skuCost($orgStock);
                if ($skuCost <= 0) {
                    continue;
                }

                if ($repricedStockId !== null && $orgStock->stock_id === $repricedStockId) {
                    $skuCost *= $priceRatio;
                }

                $unitCost = $skuCost / (float) ($orgStock->packed_in ?: 1);

                $perOrg[$orgStock->organisation_id]['cost'] = ($perOrg[$orgStock->organisation_id]['cost'] ?? 0)
                    + $unitCost * $tradeUnitQuantity;
                $perOrg[$orgStock->organisation_id]['availability'][$tradeUnit->id]
                    = ($perOrg[$orgStock->organisation_id]['availability'][$tradeUnit->id] ?? 0)
                    + max(0, (float) $orgStock->quantity_available) / ($tradeUnitQuantity ?: 1);
            }
        }

        // An org's weight is the sellable outers it can actually assemble: for a multi
        // trade unit bundle that is bounded by the scarcest component, not the sum of
        // all components' stock (which double-counts bundles vs single-unit assets)
        foreach ($perOrg as &$orgData) {
            $orgData['weight'] = min($orgData['availability']);
        }
        unset($orgData);

        $coveringEveryTradeUnit = array_filter($perOrg, fn (array $orgData) => count($orgData['availability']) === $masterAsset->tradeUnits->count());

        return $coveringEveryTradeUnit ?: $perOrg;
    }

    /**
     * A trade unit one of our organisations makes is costed by its makers only: the others buy it from them,
     * so their purchase price is the maker's selling price, not a cost (INI-567). The flag is set by any artefact,
     * so a maker counts only with a real purchase price that is plausible against what the others paid for it:
     * at most their dearest price and at least a fifth of their cheapest. Otherwise every organisation counts.
     *
     * @param  Collection<int, OrgStock>  $orgStocks
     * @param  callable(int): (float|null)|null  $toGroupRate
     * @return Collection<int, OrgStock>
     */
    public static function costingOrgStocks(Collection $orgStocks, ?callable $toGroupRate = null): Collection
    {
        $groupUnitCost = function (OrgStock $orgStock) use ($toGroupRate): float {
            $rate = $toGroupRate ? (float) $toGroupRate($orgStock->organisation_id) : 1.0;

            return self::skuCost($orgStock) / (float) ($orgStock->packed_in ?: 1) * $rate;
        };

        $buyerCosts = $orgStocks->reject(fn (OrgStock $orgStock) => $orgStock->is_made_in_house)
            ->map($groupUnitCost)
            ->filter(fn (float $cost) => $cost > 0);

        $makers = $orgStocks->filter(function (OrgStock $orgStock) use ($groupUnitCost, $buyerCosts) {
            if (!$orgStock->is_made_in_house || (float) $orgStock->lpp_per_sku <= 0) {
                return false;
            }
            $cost = $groupUnitCost($orgStock);

            return $cost > 0 && ($buyerCosts->isEmpty() || ($cost <= $buyerCosts->max() && $cost >= $buyerCosts->min() * 0.2));
        });

        return $makers->isNotEmpty() ? $makers : $orgStocks;
    }

    private static function skuCost(OrgStock $orgStock): float
    {
        foreach ([$orgStock->lpp_per_sku, $orgStock->current_supplier_sku_cost, $orgStock->sku_value] as $cost) {
            if ((float) $cost > 0) {
                return (float) $cost;
            }
        }

        return 0;
    }

    /**
     * @param  array<int, array{cost: float, availability: array<int, float>, weight: float}>  $perOrg
     */
    private function weightedCost(array $perOrg): ?float
    {
        $weightedSum = 0;
        $totalWeight = 0;
        $plainSum    = 0;
        $orgCount    = 0;

        foreach ($perOrg as $organisationId => $data) {
            $rate = $this->getOrgToGroupRate($organisationId);
            if (!$rate) {
                continue;
            }

            $groupCost = $data['cost'] * $rate;

            $weightedSum += $groupCost * $data['weight'];
            $totalWeight += $data['weight'];
            $plainSum    += $groupCost;
            $orgCount++;
        }

        if (!$orgCount) {
            return null;
        }

        return $totalWeight > 0 ? $weightedSum / $totalWeight : $plainSum / $orgCount;
    }

    private function getOrgToGroupRate(int $organisationId): ?float
    {
        if (!array_key_exists($organisationId, $this->orgToGroupRate)) {
            $organisation = Organisation::find($organisationId);

            $this->orgToGroupRate[$organisationId] = $organisation
                ? GetCurrencyExchange::run($organisation->currency, $organisation->group->currency)
                : null;
        }

        return $this->orgToGroupRate[$organisationId];
    }

    public function asCommand(Command $command): int
    {
        ini_set('memory_limit', '2G');
        DB::connection()->disableQueryLog();

        $query = MasterAsset::where('status', true)->where('is_main', true);

        if ($command->argument('master_shop')) {
            $masterShop = MasterShop::where('slug', $command->argument('master_shop'))->firstOrFail();
            $query->where('master_shop_id', $masterShop->id);
        }

        $count = 0;
        $query->with('tradeUnits.orgStocks')->orderBy('id')
            ->chunkById(250, function ($masterAssets) use (&$count, $command) {
                foreach ($masterAssets as $masterAsset) {
                    $this->handle($masterAsset);
                    $count++;
                }
                $command->getOutput()->write('.');
            });

        $command->newLine();
        $command->info("Effective cost hydrated for $count master assets");

        return 0;
    }
}
