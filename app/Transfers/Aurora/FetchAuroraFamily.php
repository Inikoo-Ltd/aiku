<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Jun 2024 12:55:09 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use Illuminate\Support\Facades\DB;

class FetchAuroraFamily extends FetchAurora
{
    use WithAuroraImages;

    protected function parseModel(): void
    {
        $parent        = null;

        if ($this->auroraModelData->{'Product Category Department Category Key'}) {


            $parent        = $this->parseDepartment($this->organisation->id.':'.$this->auroraModelData->{'Product Category Department Category Key'});

        }
        if (!$parent) {
            $parent = $this->parseShop($this->organisation->id.':'.$this->auroraModelData->{'Product Category Store Key'});
        }

        $this->parsedData['parent'] = $parent;

        $code = $this->cleanTradeUnitReference($this->auroraModelData->{'Category Code'});


        $this->parsedData['family'] = [
            'type'                     => ProductCategoryTypeEnum::FAMILY,
            'code'                     => $code,
            'name'                     => $this->auroraModelData->{'Category Label'},
            'source_family_id'         => $this->organisation->id.':'.$this->auroraModelData->{'Category Key'},
            'images'                   => $this->parseImages(),
            'fetched_at'               => now(),
            'last_fetched_at'          => now(),
        ];

        $createdAt = $this->parseDatetime($this->auroraModelData->{'Product Category Valid From'});
        if ($createdAt) {
            $this->parsedData['family']['created_at'] = $createdAt;
        }
    }

    private function parseImages(): array
    {
        $images = $this->getModelImagesCollection(
            'Category',
            $this->auroraModelData->{'Category Key'}
        )->map(function ($auroraImage) {
            return $this->fetchImage($auroraImage);
        });

        return $images->toArray();
    }

    protected function fetchData($id): object|null
    {
        return DB::connection('aurora')
            ->table('Category Dimension')
            ->leftJoin('Product Category Dimension', 'Product Category Key', 'Category Key')
            ->where('Category Key', $id)->first();
    }
}
