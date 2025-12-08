<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 05 Jul 2024 01:11:52 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Fulfilment\FulfilmentTransaction;

use App\Enums\EnumHelperTrait;

enum FulfilmentTransactionTypeEnum: string
{
    use EnumHelperTrait;

    case PRODUCT = 'product';
    case SERVICE = 'service';

    public static function labels(): array
    {
        return [
            'product' => __('Product'),
            'service' => __('Services'),
        ];

    }
}
