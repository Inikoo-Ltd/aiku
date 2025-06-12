<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 10 Jun 2025 17:33:13 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use App\Actions\Utils\Abbreviate;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use Illuminate\Support\Facades\DB;

class FetchAuroraCollection extends FetchAurora
{
    use WithAuroraImages;

    protected function parseModel(): void
    {
        $shop = $this->parseShop($this->organisation->id.':'.$this->auroraModelData->{'Product Category Store Key'});

        if ($shop->type == ShopTypeEnum::DROPSHIPPING) {
            return;
        }

        $collectionsRootAuroraIDs = DB::connection('aurora')->table('Category Dimension')
            ->select('Category Key', 'Category Code', 'Category Subject')
            ->where('Category Branch Type', 'Root')
            ->where('Category Scope', 'Product')
            ->where('Category Code', 'like', 'Web.%')
            ->get()->pluck('Category Key')->toArray();


        if (!in_array($this->auroraModelData->{'Category Root Key'}, $collectionsRootAuroraIDs)) {
            return;
        }


        $code = $this->cleanTradeUnitReference($this->auroraModelData->{'Category Code'});
        $code = trim($code);

        if ($code == '40%') {
            $code = '40off';
        }

        $code = preg_replace('/\+/', 'and', $code);

        if (strlen($code) > 32) {
            $code = Abbreviate::run($code, 32);
        }

        if ($code == '') {
            return;
        }
        $this->parsedData['shop'] = $shop;

        $this->parsedData['collection'] = [
            'type'            => ProductCategoryTypeEnum::DEPARTMENT,
            'code'            => $code,
            'name'            => $this->auroraModelData->{'Category Label'},
            'source_id'       => $this->organisation->id.':'.$this->auroraModelData->{'Category Key'},
            'fetched_at'      => now(),
            'last_fetched_at' => now(),
            'images'          => $this->parseImages(),
        ];

        $createdAt = $this->parseDatetime($this->auroraModelData->{'Product Category Valid From'});
        if ($createdAt) {
            $this->parsedData['collection']['created_at'] = $createdAt;
        }


        $subjects = DB::connection('aurora')
            ->table('Category Bridge')
            ->where('Category Key', $this->auroraModelData->{'Category Key'})->get();
        $models   = [];
        foreach ($subjects as $subject) {
            $modelAuroraType = $subject->{'Subject'};
            $modelAuroraId   = $subject->{'Subject Key'};
            $model           = null;
            if ($modelAuroraType == 'Product') {
                $model = $this->parseProduct($shop->organisation->id.':'.$modelAuroraId);
            } else {
                $model = $this->parseFamily($shop->organisation->id.':'.$modelAuroraId);
            }

            if ($model) {
                $models[] = $model;
            }
        }
        $this->parsedData['models'] = $models;
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
