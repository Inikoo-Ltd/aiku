<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Jun 2024 12:55:10 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use App\Actions\Utils\Abbreviate;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use Illuminate\Support\Facades\DB;

class FetchAuroraDepartment extends FetchAurora
{
    use WithAuroraImages;

    protected function parseModel(): void
    {

        $shop = $this->parseShop($this->organisation->id.':'.$this->auroraModelData->{'Product Category Store Key'});



        $departmentsRootAuroraIDs = DB::connection('aurora')->table('Category Dimension')
            ->select('Category Key', 'Category Code', 'Category Subject')
            ->where('Category Branch Type', 'Root')
            ->where('Category Scope', 'Product')
            ->where('Category Code', 'like', 'Web.%')
            ->where('Category Subject', 'Category')
            ->get()->pluck('Category Key')->toArray();

        if (in_array($this->auroraModelData->{'Category Root Key'}, $departmentsRootAuroraIDs)) {
            return;
        }


        $this->parsedData['shop'] = $shop;

        $code = $this->cleanTradeUnitReference($this->auroraModelData->{'Category Code'});

        if ($code == '40%') {
            $code = '40off';
        }

        if (strlen($code) > 32) {
            $code = Abbreviate::run($code, 32);
        }

        $this->parsedData['department'] = [
            'type'                 => ProductCategoryTypeEnum::DEPARTMENT,
            'code'                 => $code,
            'name'                 => $this->auroraModelData->{'Category Label'},
            'source_department_id' => $this->organisation->id.':'.$this->auroraModelData->{'Category Key'},
            'fetched_at'           => now(),
            'last_fetched_at'      => now(),
            'images'               => $this->parseImages(),
        ];

        $createdAt = $this->parseDatetime($this->auroraModelData->{'Product Category Valid From'});
        if ($createdAt) {
            $this->parsedData['department']['created_at'] = $createdAt;
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
