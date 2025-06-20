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
        $shop = $this->parseShop($this->organisation->id.':'.$this->auroraModelData->{'Product Category Store Key'});


        $familiesRootAuroraIDs = DB::connection('aurora')->table('Category Dimension')
            ->select('Category Key', 'Category Code', 'Category Subject')
            ->where('Category Branch Type', 'Root')
            ->where('Category Scope', 'Product')
            ->where('Category Code', 'like', 'Web.%')
            ->where('Category Subject', 'Product')
            ->get()->pluck('Category Key')->toArray();

        if (in_array($this->auroraModelData->{'Category Root Key'}, $familiesRootAuroraIDs)) {
            return;
        }

        $parent = null;

        if ($this->auroraModelData->{'Product Category Department Category Key'}) {
            $parent = $this->parseDepartment($this->organisation->id.':'.$this->auroraModelData->{'Product Category Department Category Key'});
        }
        if (!$parent) {
            $parent = $this->parseShop($this->organisation->id.':'.$this->auroraModelData->{'Product Category Store Key'});
        }


        $code = $this->cleanTradeUnitReference($this->auroraModelData->{'Category Code'});


        if ($code == '') {
            return;
        }

        $this->parsedData['parent'] = $parent;


        $this->parsedData['family'] = [
            'type'             => ProductCategoryTypeEnum::FAMILY,
            'code'             => $code,
            'name'             => $this->auroraModelData->{'Category Label'},
            'source_family_id' => $this->organisation->id.':'.$this->auroraModelData->{'Category Key'},
            'image'            => $this->parseImage(),
            'fetched_at'       => now(),
            'last_fetched_at'  => now(),
        ];

        $createdAt = $this->parseDatetime($this->auroraModelData->{'Product Category Valid From'});
        if ($createdAt) {
            $this->parsedData['family']['created_at'] = $createdAt;
        }
    }

    private function parseImage(): array
    {
        $image = $this->getModelMainImage(
            'Category',
            $this->auroraModelData->{'Category Key'}
        );

        if ($image) {
            return $this->fetchImage($image);
        } else {
            return [];
        }


    }


    protected function fetchData($id): object|null
    {
        return DB::connection('aurora')
            ->table('Category Dimension')
            ->leftJoin('Product Category Dimension', 'Product Category Key', 'Category Key')
            ->where('Category Key', $id)->first();
    }
}
