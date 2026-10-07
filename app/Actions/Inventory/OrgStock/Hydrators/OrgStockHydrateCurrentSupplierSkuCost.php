<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 23 Apr 2026 17:47:12 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock\Hydrators;

use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Actions\Inventory\OrgStock\Stock\Concerns\CalculatesOrgStockHistories;
use App\Actions\Masters\MasterAsset\Hydrators\MasterAssetHydrateEffectiveCost;
use App\Actions\Production\RawMaterial\Hydrators\RawMaterialHydrateFromOrgStock;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Models\Inventory\OrgStock;
use App\Models\Production\RawMaterial;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class OrgStockHydrateCurrentSupplierSkuCost implements ShouldBeUnique
{
    use AsAction;
    use CalculatesOrgStockHistories;

    public string $commandSignature = 'org_stocks:current_supplier_sku_cost {--a|all}';


    public function getJobUniqueId(OrgStock $orgStock): string
    {
        return $orgStock->id;
    }

    public function handle(OrgStock $orgStock): void
    {
        $skuCost = $this->getSKUCost($orgStock);
        $orgStock->update([
            'current_supplier_sku_cost' => $skuCost
        ]);

        if ($orgStock->wasChanged('current_supplier_sku_cost')) {
            MasterAssetHydrateEffectiveCost::dispatchForOrgStock($orgStock);
        }

        foreach (RawMaterial::where('org_stock_id', $orgStock->id)->get() as $rawMaterial) {
            RawMaterialHydrateFromOrgStock::dispatch($rawMaterial);
        }
    }

    public function getSupplierUnitCost(OrgStock $orgStock): float|int|null
    {
        $orgSupplierProduct = $orgStock->orgSupplierProducts->first(fn ($orgSupplierProduct) => $orgSupplierProduct->pivot->status);
        if (!$orgSupplierProduct) {
            return null;
        }

        $supplierProduct = $orgSupplierProduct->supplierProduct;

        return $supplierProduct->cost
            * GetCurrencyExchange::run($supplierProduct->currency, $orgStock->organisation->currency)
            * (1 + $supplierProduct->extra_costs);
    }

    public function getSKUCost(OrgStock $orgStock): float|int|null
    {
        $unitCost = $this->getSupplierUnitCost($orgStock);
        if ($unitCost === null) {
            return null;
        }

        //Todo, this is probably wrong, wer need to find the relation units/SKUs form (org_)supplier_product to org_stock
        // e.g. return $unitCost*$orgSupplierProduct->pivot->quantity;

        $skuCost = $unitCost * $orgStock->packed_in;

        // ponytail: some Aurora supplier parts store a per-carton cost while packed_in
        // stays 1, inflating the SKU cost ~50-200x (HELP-2965). Until the supplier
        // product -> org stock unit relation is resolved (Todo above), distrust any
        // supplier cost more than 10x away from the last-in sku_value.
        $skuValue = (float) ($orgStock->sku_value ?? 0);
        if ($skuValue > 0 && ($skuCost > $skuValue * 10 || $skuCost < $skuValue / 10)) {
            return $skuValue;
        }

        return $skuCost;
    }

    public function asCommand(Command $command): int
    {
        $query = OrgStock::query()->whereNull('deleted_at');

        if (!$command->option('all')) {
            $query->where('state', '!=', OrgStockStateEnum::DISCONTINUED->value);
        }

        $count = $query->count();
        $bar   = $command->getOutput()->createProgressBar($count);
        $bar->setFormat('debug');
        $bar->start();

        $query->chunk(1000, function ($orgStocks) use ($bar) {
            foreach ($orgStocks as $orgStock) {
                $this->handle($orgStock);
                $bar->advance();
            }
        });

        $bar->finish();

        return 0;
    }

}
