<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 17 Nov 2024 15:24:04 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use Illuminate\Support\Facades\DB;

class FetchAuroraPollOption extends FetchAurora
{
    protected function parseModel(): void
    {
        $this->parsedData['poll'] = $this->parsePoll($this->organisation->id.':'.$this->auroraModelData->{'Customer Poll Query Option Query Key'});
        $this->parsedData['poll_option'] = [
            'value' => $this->auroraModelData->{'Customer Poll Query Option Name'},
            'label' => $this->auroraModelData->{'Customer Poll Query Option Label'},
            'source_id' => $this->organisation->id.':'.$this->auroraModelData->{'Customer Poll Query Option Key'},
            'fetched_at' => now(),
            'last_fetched_at' => now(),
        ];
    }

    protected function fetchData($id): ?object
    {
        return DB::connection('aurora')
            ->table('Customer Poll Query Option Dimension')
            ->where('Customer Poll Query Option Key', $id)->first();
    }
}
