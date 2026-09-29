<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 29 Sep 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\ProductCategory;

use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;

class GetSubDepartmentTimeSeriesStats extends GetDepartmentTimeSeriesStats
{
    protected function categoryType(): ProductCategoryTypeEnum
    {
        return ProductCategoryTypeEnum::SUB_DEPARTMENT;
    }
}
