<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 12 Mar 2023 23:47:41 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Enums\Catalogue\Shop;

use App\Enums\EnumHelperTrait;

enum ShopTypeEnum: string
{
    use EnumHelperTrait;

    case B2B          = 'b2b';
    case B2C          = 'b2c';
    case FULFILMENT   = 'fulfilment';
    case DROPSHIPPING = 'dropshipping';
    case EXTERNAL_SHOP = 'external_shop';

    public static function labels(): array
    {
        return [
            'b2b'              => __('B2B'),
            'b2c'              => __('B2C'),
            'fulfilment'       => __('Fulfilment'),
            'dropshipping'     => __('Dropshipping'),
            'external_shop'     => __('External Shop')
        ];
    }
}
