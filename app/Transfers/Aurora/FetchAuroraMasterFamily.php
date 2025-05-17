<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 28 Dec 2024 00:15:18 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use Illuminate\Support\Facades\DB;

class FetchAuroraMasterFamily extends FetchAurora
{
    use WithAuroraImages;
    use WithMasterFetch;

    protected function parseModel(): void
    {
        $masterShop = $this->getMasterShop();

        if ($masterShop == null) {
            return;
        }

        if ($this->auroraModelData->{'Product Category Status'} != 'Active') {
            return;
        }
        $code = $this->cleanTradeUnitReference($this->auroraModelData->{'Category Code'});

        $numberActiveProducts = DB::connection('aurora')
            ->table('Product Dimension')
            ->where('Product Family Category Key', $this->auroraModelData->{'Category Key'})
            ->whereIn('Product Status', ['Active', 'Discontinuing'])
            ->whereNot('Product Web Configuration', 'Offline')
            ->count();

        if ($numberActiveProducts == 0) {
            return;
        }


        $masterDepartment = $this->organisation->group->masterProductCategories()->where('source_department_id', $this->organisation->id.':'.$this->auroraModelData->{'Product Category Department Category Key'})->first();


        if (in_array($code, [
            'Promo_UK',
            'Bonus100',
            'GR',
            'AW100',
            'PuckJBB',
            'PuckJDBB',
            'PuckSBB',
            'PuckMFH',
            'PuckMFH',
            'MegaC',
            'FRC',
            '10p',
            'Info',
            'GloveSupply'
        ])) {
            return;
        }


        $this->parsedData['parent'] = $masterDepartment ?? $masterShop;

        $this->parsedData['master_family'] = [
            'type'             => MasterProductCategoryTypeEnum::FAMILY,
            'code'             => $code,
            'name'             => $this->auroraModelData->{'Category Label'},
            'source_family_id' => $this->organisation->id.':'.$this->auroraModelData->{'Category Key'},
            'images'           => $this->parseImages(),
            'fetched_at'       => now(),
            'last_fetched_at'  => now(),
        ];

        $createdAt = $this->parseDatetime($this->auroraModelData->{'Product Category Valid From'});
        if ($createdAt) {
            $this->parsedData['master_family']['created_at'] = $createdAt;
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
