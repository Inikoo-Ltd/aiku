<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Jun 2024 12:55:09 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FetchAuroraTradeUnit extends FetchAurora
{
    use WithAuroraImages;
    use WithAuroraParsers;

    protected function parseModel(): void
    {
        $reference = $this->cleanTradeUnitReference($this->auroraModelData->{'Part Reference'});


        $sourceSlug = Str::lower($reference);


        $name = $this->auroraModelData->{'Part Recommended Product Unit Name'};
        if ($name == '') {
            $name = $reference;
        }


        $grossWeight = null;
        $marketingWeight   = null;


        // we trust only organisation 2 for weights (SK)
        if ($this->organisation->id == 2) {
            if ($this->auroraModelData->{'Part Package Weight'} > 0) {
                $grossWeight = round(1000 * $this->auroraModelData->{'Part Package Weight'} / $this->auroraModelData->{'Part Units Per Package'});
            }

            if ($this->auroraModelData->{'Part Unit Weight'} > 0) {
                $marketingWeight = round(1000 * $this->auroraModelData->{'Part Unit Weight'});
            }
        }


        $this->parsedData['trade_unit'] = [
            'name'            => $name,
            'code'            => $reference,
            'source_id'       => $this->organisation->id.':'.$this->auroraModelData->{'Part SKU'},
            'source_slug'     => $sourceSlug,
            'fetched_at'      => now(),
            'last_fetched_at' => now(),
        ];

        if ($grossWeight) {
            $this->parsedData['trade_unit']['gross_weight'] = $grossWeight;
        }

        if ($marketingWeight) {
            $this->parsedData['trade_unit']['marketing_weight'] = $marketingWeight;
        }


        // we trust only organisation 2 for barcodes (SK)
        if ($this->organisation->id == 2) {
            $barcodes = [];


            $auroraBarcodes = DB::connection('aurora')
                ->table('Barcode Asset Bridge')
                ->where('Barcode Asset Type', 'Part')
                ->where('Barcode Asset Key', $this->auroraModelData->{'Part SKU'})
                ->get();

            foreach ($auroraBarcodes as $auroraBarcode) {
                $barcode = $this->parseBarcode($this->organisation->id.':'.$auroraBarcode->{'Barcode Asset Barcode Key'});

                if (!$barcode) {
                    continue;
                }


                $barcodeData = [
                    'type'         => 'ean',
                    'status'       => $auroraBarcode->{'Barcode Asset Status'} === 'Assigned',
                    'withdrawn_at' => $this->parseDatetime($auroraBarcode->{'Barcode Asset Withdrawn Date'})
                ];
                $createdAt   = $this->parseDatetime($auroraBarcode->{'Barcode Asset Assigned Date'});

                if ($createdAt) {
                    $barcodeData['created_at'] = $createdAt;
                }

                $barcodes[$barcode->id] = $barcodeData;
            }
            $this->parsedData['barcodes'] = $barcodes;
        }

    }


    protected function fetchData($id): object|null
    {
        return DB::connection('aurora')
            ->table('Part Dimension')
            ->where('Part SKU', $id)->first();
    }


}
