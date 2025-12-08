<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Jun 2024 12:55:10 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use App\Enums\Production\Artefact\ArtefactStateEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FetchAuroraArtefact extends FetchAurora
{
    use WithAuroraParsers;

    protected function parseModel(): void
    {
        if ($this->auroraModelData->aiku_ignore == 'Yes') {
            return;
        }

        $production = $this->organisation->productions()->first();

        if (! $production) {
            return;
        }

        $auroraPartData = DB::connection('aurora')
            ->table('Part Dimension')
            ->where('Part SKU', $this->auroraModelData->{'Supplier Part Part SKU'})
            ->first();

        $tradeUnitReference = $this->cleanTradeUnitReference($auroraPartData->{'Part Reference'});
        $tradeUnitSlug = Str::lower($tradeUnitReference);

        $this->parsedData['trade_unit'] = $this->parseTradeUnit(
            $tradeUnitSlug,
            $auroraPartData->{'Part SKU'}
        );

        $this->parsedData['production'] = $production;

        $data = [];
        $settings = [];

        $status = true;
        if ($this->auroraModelData->{'Supplier Part Status'} == 'NoAvailable') {
            $status = false;
        }
        $state = match ($this->auroraModelData->{'Supplier Part Status'}) {
            'Discontinued', 'NoAvailable' => ArtefactStateEnum::DISCONTINUED,
            default => ArtefactStateEnum::ACTIVE,
        };

        //  if ($state == SupplierProductStateEnum::DISCONTINUED) {
        //      $status = false;
        //  }

        if ($this->auroraModelData->{'Supplier Part From'} == '0000-00-00 00:00:00') {
            $created_at = null;
        } else {
            $created_at = $this->auroraModelData->{'Supplier Part From'};
        }

        $data['original_unit_cost'] = $this->auroraModelData->{'Supplier Part Unit Cost'} ?? 0;

        $stock_quantity_status = match ($auroraPartData->{'Part Stock Status'}) {
            'Out_Of_Stock', 'Error' => 'out-of-stock',
            default => strtolower($auroraPartData->{'Part Stock Status'})
        };

        $name = $this->auroraModelData->{'Supplier Part Description'};
        if ($name == '') {
            $name = $this->auroraModelData->{'Supplier Part Reference'};
        }

        $code = $this->auroraModelData->{'Supplier Part Reference'};
        $code = str_replace('&', 'and', $code);
        $code = $this->cleanTradeUnitReference($code);

        $this->parsedData['artefact'] =
            [
                'code' => $code,
                'name' => $name,

                'cost' => round($this->auroraModelData->{'Supplier Part Unit Cost'} ?? 0, 2),
                'units_per_pack' => $auroraPartData->{'Part Units Per Package'},
                'units_per_carton' => $this->auroraModelData->{'Supplier Part Packages Per Carton'} * $auroraPartData->{'Part Units Per Package'},

                //  'status'                => $status,
                'state' => $state,
                'stock_quantity_status' => $stock_quantity_status,

                'data' => $data,
                'settings' => $settings,
                'created_at' => $created_at,
                'source_id' => $this->organisation->id.':'.$this->auroraModelData->{'Supplier Part Key'},
            ];
    }

    protected function fetchData($id): ?object
    {
        return DB::connection('aurora')
            ->table('Supplier Part Dimension as ssp')
            //    ->leftjoin('Supplier Dimension', 'Supplier Part Supplier Key', 'Supplier Key')
            //    ->leftjoin('Part Dimension', 'Supplier Part Part SKU', 'Part SKU')
            ->where('ssp.aiku_ignore', 'No')
            ->where('Supplier Part Key', $id)->first();
    }
}
