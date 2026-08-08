<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 08 Aug 2026 20:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use App\Enums\Production\RawMaterial\RawMaterialStateEnum;
use App\Enums\Production\RawMaterial\RawMaterialStockStatusEnum;
use App\Enums\Production\RawMaterial\RawMaterialTypeEnum;
use App\Enums\Production\RawMaterial\RawMaterialUnitEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FetchAuroraRawMaterial extends FetchAurora
{
    protected function parseModel(): void
    {
        $production = $this->organisation->productions()->first();
        if (!$production) {
            print "Error No production found in organisation ".$this->organisation->slug." for raw material ".$this->auroraModelData->{'Raw Material Key'}."\n";

            return;
        }

        $this->parsedData['production'] = $production;

        $code = preg_replace('/[^A-Za-z0-9_-]/', '-', trim($this->auroraModelData->{'Raw Material Code'}));

        $description = trim((string)$this->auroraModelData->{'Raw Material Description'});
        if ($description == '') {
            $description = $code;
        }

        $type = match ($this->auroraModelData->{'Raw Material Type'}) {
            'Consumable' => RawMaterialTypeEnum::CONSUMABLE,
            'Intermediate' => RawMaterialTypeEnum::INTERMEDIATE,
            default => RawMaterialTypeEnum::STOCK,
        };

        $state = match ($this->auroraModelData->{'Raw Material State'}) {
            'InUse' => RawMaterialStateEnum::IN_USE,
            'Orphan' => RawMaterialStateEnum::ORPHAN,
            'Discontinued' => RawMaterialStateEnum::DISCONTINUED,
            default => RawMaterialStateEnum::IN_PROCESS,
        };

        $unit = match ($this->auroraModelData->{'Raw Material Unit'}) {
            'Pack' => RawMaterialUnitEnum::PACK,
            'Carton' => RawMaterialUnitEnum::CARTON,
            'Liter' => RawMaterialUnitEnum::LITER,
            'Kilogram' => RawMaterialUnitEnum::KILOGRAM,
            default => RawMaterialUnitEnum::UNIT,
        };

        $stockStatus = match ($this->auroraModelData->{'Raw Material Stock Status'}) {
            'Unlimited' => RawMaterialStockStatusEnum::UNLIMITED,
            'Surplus' => RawMaterialStockStatusEnum::SURPLUS,
            'Low' => RawMaterialStockStatusEnum::LOW,
            'Critical' => RawMaterialStockStatusEnum::CRITICAL,
            'Out_Of_Stock' => RawMaterialStockStatusEnum::OUT_OF_STOCK,
            'Error' => RawMaterialStockStatusEnum::ERROR,
            default => RawMaterialStockStatusEnum::OPTIMAL,
        };

        $this->parsedData['raw_material'] = [
            'type'                 => $type,
            'state'                => $state,
            'code'                 => $code,
            'description'          => Str::limit($description, 252),
            'unit'                 => $unit,
            'unit_cost'            => $this->auroraModelData->{'Raw Material Unit Cost'} ?? 0,
            'quantity_on_location' => $this->auroraModelData->{'Raw Material Stock'} ?? 0,
            'stock_status'         => $stockStatus,
            'created_at'           => $this->parseDatetime($this->auroraModelData->{'Raw Material Creation Date'}),
            'source_id'            => $this->organisation->id.':'.$this->auroraModelData->{'Raw Material Key'},
        ];
    }

    protected function fetchData($id): object|null
    {
        return DB::connection('aurora')
            ->table('Raw Material Dimension')
            ->where('Raw Material Key', $id)
            ->first();
    }
}
