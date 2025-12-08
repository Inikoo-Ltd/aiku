<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 17 Nov 2024 15:10:13 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use App\Enums\CRM\Poll\PollTypeEnum;
use Illuminate\Support\Facades\DB;

class FetchAuroraPoll extends FetchAurora
{
    protected function parseModel(): void
    {
        $this->parsedData['shop'] = $this->parseShop($this->organisation->id.':'.$this->auroraModelData->{'Customer Poll Query Store Key'});
        $this->parsedData['poll'] = [
            'type' => $this->auroraModelData->{'Customer Poll Query Type'} == 'Options' ? PollTypeEnum::OPTION : PollTypeEnum::OPEN_QUESTION,
            'position' => $this->auroraModelData->{'Customer Poll Query Position'},
            'name' => $this->auroraModelData->{'Customer Poll Query Name'},
            'label' => $this->auroraModelData->{'Customer Poll Query Label'},
            'in_registration' => $this->auroraModelData->{'Customer Poll Query In Registration'} == 'Yes',
            'in_registration_required' => $this->auroraModelData->{'Customer Poll Query Registration Required'} == 'Yes',
            'in_iris' => $this->auroraModelData->{'Customer Poll Query In Profile'} == 'Yes',
            'source_id' => $this->organisation->id.':'.$this->auroraModelData->{'Customer Poll Query Key'},
            'fetched_at' => now(),
            'last_fetched_at' => now(),
        ];
    }

    protected function fetchData($id): ?object
    {
        return DB::connection('aurora')
            ->table('Customer Poll Query Dimension')
            ->where('Customer Poll Query Key', $id)->first();
    }
}
