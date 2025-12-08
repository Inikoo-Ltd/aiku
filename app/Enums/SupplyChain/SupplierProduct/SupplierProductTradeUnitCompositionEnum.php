<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 11 Aug 2024 16:23:30 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\SupplyChain\SupplierProduct;

use App\Enums\EnumHelperTrait;

enum SupplierProductTradeUnitCompositionEnum: string
{
    use EnumHelperTrait;

    case MATCH = 'match';
    case MULTIPLE = 'multiple';
    case MULTIPLE_REPACKED = 'multiple_repacked';
    case MIX = 'mix';
}
