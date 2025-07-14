<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Jun 2024 12:55:09 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use Arr;
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


        $grossWeight     = null;
        $marketingWeight = null;


        if ($this->auroraModelData->{'Part Package Weight'} > 0) {
            $grossWeight = round(1000 * $this->auroraModelData->{'Part Package Weight'} / $this->auroraModelData->{'Part Units Per Package'});
        }

        if ($this->auroraModelData->{'Part Unit Weight'} > 0) {
            $marketingWeight = round(1000 * $this->auroraModelData->{'Part Unit Weight'});
        }

        $dimensions = null;
        if ($this->auroraModelData->{'Part Unit Dimensions'}) {
            $rawDimensions = json_decode($this->auroraModelData->{'Part Unit Dimensions'}, true);

            $dimensions = $this->parseDimension($rawDimensions);
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

        if ($dimensions) {
            $this->parsedData['trade_unit']['marketing_dimensions'] = $dimensions;
        }


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


    protected function fetchData($id): object|null
    {
        return DB::connection('aurora')
            ->table('Part Dimension')
            ->where('Part SKU', $id)->first();
    }


    public function parseDimension($rawDimensions): ?array
    {
        $dimensions = null;

        if (Arr::has($rawDimensions, 'l')) {
            $dimensions['l'] = round(1000 * $rawDimensions['l']) / 1000;
        } else {
            return null;
        }

        if (Arr::has($rawDimensions, 'w')) {
            $dimensions['w'] = round(1000 * $rawDimensions['w']) / 1000;
        } else {
            return null;
        }
        if (Arr::has($rawDimensions, 'h')) {
            $dimensions['h'] = round(1000 * $rawDimensions['h']) / 1000;
        } else {
            return null;
        }

        if (Arr::has($rawDimensions, 'units')) {
            $dimensions['units'] = $rawDimensions['units'];
        } else {
            return null;
        }

        if (Arr::has($rawDimensions, 'type')) {
            $dimensions['type'] = strtolower($rawDimensions['type']);
        } else {
            return null;
        }

        if ($dimensions['l'] == 0 && $dimensions['w'] == 0 && $dimensions['h'] == 0) {
            return null;
        }

        return $dimensions;
    }

}
