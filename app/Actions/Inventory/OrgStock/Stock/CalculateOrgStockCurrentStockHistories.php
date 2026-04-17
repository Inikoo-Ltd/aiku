<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 01 Apr 2026 15:32:59 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock\Stock;

use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Actions\Inventory\OrgStock\Stock\Concerns\CalculatesOrgStockHistories;
use App\Models\Inventory\LocationOrgStock;
use App\Models\Inventory\OrgStock;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
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


        $date         = now();
        $exchangeRate = GetCurrencyExchange::run($orgStock->organisation->currency, $orgStock->group->currency);

        $orgStockLocationData  = [];
        $locationsOrgStocksIds = $this->getLocationsOrgStocksIds($orgStock);
        $costPerSku            = $this->getCostPerSku($orgStock, $date);
        $lastSoldDate          = $this->lastSoldDate($orgStock, $date);


        foreach ($locationsOrgStocksIds as $locationsOrgStocksId) {
            $locationOrgStock = LocationOrgStock::find($locationsOrgStocksId);
            if ($locationOrgStock) {
                $quantity               = $locationOrgStock->quantity;
                $orgStockLocationData[] = [
                    'location_id'     => $locationOrgStock->location_id,
                    'quantity'        => $quantity,
                    'org_stock_value' => $quantity * $costPerSku,
                    'grp_stock_value' => $quantity * $costPerSku * $exchangeRate,
                ];
            }
        }

        $this->persistOrgStockHistories($orgStock, $date, $orgStockLocationData, $costPerSku, $lastSoldDate, $this->hydrateDelay);

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
