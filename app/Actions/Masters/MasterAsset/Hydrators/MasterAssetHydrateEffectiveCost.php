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
use App\Models\Production\Artefact;
use App\Models\Production\ManufacturePayBand;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Computes master_assets.effective_cost: the cost of one outer in group currency, each trade unit
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

    /** @var array<string, float|null> */
    private array $productionUnitCosts = [];

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
        $cost = $this->cost($masterAsset);

        if ($cost === null) {
            if ($masterAsset->effective_cost !== null) {
                $masterAsset->updateQuietly(['effective_cost' => null]);
            }

            return;
        }

        $effectiveCost = round($cost, 4);

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

        $currentCost = $this->cost($masterAsset);
        $newCost     = $this->cost($masterAsset, $stockId, $priceRatio);

        if (!$currentCost || $newCost === null) {
            return null;
        }

        return round((float) $masterAsset->effective_cost * $newCost / $currentCost, 4);
    }

    /**
     * One outer in group currency: each trade unit at the average unit cost of the organisations costing it,
     * weighted by the units each has available (sales are served where the stock is), or a plain average when
     * none has stock, times its quantity in the outer. Null when no trade unit has a cost.
     */
    private function cost(MasterAsset $masterAsset, ?int $repricedStockId = null, float $priceRatio = 1): ?float
    {
        $total   = 0.0;
        $costed  = false;
        $toGroup = fn (int $organisationId) => $this->getOrgToGroupRate($organisationId);

        foreach ($masterAsset->tradeUnits as $tradeUnit) {
            $orgStocks   = self::costingOrgStocks($tradeUnit->orgStocks, $toGroup);
            $makersOnly  = $orgStocks->every(fn (OrgStock $orgStock) => $orgStock->is_made_in_house)
                && $orgStocks->count() < $tradeUnit->orgStocks->count();
            $buyerMax    = $tradeUnit->orgStocks->reject(fn (OrgStock $orgStock) => $orgStock->is_made_in_house)
                ->map(fn (OrgStock $orgStock) => self::skuCost($orgStock) / (float) ($orgStock->packed_in ?: 1) * (float) $toGroup($orgStock->organisation_id))
                ->max();

            $weightedSum = $weights = $plainSum = 0.0;
            $count       = 0;
            foreach ($orgStocks as $orgStock) {
                $rate    = $toGroup($orgStock->organisation_id);
                $skuCost = self::skuCost($orgStock);
                if (!$rate || $skuCost <= 0) {
                    continue;
                }
                if ($repricedStockId !== null && $orgStock->stock_id === $repricedStockId) {
                    $skuCost *= $priceRatio;
                }

                $unitCost = $skuCost / (float) ($orgStock->packed_in ?: 1) * $rate;
                if ($makersOnly) {
                    $floor    = ($this->productionUnitCost($orgStock->organisation_id, $tradeUnit->id) ?? 0) * $rate;
                    $unitCost = max($unitCost, $buyerMax ? min($floor, $buyerMax) : $floor);
                }

                $available    = max(0, (float) $orgStock->quantity_available);
                $weightedSum += $unitCost * $available;
                $weights     += $available;
                $plainSum    += $unitCost;
                $count++;
            }

            if (!$count) {
                continue;
            }

            $costed = true;
            $total += ($weights > 0 ? $weightedSum / $weights : $plainSum / $count) * (float) $tradeUnit->pivot->quantity;
        }

        return $costed ? $total : null;
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

    /**
     * What one trade unit costs the organisation making it today, from its artefact's recipe: raw materials at
     * their current cost plus labour at each step's target per hour paid at band 0. A floor under the maker's
     * stock cost, which may be years old (INI-567). Null without a recipe.
     */
    public function productionUnitCost(int $organisationId, int $tradeUnitId): ?float
    {
        $key = $organisationId.':'.$tradeUnitId;
        if (array_key_exists($key, $this->productionUnitCosts)) {
            return $this->productionUnitCosts[$key];
        }

        $artefact = Artefact::where('organisation_id', $organisationId)
            ->where('trade_unit_id', $tradeUnitId)
            ->orderByRaw("state = 'active' desc")
            ->orderBy('id')
            ->first(['id', 'production_id']);

        if (!$artefact) {
            return $this->productionUnitCosts[$key] = null;
        }

        $materials = (float) DB::table('artefacts_manufacture_tasks')
            ->join('recipe_step_raw_materials', 'recipe_step_raw_materials.artefact_manufacture_task_id', '=', 'artefacts_manufacture_tasks.id')
            ->join('raw_materials', 'raw_materials.id', '=', 'recipe_step_raw_materials.raw_material_id')
            ->where('artefacts_manufacture_tasks.artefact_id', $artefact->id)
            ->whereNull('raw_materials.deleted_at')
            ->sum(DB::raw('recipe_step_raw_materials.quantity_per_unit * raw_materials.unit_cost'));

        $hoursPerUnit = (float) DB::table('artefacts_manufacture_tasks')
            ->join('manufacture_tasks', 'manufacture_tasks.id', '=', 'artefacts_manufacture_tasks.manufacture_task_id')
            ->where('artefacts_manufacture_tasks.artefact_id', $artefact->id)
            ->sum(DB::raw('COALESCE(artefacts_manufacture_tasks.units_per_artefact, 1) / NULLIF(COALESCE(artefacts_manufacture_tasks.standard_rate, manufacture_tasks.standard_rate), 0)'));

        $hourlyRate = $hoursPerUnit > 0
            ? (float) ManufacturePayBand::query()->effectiveAt($artefact->production_id, now())->where('code', '0')->value('hourly_rate')
            : 0;

        $cost = $materials + $hoursPerUnit * $hourlyRate;

        return $this->productionUnitCosts[$key] = $cost > 0 ? $cost : null;
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
