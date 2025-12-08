<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 20 Nov 2024 15:27:48 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Billables\Shipping;

use App\Enums\Catalogue\IsBillableState;
use App\Enums\EnumHelperTrait;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;

enum ShippingStateEnum: string
{
    use EnumHelperTrait;
    use IsBillableState;

    case IN_PROCESS = 'in_process';
    case ACTIVE = 'active';
    case DISCONTINUED = 'discontinued';

    public static function count(Shop|Organisation|Group $parent): array
    {
        $stats = match (class_basename($parent)) {
            'Shop' => $parent->stats,
            'Organisation', 'Group' => $parent->catalogueStats,
        };

        return [
            'in_process' => $stats->number_shippings_state_in_process,
            'active' => $stats->number_shippings_state_active,
            'discontinued' => $stats->number_shippings_state_discontinued,
        ];
    }
}
