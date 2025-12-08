<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Jun 2024 12:55:10 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use App\Actions\Utils\Abbreviate;
use App\Enums\Inventory\Warehouse\WarehouseStateEnum;
use Illuminate\Support\Facades\DB;

class FetchAuroraWarehouse extends FetchAurora
{
    protected function parseModel(): void
    {
        $this->parsedData['warehouse'] = [
            'code' => Abbreviate::run($this->auroraModelData->{'Warehouse Name'}),
            'name' => $this->auroraModelData->{'Warehouse Name'},
            'state' => match ($this->auroraModelData->{'Warehouse State'}) {
                'Active' => WarehouseStateEnum::OPEN->value,
                default => WarehouseStateEnum::CLOSED->value
            },
            'settings' => [
                'address_link' => 'Organisation:default',
            ],
            'source_id' => $this->organisation->id.':'.$this->auroraModelData->{'Warehouse Key'},
            'created_at' => $this->auroraModelData->{'Warehouse Valid From'} ?? null,
            'fetched_at' => now(),
            'last_fetched_at' => now(),
        ];
    }

    protected function fetchData($id): ?object
    {
        return DB::connection('aurora')
            ->table('Warehouse Dimension')
            ->where('Warehouse Key', $id)
            ->first();
    }
}
