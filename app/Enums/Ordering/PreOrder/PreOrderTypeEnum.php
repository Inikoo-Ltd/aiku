<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 28 Sep 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\Ordering\PreOrder;

use App\Enums\EnumHelperTrait;

enum PreOrderTypeEnum: string
{
    use EnumHelperTrait;

    case BACK_ORDER = 'back_order';
    case MADE_TO_ORDER = 'made_to_order';

    public function label(): string
    {
        return match ($this) {
            self::BACK_ORDER => __('Back-order'),
            self::MADE_TO_ORDER => __('Made to order'),
        };
    }
}
