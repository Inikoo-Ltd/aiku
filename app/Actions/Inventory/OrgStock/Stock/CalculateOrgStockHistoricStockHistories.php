<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 01 Apr 2026 15:32:59 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock\Stock;

use App\Actions\Helpers\CurrencyExchange\GetHistoricCurrencyExchange;
use App\Actions\Inventory\OrgStock\Stock\Concerns\CalculatesOrgStockHistories;
use App\Models\Inventory\Location;
use App\Models\Inventory\OrgStock;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

class CalculateOrgStockHistoricStockHistories
{
    use AsAction;
    use CalculatesOrgStockHistories;

    public bool $debug = false;

    public function handle(OrgStock $orgStock, Carbon $date, ?Command $command = null): array
    {
        $exchangeRate = GetHistoricCurrencyExchange::run($orgStock->organisation->currency, $orgStock->group->currency, $date);


        $orgStockLocationData = [];
        $locationsIds         = $this->getLocationsIds($orgStock, $date);
        $costPerSku           = $this->getCostPerSku($orgStock, $date);

        $lastSoldDate = $this->lastSoldDate($orgStock, $date);

        foreach ($locationsIds as $locationId) {
            $location = Location::withTrashed()->find($locationId);

            if ($location) {
                if ($this->debug) {
                    $command?->warn("Checking location $location->slug");
                }
                $wasLocationValid = $this->wasLocationValid($orgStock, $location, $date, $command);
                if ($wasLocationValid) {
                    $quantity = $this->getStockQuantity($orgStock, $location, $date);


                    $command?->line('Stock on '.$location->slug.' ('.$location->id.')  '.$date->format('Y-m-d').'  '.$quantity);
                    $orgStockLocationData[] = [
                        'location_id'     => $location->id,
                        'quantity'        => $quantity,
                        'org_stock_value' => $quantity * $costPerSku,
                        'grp_stock_value' => $quantity * $costPerSku * $exchangeRate,

                    ];
                }
            }
        }

        $this->persistOrgStockHistories($orgStock, $date, $orgStockLocationData, $costPerSku, $lastSoldDate);

        return $orgStockLocationData;
    }

    public function getCommandSignature(): string
    {
        return 'org_stock:calculate_historic_quantity_on_locations {orgStock : OrgStock ID or slug} {date?}';
    }

    public function asCommand(Command $command): int
    {
        $this->debug = true;
        if (is_numeric($command->argument('orgStock'))) {
            $orgStock = OrgStock::where('id', $command->argument('orgStock'))->firstOrFail();
        } else {
            $orgStock = OrgStock::where('slug', $command->argument('orgStock'))->firstOrFail();
        }

        if ($command->argument('date')) {
            $date = Carbon::parse($command->argument('date'));
        } else {
            $date = Carbon::now();
        }

        $command->line("Get Stock of $orgStock->slug  ($orgStock->id) on ".$date->format('Y-m-d'));
        $this->handle($orgStock, $date, $command);

        return 0;
    }

}
