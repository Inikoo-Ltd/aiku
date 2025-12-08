<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Jun 2024 12:55:10 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use App\Enums\Helpers\Barcode\BarcodeStatusEnum;
use App\Enums\Helpers\Barcode\BarcodeTypeEnum;
use Illuminate\Support\Facades\DB;

class FetchAuroraBarcode extends FetchAurora
{
    protected function parseModel(): void
    {
        $status = match ($this->auroraModelData->{'Barcode Status'}) {
            'Used' => BarcodeStatusEnum::USED,
            'Reserved' => BarcodeStatusEnum::RESERVED,
            default => BarcodeStatusEnum::AVAILABLE
        };

        $this->parsedData['barcode'] = [
            'number' => $this->auroraModelData->{'Barcode Number'},
            'source_id' => $this->organisation->id.':'.$this->auroraModelData->{'Barcode Key'},
            'type' => BarcodeTypeEnum::EAN,
            'status' => $status,
            'note' => $this->auroraModelData->{'Barcode Sticky Note'},
            'assigned_at' => $this->auroraModelData->{'Barcode Used From'} ? $this->parseDatetime($this->auroraModelData->{'Barcode Used From'}) : null,
            'fetched_at' => now(),
            'last_fetched_at' => now(),
        ];
    }

    protected function fetchData($id): ?object
    {
        return DB::connection('aurora')
            ->table('Barcode Dimension')
            ->where('Barcode Key', $id)->first();
    }
}
