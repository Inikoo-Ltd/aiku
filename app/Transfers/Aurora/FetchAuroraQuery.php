<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 14 Nov 2024 13:08:52 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use Illuminate\Support\Facades\DB;

class FetchAuroraQuery extends FetchAurora
{
    protected function parseModel(): void
    {
        if (! ($this->auroraModelData->{'List Scope'} == 'Customer' && $this->auroraModelData->{'List Use Type'} == 'UserCreated')) {
            return;
        }

        $shop = $this->parseShop($this->organisation->id.':'.$this->auroraModelData->{'List Parent Key'});

        $isStatic = $this->auroraModelData->{'List Type'} === 'Static';

        $this->parsedData['shop'] = $shop;

        $this->parsedData['query'] = [
            'name' => $this->auroraModelData->{'List Name'},
            'model' => 'Customer',
            'constrains' => [],
            'source_constrains' => json_decode($this->auroraModelData->{'List Metadata'}, true),
            'created_at' => $this->parseDatetime($this->auroraModelData->{'List Creation Date'}),
            'is_static' => $isStatic,
            'fetched_at' => now(),
            'last_fetched_at' => now(),
            'source_id' => $this->organisation->id.':'.$this->auroraModelData->{'List Key'},
        ];
    }

    protected function fetchData($id): ?object
    {
        return DB::connection('aurora')
            ->table('List Dimension')
            ->where('List Key', $id)->first();
    }
}
