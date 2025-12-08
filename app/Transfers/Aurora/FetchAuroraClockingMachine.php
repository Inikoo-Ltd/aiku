<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Jun 2024 12:52:18 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use App\Enums\HumanResources\ClockingMachine\ClockingMachineTypeEnum;
use Illuminate\Support\Facades\DB;

class FetchAuroraClockingMachine extends FetchAurora
{
    protected function parseModel(): void
    {
        $this->parsedData['workplace'] = $this->organisation->workplaces()->first();

        $type = match ($this->auroraModelData->{'Clocking Machine Code'}) {
            'app-v1' => ClockingMachineTypeEnum::MOBILE_APP,
            default => ClockingMachineTypeEnum::LEGACY
        };

        $this->parsedData['clocking-machine'] = [
            'name' => $this->auroraModelData->{'Clocking Machine Code'},
            'source_id' => $this->organisation->id.':'.$this->auroraModelData->{'Clocking Machine Key'},
            'type' => $type,
            'created_at' => $this->parseDatetime($this->auroraModelData->{'Clocking Machine Creation Date'}),
            'fetched_at' => now(),
            'last_fetched_at' => now(),
        ];

        $createdBy = $this->auroraModelData->{'Clocking Machine Creation Date'};

        if ($createdBy) {
            $this->parsedData['clocking-machine']['created_by'] = $createdBy;
        }
    }

    protected function fetchData($id): ?object
    {
        return DB::connection('aurora')
            ->table('Clocking Machine Dimension')
            ->where('Clocking Machine Key', $id)->first();
    }
}
