<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 26 Dec 2024 12:07:39 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Goods\Stock;

use App\Enums\EnumHelperTrait;

enum StockTradeUnitCompositionEnum: string
{
    use EnumHelperTrait;

    case MATCH = 'match';
    case MULTIPLE = 'multiple';
    case MULTIPLE_REPACKED = 'multiple_repacked';
    case MIX = 'mix';
}
