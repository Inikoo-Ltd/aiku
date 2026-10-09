<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 10 Oct 2026 13:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\Goods\Packaging;

use App\Enums\EnumHelperTrait;

/**
 * What a flow of goods counts as in a packaging EPR return, seen from the organisation that moved them.
 */
enum EprActivityEnum: string
{
    use EnumHelperTrait;

    case IMPORTED                = 'imported';
    case BOUGHT_DOMESTIC         = 'bought_domestic';
    case PURCHASE_UNKNOWN_ORIGIN = 'purchase_unknown_origin';
    case PACKED_FILLED           = 'packed_filled';
    case SOLD_DOMESTIC           = 'sold_domestic';
    case EXPORTED                = 'exported';
    case SALE_UNKNOWN_COUNTRY    = 'sale_unknown_country';

    public static function labels(): array
    {
        return [
            'imported'                => __('Imported'),
            'bought_domestic'         => __('Bought domestically'),
            'purchase_unknown_origin' => __('Bought, origin unknown'),
            'packed_filled'           => __('Packed or filled (own production)'),
            'sold_domestic'           => __('Sold domestically'),
            'exported'                => __('Exported'),
            'sale_unknown_country'    => __('Sold, destination unknown'),
        ];
    }
}
