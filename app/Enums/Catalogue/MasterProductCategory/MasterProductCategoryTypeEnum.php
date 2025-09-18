<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 25 Dec 2024 02:14:11 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Catalogue\MasterProductCategory;

use App\Enums\EnumHelperTrait;

enum MasterProductCategoryTypeEnum: string
{
    use EnumHelperTrait;

    case DEPARTMENT     = 'department';
    case SUB_DEPARTMENT = 'sub_department';
    case FAMILY         = 'family';

    public function label(): string
    {
        return match ($this) {
            MasterProductCategoryTypeEnum::DEPARTMENT => 'Master Department',
            MasterProductCategoryTypeEnum::SUB_DEPARTMENT => 'Master Sub Department',
            MasterProductCategoryTypeEnum::FAMILY => 'Master Family',
        };
    }
}
