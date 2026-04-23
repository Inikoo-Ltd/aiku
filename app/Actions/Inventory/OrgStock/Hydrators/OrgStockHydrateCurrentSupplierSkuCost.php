<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 23 Apr 2026 17:47:12 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock\Hydrators;

use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Actions\Inventory\OrgStock\Stock\Concerns\CalculatesOrgStockHistories;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Models\Inventory\OrgStock;
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
    }

    public function getSKUCost(OrgStock $orgStock): float|int|null
    {
        foreach ($orgStock->orgSupplierProducts as $orgSupplierProduct) {
            if (!$orgSupplierProduct->pivot->status) {
                continue;
            }

            $unitCostSupplierCurrency = $orgSupplierProduct->supplierProduct->cost;

            $unitCost = $unitCostSupplierCurrency * GetCurrencyExchange::run(
                $orgSupplierProduct->supplierProduct->currency,
                $orgStock->organisation->currency
            );

            $unitCost = $unitCost * (1 + $orgSupplierProduct->supplierProduct->extra_costs);

            //Todo, this is probably wrong, wer need to find the relation units/SKUs form (org_)supplier_product to org_stock
            // e.g. return $unitCost*$orgSupplierProduct->pivot->quantity;

            return $unitCost * $orgStock->packed_in;
        }

        return null;
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
