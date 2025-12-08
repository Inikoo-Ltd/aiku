<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 06 Sept 2024 14:28:13 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use Illuminate\Support\Facades\DB;

class FetchAuroraHistoricSupplierProduct extends FetchAurora
{
    protected function parseModel(): void
    {
        $supplierProduct = $this->parseSupplierProduct($this->organisation->id.':'.$this->auroraModelData->{'Supplier Part Historic Supplier Part Key'});

        if (! $supplierProduct) {
            return;
        }
        $this->parsedData['supplier_product'] = $supplierProduct;

        $status = 0;
        if (DB::connection('aurora')->table('Supplier Part Dimension')->where('Supplier Part Historic Key', '=', $this->auroraModelData->{'Supplier Part Historic Key'})->exists()) {
            $status = 1;
        }

        $units = $this->auroraModelData->{'Supplier Part Historic Units Per Package'};
        if ($units == 0) {
            $units = 1;
        }

        $packagesPerCarton = $this->auroraModelData->{'Supplier Part Historic Packages Per Carton'};
        if (! $packagesPerCarton) {
            $packagesPerCarton = 1;
        }
        $units_per_carton = $units * $packagesPerCarton;
        if ($units_per_carton == 0) {
            $units_per_carton = 1;
        }
        $supplierProductCode = $this->auroraModelData->{'Supplier Part Historic Reference'} ?? 'missing-code-'.$this->auroraModelData->{'Supplier Part Historic Supplier Part Key'};

        $this->parsedData['historic_supplier_product'] = [
            'code' => $supplierProductCode,
            'units_per_pack' => $units,
            'units_per_carton' => $units_per_carton,
            'status' => $status,
            'source_id' => $this->organisation->id.':'.$this->auroraModelData->{'Supplier Part Historic Key'},
            'fetched_at' => now(),
            'last_fetched_at' => now(),
        ];

        if ($this->auroraModelData->{'Supplier Part Historic Carton CBM'}) {
            $this->parsedData['historic_supplier_product']['cbm'] = $this->auroraModelData->{'Supplier Part Historic Carton CBM'};
        }

    }

    protected function fetchData($id): ?object
    {
        return DB::connection('aurora')
            ->table('Supplier Part Historic Dimension')
            ->leftJoin(
                'Supplier Part Dimension',
                'Supplier Part Dimension.Supplier Part Key',
                '=',
                'Supplier Part Historic Dimension.Supplier Part Historic Supplier Part Key'
            )
            ->where('Supplier Part Historic Dimension.Supplier Part Historic Key', $id)->first();
    }
}
