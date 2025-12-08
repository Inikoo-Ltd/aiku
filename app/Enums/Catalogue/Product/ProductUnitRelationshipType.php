<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 01 Jun 2024 20:07:21 Central European Summer Time, Mijas Costa, Spain
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Catalogue\Product;

use App\Enums\EnumHelperTrait;

enum ProductUnitRelationshipType: string
{
    use EnumHelperTrait;

    case SINGLE = 'single';
    case MULTIPLE = 'multiple';
    case MULTIPLE_REPACKED = 'multiple_repacked';
    case MIX = 'mix';
}
