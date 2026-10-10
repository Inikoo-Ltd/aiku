<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 10 Oct 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\Goods\Packaging;

use App\Enums\EnumHelperTrait;

enum PackagingEndUseEnum: string
{
    use EnumHelperTrait;

    case HOUSEHOLD     = 'household';
    case NON_HOUSEHOLD = 'non_household';
    case MIXED         = 'mixed';

    public static function labels(): array
    {
        return [
            'household'     => __('Household'),
            'non_household' => __('Non-household'),
            'mixed'         => __('Mixed'),
        ];
    }
}
