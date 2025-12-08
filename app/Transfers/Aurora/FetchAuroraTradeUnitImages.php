<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Jun 2024 12:55:10 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use Illuminate\Support\Facades\DB;

class FetchAuroraTradeUnitImages extends FetchAurora
{
    use WithAuroraImages;

    protected function parseModel(): void
    {

        $images = [];

        $stockImages = $this->getModelImagesCollection(
            'Part',
            $this->auroraModelData->{'Part SKU'}
        )->map(function ($auroraImage) {
            return $this->fetchImage($auroraImage);
        })->all();

        $images = array_merge($images, $stockImages);

        foreach (
            DB::connection('aurora')
                ->table('Product Part Bridge')
                ->where('Product Part Part SKU', $this->auroraModelData->{'Part SKU'})->get() as $auroraProductsData
        ) {
            $productImages = $this->getModelImagesCollection(
                'Asset',
                $auroraProductsData->{'Product Part Product ID'}
            )->map(function ($auroraImage) {
                return $this->fetchImage($auroraImage);
            })->all();

            $images = array_merge($images, $productImages);
        }

        $this->parsedData['images'] = $images;

        $this->parsedData['trade_unit'] = [
            'source_id' => $this->organisation->id.':'.$this->auroraModelData->{'Part SKU'},
        ];

    }

    protected function fetchData($id): ?object
    {
        return DB::connection('aurora')
            ->table('Part Dimension')
            ->select('Part SKU')
            ->where('Part SKU', $id)->first();
    }
}
