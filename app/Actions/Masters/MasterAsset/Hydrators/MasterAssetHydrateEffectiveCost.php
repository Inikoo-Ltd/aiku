<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 26 Jul 2026 14:40:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterAsset\Hydrators;

use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterShop;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Computes master_assets.effective_cost: the cost of one outer in group currency,
 * averaged across the organisations that stock it, weighted by their available stock
 * (sales are served where the stock is). Falls back to a simple average when nothing
 * is in stock anywhere. Per-org cost mirrors the product-creation math:
 * current_supplier_sku_cost / packed_in * trade unit quantity, org → group currency.
 */
class MasterAssetHydrateEffectiveCost
{
    use AsAction;

    public string $commandSignature = 'master_assets:hydrate_effective_cost {master_shop? : master shop slug, all if omitted}';

    /** @var array<int, float|null> */
    private array $orgToGroupRate = [];

    public function handle(MasterAsset $masterAsset): void
    {
        $perOrg = [];

        foreach ($masterAsset->tradeUnits as $tradeUnit) {
            $tradeUnitQuantity = (float) $tradeUnit->pivot->quantity;

            foreach ($tradeUnit->orgStocks as $orgStock) {
                $skuCost = (float) ($orgStock->current_supplier_sku_cost ?? 0);
                if ($skuCost <= 0) {
                    $skuCost = (float) ($orgStock->sku_value ?? 0);
                }
                if ($skuCost <= 0) {
                    continue;
                }

                $unitCost = $skuCost / (float) ($orgStock->packed_in ?: 1);

                $perOrg[$orgStock->organisation_id]['cost'] = ($perOrg[$orgStock->organisation_id]['cost'] ?? 0)
                    + $unitCost * $tradeUnitQuantity;
                // ponytail: weight from summed sku availability, exact sellable-outers per
                // multi trade unit bundle not worth the maths here
                $perOrg[$orgStock->organisation_id]['weight'] = ($perOrg[$orgStock->organisation_id]['weight'] ?? 0)
                    + max(0, (float) $orgStock->quantity_available);
            }
        }

        if (!$perOrg) {
            if ($masterAsset->effective_cost !== null) {
                $masterAsset->updateQuietly(['effective_cost' => null]);
            }

            return;
        }

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
            return;
        }

        $effectiveCost = round($totalWeight > 0 ? $weightedSum / $totalWeight : $plainSum / $orgCount, 4);

        if ((float) $masterAsset->effective_cost !== $effectiveCost) {
            $masterAsset->updateQuietly(['effective_cost' => $effectiveCost]);
        }
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
