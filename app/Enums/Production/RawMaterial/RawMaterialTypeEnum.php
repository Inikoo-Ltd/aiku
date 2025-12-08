<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 07 May 2024 21:09:00 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Production\RawMaterial;

use App\Enums\EnumHelperTrait;

enum RawMaterialTypeEnum: string
{
    use EnumHelperTrait;

    case STOCK = 'stock';
    case CONSUMABLE = 'consumable';
    case INTERMEDIATE = 'intermediate';

}
