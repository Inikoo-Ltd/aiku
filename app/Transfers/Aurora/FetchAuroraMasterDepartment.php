<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Dec 2024 21:51:24 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use App\Actions\Utils\Abbreviate;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use Illuminate\Support\Facades\DB;

class FetchAuroraMasterDepartment extends FetchAurora
{
    protected function parseModel(): void
    {
        $shop = $this->parseShop($this->organisation->id.':'.$this->auroraModelData->{'Product Category Store Key'});

        $masterShop = null;


        if (in_array($shop->slug, ['uk', 'awd', 'awf', 'aroma', 'acar'])) {
            $masterShop = $shop->masterShop;
        }
        if ($masterShop == null) {
            return;
        }

        if ($this->auroraModelData->{'Product Category Status'} != 'Active') {
            return;
        }


        $this->parsedData['master_shop'] = $masterShop;

        $code = $this->cleanTradeUnitReference($this->auroraModelData->{'Category Code'});

        if ($code == '40%') {
            $code = '40off';
        }

        if (strlen($code) > 32) {
            $code = Abbreviate::run($code, 32);
        }


        $this->parsedData['master_department'] = [
            'type'                 => MasterProductCategoryTypeEnum::DEPARTMENT,
            'code'                 => $code,
            'name'                 => $this->auroraModelData->{'Category Label'},
            'source_department_id' => $this->organisation->id.':'.$this->auroraModelData->{'Category Key'},
            'fetched_at'           => now(),
            'last_fetched_at'      => now(),
        ];

        $createdAt = $this->parseDatetime($this->auroraModelData->{'Product Category Valid From'});
        if ($createdAt) {
            $this->parsedData['master_department']['created_at'] = $createdAt;
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
