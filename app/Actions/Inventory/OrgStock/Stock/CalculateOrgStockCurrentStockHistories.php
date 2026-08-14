<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 01 Apr 2026 15:32:59 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock\Stock;

use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateStockValue;
use App\Actions\Inventory\OrgStock\Stock\Concerns\CalculatesOrgStockHistories;
use App\Models\Inventory\LocationOrgStock;
use App\Models\Inventory\OrgStock;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

class CalculateOrgStockCurrentStockHistories implements ShouldBeUnique
{
    use AsAction;
    use CalculatesOrgStockHistories;

    public bool $debug = false;
    public int $hydrateDelay = 60;

    public function getJobUniqueId(?int $orgStockId): string
    {
        return $orgStockId ?? 'empty';
    }

    public function asJob(?int $orgStockId): void
    {
        $this->hydrateDelay = 15;
        $this->handle($orgStockId);
    }

    public function handle(?int $orgStockId): array
    {
        if (!$orgStockId) {
            return [];
        }
        $orgStock = OrgStock::find($orgStockId);
        if (!$orgStock) {
            return [];
        }


        $date         = Carbon::now();
        $exchangeRate = GetCurrencyExchange::run($orgStock->organisation->currency, $orgStock->group->currency);

        $orgStockLocationData  = [];
        $locationsOrgStocksIds = $this->getLocationsOrgStocksIds($orgStock);
        $costPerSku            = $this->getLppPerSku($orgStock, $date);
        $valuation             = $this->getValuationPerSku($orgStock, $date);
        $wacPerSku             = $valuation['wac'];
        $fifoPerSku            = $valuation['fifo'];
        $lastSoldDate          = $this->lastSoldDate($orgStock, $date);


        foreach ($locationsOrgStocksIds as $locationsOrgStocksId) {
            $locationOrgStock = LocationOrgStock::find($locationsOrgStocksId);
            if ($locationOrgStock) {
                $quantity               = $locationOrgStock->quantity;
                $orgStockLocationData[] = [
                    'location_id'         => $locationOrgStock->location_id,
                    'quantity'            => $quantity,
                    'org_stock_lpp_value'     => $quantity * $costPerSku,
                    'grp_stock_lpp_value'     => $quantity * $costPerSku * $exchangeRate,
                    'org_stock_wac_value' => $wacPerSku === null ? null : $quantity * $wacPerSku,
                    'grp_stock_wac_value' => $wacPerSku === null ? null : $quantity * $wacPerSku * $exchangeRate,
                    'org_stock_fifo_value' => $fifoPerSku === null ? null : $quantity * $fifoPerSku,
                    'grp_stock_fifo_value' => $fifoPerSku === null ? null : $quantity * $fifoPerSku * $exchangeRate,
                ];
            }
        }

        $this->persistOrgStockHistories($orgStock, $date, $orgStockLocationData, $costPerSku, $lastSoldDate, $this->hydrateDelay, $wacPerSku, $fifoPerSku);

        $officialPerSku = $fifoPerSku > 0 ? $fifoPerSku : $costPerSku;
        if ($officialPerSku > 0) {
            $orgStock->update(['sku_value' => $officialPerSku]);
        }

        OrgStockHydrateStockValue::run($orgStock);

        return $orgStockLocationData;
    }


    private function getLocationsOrgStocksIds(OrgStock $orgStock): array
    {
        return LocationOrgStock::where('org_stock_id', $orgStock->id)->pluck('id')->toArray();
    }


    public function getCommandSignature(): string
    {
        return 'org_stock:calculate_current_quantity_on_locations {orgStock : OrgStock ID or slug}';
    }

    public function asCommand(Command $command): int
    {
        $this->debug = true;
        if (is_numeric($command->argument('orgStock'))) {
            $orgStock = OrgStock::where('id', $command->argument('orgStock'))->firstOrFail();
        } else {
            $orgStock = OrgStock::where('slug', $command->argument('orgStock'))->firstOrFail();
        }

        $command->line("Get Stock of $orgStock->slug  ($orgStock->id) on ".now()->format('Y-m-d'));
        $this->handle($orgStock->id);

        return 0;
    }

}
