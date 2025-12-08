<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 31 May 2024 20:15:32 Central European Summer Time, Mijas Costa, Spain
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Helpers\Barcode;

use App\Enums\EnumHelperTrait;

enum BarcodePrefixEnum: string
{
    use EnumHelperTrait;

    case PALLET_DELIVERY = 'pad';
    case PALLET = 'pal';
    case STORED_ITEM = 'sto';
    case LOCATION = 'loc';
    case PALLET_RETURN = 'par';

    public static function prefixConnector(): string
    {
        return '~';
    }
}
