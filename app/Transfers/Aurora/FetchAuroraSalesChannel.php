<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Nov 2024 19:07:40 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use App\Enums\Ordering\SalesChannel\SalesChannelTypeEnum;
use Illuminate\Support\Facades\DB;

class FetchAuroraSalesChannel extends FetchAurora
{
    protected function parseModel(): void
    {
        $type = match ($this->auroraModelData->{'Order Source Type'}) {
            'website' => SalesChannelTypeEnum::WEBSITE,
            'phone' => SalesChannelTypeEnum::PHONE,
            'show' => SalesChannelTypeEnum::SHOWROOM,
            'email' => SalesChannelTypeEnum::EMAIL,
            'marketplace' => SalesChannelTypeEnum::MARKETPLACE,
            default => SalesChannelTypeEnum::OTHER,
        };

        $code = trim(strtolower($this->auroraModelData->{'Order Source Code'}));

        $this->parsedData['sales_channel'] = [
            'type' => $type,
            'source_id' => $this->organisation->id.':'.$this->auroraModelData->{'Order Source Key'},
            'fetched_at' => now(),
            'last_fetched_at' => now(),
            'is_seeded' => $type != SalesChannelTypeEnum::MARKETPLACE,
        ];

        if ($type == SalesChannelTypeEnum::MARKETPLACE) {
            $code = match ($code) {
                'ankorstore-ac' => 'ankorstore',
                'faire-ac', 'faire-aroma' => 'faire',
                default => $code
            };

            $this->parsedData['sales_channel']['code'] = $code;
            $this->parsedData['sales_channel']['name'] = $this->auroraModelData->{'Order Source Name'};
        }
    }

    protected function fetchData($id): ?object
    {
        return DB::connection('aurora')
            ->table('Order Source Dimension')
            ->where('Order Source Key', $id)->first();
    }
}
