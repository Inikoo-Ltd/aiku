<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Jun 2024 12:55:10 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use App\Enums\SupplyChain\SupplierProduct\SupplierProductStateEnum;
use Illuminate\Support\Facades\DB;

class FetchAuroraDeletedSupplierProduct extends FetchAurora
{
    protected function parseModel(): void
    {
        $deleted_at = $this->parseDatetime($this->auroraModelData->{'Supplier Part Deleted Date'});
        $auroraDeletedData = json_decode(gzuncompress($this->auroraModelData->{'Supplier Part Deleted Metadata'}));

        $supplier = $this->parseSupplier(
            $this->organisation->id.':'.$auroraDeletedData->{'Supplier Part Supplier Key'}
        );

        if (! $supplier) {
            return;
        }

        $this->parsedData['supplier'] = $supplier;

        if (! $auroraDeletedData->{'Supplier Part Part SKU'}) {
            return;
        }
        $stock = $this->parseStock($this->organisation->id.':'.$auroraDeletedData->{'Supplier Part Part SKU'});
        if (! $stock) {
            return;
        }

        $data = [];
        $settings = [];

        $state = match ($auroraDeletedData->{'Supplier Part Status'}) {
            'Discontinued', 'NoAvailable' => SupplierProductStateEnum::DISCONTINUED,
            default => SupplierProductStateEnum::ACTIVE,
        };

        if ($auroraDeletedData->{'Supplier Part From'} == '0000-00-00 00:00:00') {
            $created_at = null;
        } else {
            $created_at = $auroraDeletedData->{'Supplier Part From'};
        }

        $data['original_unit_cost'] = $auroraDeletedData->{'Supplier Part Unit Cost'} ?? 0;

        $code = strtolower($auroraDeletedData->{'Supplier Part Reference'}).'-'.$this->organisation->slug;
        if (str_starts_with($code, 'gbot-')) {
            $code .= '-'.$auroraDeletedData->{'Supplier Part Key'};
        }
        if (str_starts_with($code, 'tbm-')) {
            $code .= '-'.$auroraDeletedData->{'Supplier Part Key'};
        }
        if (str_starts_with($code, 'lbn-')) {
            $code .= '-'.$auroraDeletedData->{'Supplier Part Key'};
        }

        if (str_starts_with($code, 'hapi-')) {
            $code .= '-'.$auroraDeletedData->{'Supplier Part Key'};
        }

        $code = $code.'-deleted';

        $name = $auroraDeletedData->{'Supplier Part Description'};
        if ($name == '') {
            $name = $auroraDeletedData->{'Supplier Part Reference'};
        }

        $this->parsedData['supplierProduct'] =
            [
                'code' => $code,
                'name' => $name,

                'cost' => round($auroraDeletedData->{'Supplier Part Unit Cost'} ?? 0, 2),
                'units_per_pack' => $stock->units_per_pack,
                'units_per_carton' => $auroraDeletedData->{'Supplier Part Packages Per Carton'} * $stock->units_per_pack,

                'is_available' => false,
                'state' => $state,
                'stock_quantity_status' => 'no_applicable',
                'stock_id' => $stock->id,

                'deleted_at' => $deleted_at,
                'data' => $data,
                'settings' => $settings,
                'created_at' => $created_at,
                'source_id' => $this->organisation->id.':'.$auroraDeletedData->{'Supplier Part Key'},
                'fetched_at' => now(),
                'last_fetched_at' => now(),
            ];

        $this->parsedData['historicSupplierProductSourceID'] = $auroraDeletedData->{'Supplier Part Historic Key'};
    }

    protected function fetchData($id): ?object
    {
        return DB::connection('aurora')
            ->table('Supplier Part Deleted Dimension')
            ->where('Supplier Part Deleted Key', $id)->first();
    }
}
