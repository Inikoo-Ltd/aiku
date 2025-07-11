<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Jun 2024 12:55:09 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use Illuminate\Support\Facades\DB;

class FetchAuroraProductHasOrgStock extends FetchAurora
{
    protected function parseModel(): void
    {
        $productStocks = [];
        foreach ($this->auroraModelData as $modelData) {
            $orgStock = $this->parseOrgStock($this->organisation->id.':'.$modelData->{'Product Part Part SKU'});

            if ($orgStock) {
                list($smallestDividend, $correspondingDivisor) = findSmallestFactors($modelData->{'Product Part Ratio'});
                $productStocks[$orgStock->id] = [
                    'quantity'        => $modelData->{'Product Part Ratio'},
                    'notes'           => $modelData->{'Product Part Note'} ?? null,
                    'source_id'       => $this->organisation->id.':'.$modelData->{'Product Part Key'},
                    'dividend'        => $smallestDividend,
                    'divisor'         => $correspondingDivisor,
                    'last_fetched_at' => now(),
                ];
            }
        }
        $this->parsedData['org_stocks'] = $productStocks;
    }

    protected function fetchData($id): object|null
    {
        return DB::connection('aurora')
            ->table('Product Part Bridge')
            ->where('Product Part Product ID', $id)->get();
    }
}
